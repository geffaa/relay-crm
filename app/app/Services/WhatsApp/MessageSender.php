<?php

namespace App\Services\WhatsApp;

use App\Models\Activity;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends outbound messages through the configured channel, enforcing the
 * 24-hour free-form window: outside it only approved templates may go out.
 */
class MessageSender
{
    public function __construct(private readonly MessagingChannelManager $channels) {}

    /** Re-sends a failed free-text message in place, so the thread keeps one bubble per message. */
    public function retry(Message $message): Message
    {
        $conversation = $message->conversation;
        $to = $conversation->contact->phone_e164;

        throw_if($message->direction !== 'out' || $message->status !== 'failed', ValidationException::withMessages(['body' => 'Only failed messages can be retried.']));
        throw_if(blank($to), ValidationException::withMessages(['body' => 'This contact has no phone number.']));
        throw_if($message->type !== 'text', ValidationException::withMessages(['body' => 'Template messages cannot be retried; send a new one.']));
        throw_if(! $conversation->canSendFreeform(), ValidationException::withMessages(['body' => 'The 24-hour reply window has closed. Use a template.']));

        try {
            $wamid = $this->channels->for($conversation->whatsappNumber)->sendText($conversation->whatsappNumber, $to, (string) $message->body);
            $message->update(['wamid' => $wamid, 'status' => 'sent', 'error' => null]);
        } catch (Throwable $e) {
            $message->update(['error' => ['message' => $e->getMessage()]]);

            throw $e;
        }

        return $message;
    }

    /**
     * Sends a file with an optional caption. Simulated numbers store the file locally;
     * live numbers need the Cloud API media upload, which this build does not include.
     */
    public function sendAttachment(Conversation $conversation, UploadedFile $file, ?string $caption, ?int $userId): Message
    {
        $number = $conversation->whatsappNumber;

        throw_if($number->mode !== 'simulated', ValidationException::withMessages(['file' => 'Attachments on live numbers need Cloud API media upload, which is not part of this build.']));
        throw_if(! $conversation->canSendFreeform(), ValidationException::withMessages(['file' => 'The 24-hour window is closed. Use a template first.']));

        $path = $file->store('whatsapp-media', 'public');
        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');

        $message = new Message([
            'direction' => 'out',
            'type' => $isImage ? 'image' : 'document',
            'body' => $caption,
            'media' => ['filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'url' => Storage::disk('public')->url($path)],
            'status' => 'sent',
            'sent_by' => $userId,
        ]);
        $message->conversation_id = $conversation->id;
        $message->save();

        $conversation->update(['last_message_at' => now(), 'unread_count' => 0]);
        Activity::record($conversation->company_id, $conversation->contact, 'message', $userId, [
            'direction' => 'out', 'text' => '[attachment] '.$file->getClientOriginalName(), 'conversation_id' => $conversation->id,
        ]);

        return $message;
    }

    public function send(Conversation $conversation, string $body, ?int $userId = null, ?string $templateName = null, ?string $templateLanguage = null): Message
    {
        $number = $conversation->whatsappNumber;
        $channel = $this->channels->for($number);
        $to = $conversation->contact->phone_e164;

        throw_if(blank($to), ValidationException::withMessages([
            'body' => 'This contact has no phone number.',
        ]));

        $freeform = $conversation->canSendFreeform();

        if (! $freeform && $templateName === null) {
            $closesAt = $conversation->windowClosesAt();

            throw ValidationException::withMessages([
                'body' => $closesAt
                    ? 'The 24-hour reply window closed at '.$closesAt->format('M j, H:i').'. Use a template.'
                    : 'No reply window is open for this contact. Use a template.',
            ]);
        }

        $message = new Message([
            'direction' => 'out',
            'type' => $templateName ? 'template' : 'text',
            'body' => $templateName ? "[template: {$templateName}] {$body}" : $body,
            'status' => 'queued',
            'sent_by' => $userId,
        ]);
        $message->conversation_id = $conversation->id;
        $message->save();

        try {
            $wamid = $templateName
                ? $channel->sendTemplate($number, $to, $templateName, $templateLanguage ?? 'en_US', [$body])
                : $channel->sendText($number, $to, $body);

            $message->update(['wamid' => $wamid, 'status' => 'sent']);
        } catch (Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }

        $conversation->update([
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);

        Activity::record($conversation->company_id, $conversation->contact, 'message', $userId, [
            'direction' => 'out',
            'text' => str($body)->limit(140),
            'conversation_id' => $conversation->id,
        ]);

        return $message;
    }

    /**
     * Template body with {1}-style placeholders resolved.
     */
    public static function renderTemplate(MessageTemplate $template, array $params = []): string
    {
        return preg_replace_callback('/\{(\d+)\}/', function ($m) use ($params) {
            return $params[(int) $m[1] - 1] ?? $m[0];
        }, $template->body);
    }
}
