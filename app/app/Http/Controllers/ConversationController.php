<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Conversation;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\WhatsAppNumber;
use App\Notifications\ConversationAssigned;
use App\Services\WhatsApp\MessageSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private readonly MessageSender $sender) {}

    /**
     * S02 — inbox list. Views: unreplied | mine | all.
     */
    /** @return array<string, mixed> */
    private function inboxList(Request $request): array
    {
        $view = in_array($request->input('view'), ['unreplied', 'mine', 'all'], true) ? $request->input('view') : 'unreplied';

        $base = Conversation::query()
            ->with(['contact:id,name,score', 'assignee:id,name', 'whatsappNumber:id,display_phone,mode'])
            ->orderByDesc('last_message_at');

        $counts = [
            'unreplied' => (clone $base)->view('unreplied')->count(),
            'mine' => (clone $base)->view('mine')->count(),
            'all' => (clone $base)->count(),
        ];

        $conversations = (clone $base)->view($view)->limit(50)->get()->map(fn (Conversation $c) => $this->listRow($c));

        return ['view' => $view, 'counts' => $counts, 'conversations' => $conversations];
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Inbox/Index', $this->inboxList($request) + ['channel' => WhatsAppNumber::first(['display_phone', 'mode'])]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        if (! $user->isManager() && $conversation->assignee_id !== $user->id) {
            abort(404);
        }

        // Opening the conversation clears the unread counter.
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        $conversation->load(['contact.account', 'contact', 'whatsappNumber', 'assignee:id,name']);

        $messages = $conversation->messages()->orderBy('created_at')->get();

        $templates = MessageTemplate::where('whatsapp_number_id', $conversation->whatsapp_number_id)
            ->get(['id', 'name', 'body', 'language', 'category']);

        $contact = $conversation->contact;
        $latestScore = $contact->latestScore();
        $openDeal = $contact->deals()->whereHas('stage', fn ($q) => $q->where('kind', 'open'))->with('stage')->first();
        $summary = $conversation->latestSummary();

        return Inertia::render('Inbox/Show', $this->inboxList($request) + [
            'channel' => $conversation->whatsappNumber->only('display_phone', 'mode'),
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'can_send_freeform' => $conversation->canSendFreeform(),
                'window_closes_at' => $conversation->windowClosesAt()?->toISOString(),
                'assignee' => $conversation->assignee?->only('id', 'name'),
                'channel_mode' => $conversation->whatsappNumber->mode,
                'display_phone' => $conversation->whatsappNumber->display_phone,
                'unreplied' => $conversation->isUnreplied(),
                'waiting_minutes' => $conversation->isUnreplied() && $conversation->last_inbound_at ? (int) $conversation->last_inbound_at->diffInMinutes(now()) : null,
            ],
            'messages' => $messages->values()->map(fn ($m, $i) => [
                'seq' => $i + 1,
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => $m->body,
                'media' => $m->media,
                'status' => $m->status,
                'error' => $m->error,
                'sent_by' => $m->sender?->name,
                'created_at' => $m->created_at->toISOString(),
            ]),
            'templates' => $templates->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'body' => $t->body, 'language' => $t->language,
            ]),
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone_e164' => $contact->phone_e164,
                'email' => $contact->email,
                'score' => $contact->score,
                'account' => $contact->account?->only('id', 'name'),
                'source' => $contact->source,
            ],
            'scoreDetail' => $latestScore ? [
                'score' => $latestScore->score,
                'structured_part' => $latestScore->structured_part,
                'ai_adjustment' => $latestScore->ai_adjustment,
                'reasons' => $latestScore->topReasons(4),
            ] : null,
            'openDeal' => $openDeal ? [
                'id' => $openDeal->id,
                'title' => $openDeal->title,
                'stage' => $openDeal->stage->name,
                'stage_position' => $openDeal->stage->position,
                'days_in_stage' => (int) floor($openDeal->daysInStage()),
                'stale_after_days' => $openDeal->stage->stale_after_days,
                'expected_close' => $openDeal->expected_close?->toDateString(),
                'value' => $openDeal->value,
                'currency' => $openDeal->currency,
            ] : null,
            'summary' => $summary ? [
                'points' => $summary->points,
                'next_step' => $summary->next_step,
                'created_at' => $summary->created_at->toISOString(),
                'model' => $summary->model,
            ] : null,
            'users' => $this->assignees(),
            'roundRobin' => (bool) app('relay.company')->round_robin,
        ]);
    }

    /** @return array<int, array<string, mixed>> teammates with how many open chats each carries */
    private function assignees(): array
    {
        $open = Conversation::query()->where('status', '!=', 'closed')->selectRaw('assignee_id, count(*) as n')->groupBy('assignee_id')->pluck('n', 'assignee_id');

        return array_map(fn ($u) => $u + ['open' => (int) ($open[$u['id']] ?? 0)], $this->users());
    }

    public function attach(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isManager() && $conversation->assignee_id !== $user->id) {
            abort(404);
        }
        $data = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'], 'caption' => ['nullable', 'string', 'max:1024']]);

        $this->sender->sendAttachment($conversation, $request->file('file'), $data['caption'] ?? null, $user->id);

        return back();
    }

    public function retry(Request $request, Conversation $conversation, int $message): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isManager() && $conversation->assignee_id !== $user->id) {
            abort(404);
        }

        $this->sender->retry($conversation->messages()->whereKey($message)->firstOrFail());

        return back()->with('success', 'Message sent.');
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isManager() && $conversation->assignee_id !== $user->id) {
            abort(404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
            'template_id' => ['nullable', 'integer'],
        ]);

        $template = null;
        if (! empty($data['template_id'])) {
            $template = MessageTemplate::where('whatsapp_number_id', $conversation->whatsapp_number_id)
                ->findOrFail($data['template_id']);
        }

        $this->sender->send(
            $conversation,
            $data['body'],
            $user->id,
            $template?->name,
            $template?->language,
        );

        return back();
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $assignee = User::whereKey($data['user_id'])
            ->whereHas('companies', fn ($q) => $q->whereKey($conversation->company_id))
            ->firstOrFail();

        $from = $conversation->assignee?->name;
        $conversation->update(['assignee_id' => $data['user_id']]);

        Activity::record(
            $conversation->company_id,
            $conversation->contact,
            'assignment',
            $request->user()->id,
            [
                'from' => $from,
                'to' => $assignee->name,
                'conversation_id' => $conversation->id,
            ],
        );

        if ($assignee->id !== $request->user()->id) {
            $assignee->notify(new ConversationAssigned($conversation, $request->user()->name));
        }

        return back();
    }

    public function close(Request $request, Conversation $conversation): RedirectResponse
    {
        $conversation->update(['status' => 'closed']);

        return back();
    }

    private function listRow(Conversation $c): array
    {
        $lastMessage = $c->messages()->latest('created_at')->first(['body', 'direction', 'type']);
        $waiting = $c->isUnreplied() && $c->last_inbound_at
            ? $c->last_inbound_at->diffInMinutes(now()) // Carbon 3: signed
            : null;

        return [
            'id' => $c->id,
            'contact_name' => $c->contact->name,
            'contact_score' => $c->contact->score,
            'assignee' => $c->assignee?->name,
            'preview' => $lastMessage->body ?? '[media]',
            'preview_direction' => $lastMessage?->direction,
            'unreplied' => $c->isUnreplied(),
            'waiting_minutes' => $waiting !== null ? (int) $waiting : null,
            'last_message_at' => $c->last_message_at?->toISOString(),
            'unread_count' => $c->unread_count,
            'channel_mode' => $c->whatsappNumber->mode,
        ];
    }

    private function users(): array
    {
        return User::whereHas('companies', fn ($q) => $q->whereKey(app('relay.company')->id))
            ->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
