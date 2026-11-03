<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppNumber;
use App\Services\ContactService;
use App\Services\RoundRobinAssigner;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Normalizes a Meta webhook payload into conversations/messages.
 * Idempotent on wamid: the same webhook delivered twice stores one message.
 */
class ProcessWhatsAppEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $companyId,
        public readonly array $payload,
    ) {}

    public function handle(ContactService $contacts, RoundRobinAssigner $assigner): void
    {
        $company = Company::find($this->companyId);
        if (! $company) {
            return;
        }

        $entries = $this->payload['entry'] ?? [];

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $this->processValue($company, $change['value'] ?? [], $contacts, $assigner);
            }
        }
    }

    private function processValue(Company $company, array $value, ContactService $contacts, RoundRobinAssigner $assigner): void
    {
        $number = WhatsAppNumber::forCompany($company)
            ->where('phone_number_id', $value['metadata']['phone_number_id'] ?? '')
            ->first();

        if (! $number) {
            Log::info('Webhook for unknown phone_number_id; ignored', [
                'company_id' => $company->id,
                'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
            ]);

            return;
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->processStatus($status);
        }

        foreach ($value['messages'] ?? [] as $message) {
            $this->processMessage($company, $number, $message, $value, $contacts, $assigner);
        }
    }

    private function processStatus(array $status): void
    {
        if (blank($status['id'] ?? null)) {
            return;
        }

        $message = Message::where('wamid', $status['id'])->first();
        if (! $message) {
            return;
        }

        $message->update([
            'status' => $status['status'] ?? $message->status,
            'error' => $status['errors'][0] ?? null,
        ]);
    }

    private function processMessage(Company $company, WhatsAppNumber $number, array $message, array $value, ContactService $contacts, RoundRobinAssigner $assigner): void
    {
        $wamid = $message['id'] ?? null;
        $from = $message['from'] ?? null;

        if (! $wamid || ! $from) {
            return;
        }

        // Idempotency: the same wamid (global unique) is stored exactly once.
        if (Message::where('wamid', $wamid)->exists()) {
            return;
        }

        $profileName = $value['contacts'][0]['profile']['name'] ?? null;

        // Meta gives numbers without "+"; normalize through libphonenumber.
        $contact = $contacts->findOrCreate($company, $profileName, '+'.$from, source: 'whatsapp');

        // company_id is not mass-assignable; stamp it explicitly.
        $conversation = Conversation::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('whatsapp_number_id', $number->id)
            ->first();

        if (! $conversation) {
            $conversation = new Conversation([
                'contact_id' => $contact->id,
                'whatsapp_number_id' => $number->id,
                'status' => 'open',
            ]);
            $conversation->company_id = $company->id;
            $conversation->save();
        }

        // New conversation: hand it to the next rep in rotation.
        $assigned = false;
        if (! $conversation->assignee_id) {
            $rep = $assigner->nextRep($company);
            if ($rep) {
                $conversation->assignee_id = $rep->id;
                $assigned = true;
            }
        }

        $timestamp = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'], 'UTC')
            : now();

        $type = $message['type'] ?? 'text';
        $body = $type === 'text' ? ($message['text']['body'] ?? null) : null;
        $media = match ($type) {
            'image' => ['id' => $message['image']['id'] ?? null, 'mime_type' => $message['image']['mime_type'] ?? null],
            'document' => [
                'id' => $message['document']['id'] ?? null,
                'filename' => $message['document']['filename'] ?? null,
                'mime_type' => $message['document']['mime_type'] ?? null,
            ],
            default => null,
        };

        Message::create([
            'conversation_id' => $conversation->id,
            'wamid' => $wamid,
            'direction' => 'in',
            'type' => $type,
            'body' => $body,
            'media' => $media,
            'status' => 'delivered',
            'created_at' => $timestamp,
        ]);

        $conversation->update([
            'last_inbound_at' => $timestamp,
            'last_message_at' => $timestamp,
            'unread_count' => $conversation->unread_count + 1,
        ]);

        SummarizeConversation::dispatch($conversation->id, $company->id);

        Activity::record(
            $company->id,
            $contact,
            'message',
            null,
            [
                'direction' => 'in',
                'text' => Str::limit((string) $body, 140),
                'conversation_id' => $conversation->id,
                'assigned_to' => $assigned ? $conversation->assignee_id : null,
            ],
            $timestamp,
        );
    }
}
