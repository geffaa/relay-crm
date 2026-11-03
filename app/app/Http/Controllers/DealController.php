<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Mentioned;
use App\Services\DealService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(private readonly DealService $deals) {}

    /**
     * S01 — pipeline board (kanban) for one pipeline.
     */
    public function pipeline(Request $request): Response
    {
        $pipelines = Pipeline::orderBy('name')->get(['id', 'name', 'is_default']);
        $pipeline = $request->filled('pipeline')
            ? Pipeline::findOrFail($request->input('pipeline'))
            : ($pipelines->firstWhere('is_default') ?? $pipelines->first());

        if (! $pipeline instanceof Pipeline) {
            abort(404);
        }

        $onlyStale = $request->boolean('stuck');
        $hot = $request->boolean('hot');
        $closing = $request->boolean('closing');
        $mineOnly = $request->boolean('mine');
        $ownerId = $mineOnly ? $request->user()->id : ($request->filled('owner') ? (int) $request->input('owner') : null);
        $currency = app('relay.company')->currency;

        $unreplied = Conversation::view('unreplied')->get(['contact_id', 'last_inbound_at'])->pluck('last_inbound_at', 'contact_id');

        $stages = $pipeline->stages()->get()->map(function (Stage $stage) use ($onlyStale, $ownerId, $currency, $hot, $closing, $unreplied) {
            $all = $stage->deals()
                ->with(['contact:id,name', 'account:id,name', 'owner:id,name'])
                ->when($onlyStale, fn ($q) => $q->stale())
                ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
                ->when($hot, fn ($q) => $q->whereHas('contact', fn ($c) => $c->where('score', '>=', 70)))
                ->when($closing, fn ($q) => $q->whereBetween('expected_close', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]))
                ->orderBy('stage_entered_at')
                ->get();
            $deals = $all->map(fn (Deal $deal) => $this->boardCard($deal) + ['unreplied_minutes' => isset($unreplied[$deal->contact_id]) ? (int) $unreplied[$deal->contact_id]->diffInMinutes(now()) : null]);
            $value = (float) $all->sum(fn (Deal $d) => Money::convert((float) $d->value, $d->currency ?? $currency, $currency));

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'kind' => $stage->kind,
                'position' => $stage->position,
                'stale_after_days' => $stage->stale_after_days,
                'deals' => $deals,
                'count' => $deals->count(),
                'value' => $value,
                'weighted' => $value * $stage->probability(),
                'probability' => $stage->probability(),
            ];
        });

        return Inertia::render('Pipeline', [
            'pipelines' => $pipelines,
            'pipeline' => $pipeline->only('id', 'name', 'is_default'),
            'stages' => $stages,
            'views' => $this->views($pipeline, $request),
            'filters' => ['stuck' => $onlyStale, 'hot' => $hot, 'closing' => $closing, 'mine' => $mineOnly, 'owner' => $ownerId, 'view' => in_array($request->input('view'), ['table', 'forecast'], true) ? $request->input('view') : 'board'],
            'owners' => $this->users(),
            'contacts' => Contact::orderBy('name')->limit(300)->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'currency' => $currency,
            'newContact' => $request->filled('new') ? (int) $request->input('new') : null,
        ]);
    }

    /** @return array<string, int> counts for the saved views in the context panel */
    private function views(Pipeline $pipeline, Request $request): array
    {
        $open = fn () => Deal::query()->where('pipeline_id', $pipeline->id)->whereHas('stage', fn ($q) => $q->where('kind', 'open'));

        return [
            'all' => Deal::where('pipeline_id', $pipeline->id)->count(),
            'mine' => $open()->where('owner_id', $request->user()->id)->count(),
            'stuck' => Deal::stale()->where('deals.pipeline_id', $pipeline->id)->count(),
            'hot' => $open()->whereHas('contact', fn ($c) => $c->where('score', '>=', 70))->count(),
            'closing' => $open()->whereBetween('expected_close', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
        ];
    }

    public function show(Request $request, Deal $deal): Response
    {
        // Reps may only open their own deals; managers and admins see all.
        if (! $request->user()->isManager() && $deal->owner_id !== $request->user()->id) {
            abort(404);
        }

        $deal->load(['stage', 'pipeline', 'contact.account', 'account', 'owner:id,name']);

        $timeline = $this->timeline($deal, null);

        $openTasks = $deal->tasks()->open()->whereNotIn('kind', ['unreplied'])->orderByRaw('due_at NULLS LAST')->get();
        $doneTasks = $deal->tasks()->whereNotNull('done_at')->latest('done_at')->limit(2)->get();
        $latestScore = $deal->contact?->latestScore();
        $lastContact = $deal->contact_id ? Activity::where('subject_type', Contact::class)->where('subject_id', $deal->contact_id)->where('type', 'message')->max('occurred_at') : null;
        $conversationId = $deal->contact_id ? Conversation::where('contact_id', $deal->contact_id)->latest('last_message_at')->value('id') : null;

        return Inertia::render('Deals/Show', [
            'deal' => [
                'id' => $deal->id,
                'title' => $deal->title,
                'value' => $deal->value,
                'currency' => $deal->currency,
                'expected_close' => $deal->expected_close?->toDateString(),
                'stage' => ['id' => $deal->stage->id, 'name' => $deal->stage->name, 'kind' => $deal->stage->kind],
                'pipeline' => ['id' => $deal->pipeline->id, 'name' => $deal->pipeline->name],
                'owner' => $deal->owner?->only('id', 'name'),
                'contact' => $deal->contact ? [
                    'id' => $deal->contact->id,
                    'name' => $deal->contact->name,
                    'phone_e164' => $deal->contact->phone_e164,
                    'account' => $deal->contact->account?->only('id', 'name'),
                    'score' => $deal->contact->score,
                ] : null,
                'account' => $deal->account?->only('id', 'name'),
                'lost_reason' => $deal->lost_reason,
                'closed_at' => $deal->closed_at?->toISOString(),
                'days_in_stage' => floor($deal->daysInStage()),
                'stale' => $deal->isStale(),
                'stale_after_days' => $deal->stage->stale_after_days,
                'stage_entered_at' => $deal->stage_entered_at->toISOString(),
            ],
            'stages' => $deal->pipeline->stages()->get(['id', 'name', 'kind', 'position']),
            'stageDays' => $this->stageDays($deal),
            'probability' => $deal->stage->probability(),
            'lastContact' => $lastContact ? Carbon::parse($lastContact)->toISOString() : null,
            'conversationId' => $conversationId,
            'created_at' => $deal->created_at->toISOString(),
            'scoreDetail' => $latestScore ? ['score' => $latestScore->score, 'reasons' => $latestScore->topReasons(4)] : null,
            'doneTasks' => $doneTasks->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'done_at' => $t->done_at?->toISOString()]),
            'activities' => $timeline['items'],
            'timeline_next' => $timeline['next'],
            'tasks' => $openTasks->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title, 'kind' => $t->kind, 'due_at' => $t->due_at?->toISOString(), 'assignee' => $t->assignee?->name,
            ]),
            'users' => $this->users(),
        ]);
    }

    /** @return array<string, int> whole days the deal has spent in each stage it has visited */
    private function stageDays(Deal $deal): array
    {
        $changes = $deal->activities()->where('type', 'stage_change')->orderBy('occurred_at')->orderBy('id')->get();
        $days = [];
        $cursor = $deal->created_at;
        $current = $changes->first()?->body['from'] ?? $deal->stage->name;

        foreach ($changes as $c) {
            $days[$current] = ($days[$current] ?? 0) + max(0, (int) floor($cursor->diffInHours($c->occurred_at) / 24));
            $cursor = $c->occurred_at;
            $current = $c->body['to'] ?? $current;
        }
        $days[$current] = ($days[$current] ?? 0) + max(0, (int) floor($cursor->diffInHours(now()) / 24));

        return $days;
    }

    /** Older timeline entries for the deal and its contact, newest first, keyed by an occurred_at cursor. */
    public function timelinePage(Request $request, Deal $deal): JsonResponse
    {
        if (! $request->user()->isManager() && $deal->owner_id !== $request->user()->id) {
            abort(404);
        }
        $before = $request->validate(['before' => ['required', 'date']])['before'];

        return response()->json($this->timeline($deal, $before));
    }

    /** @return array{items: array<int, array<string, mixed>>, next: string|null} */
    private function timeline(Deal $deal, ?string $before): array
    {
        $limit = 30;
        $rows = Activity::query()
            ->where(function ($q) use ($deal) {
                $q->where(fn ($w) => $w->where('subject_type', Deal::class)->where('subject_id', $deal->id));
                if ($deal->contact_id) {
                    $q->orWhere(fn ($w) => $w->where('subject_type', Contact::class)->where('subject_id', $deal->contact_id));
                }
            })
            ->when($before, fn ($q) => $q->where('occurred_at', '<', $before))
            ->with('actor:id,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return [
            'items' => $rows->map(fn (Activity $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'body' => $a->body,
                'actor' => $a->actor?->name,
                'on' => $a->subject_type === Deal::class ? 'deal' : 'contact',
                'occurred_at' => $a->occurred_at->toISOString(),
            ])->values()->all(),
            'next' => $more ? $rows->last()?->occurred_at->toISOString() : null,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'contact_id' => ['required', 'integer'],
            'pipeline_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close' => ['nullable', 'date'],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        $pipeline = ! empty($data['pipeline_id'])
            ? Pipeline::findOrFail($data['pipeline_id'])
            : app('relay.company')->defaultPipeline();

        $stage = ! empty($data['stage_id'])
            ? Stage::findOrFail($data['stage_id'])
            : $pipeline->stages()->orderBy('position')->first();

        $deal = Deal::create([
            'title' => $data['title'],
            'contact_id' => $contact->id,
            'account_id' => $contact->account_id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'owner_id' => $request->user()->id,
            'value' => $data['value'] ?? null,
            'currency' => $data['currency'] ?? app('relay.company')->currency,
            'expected_close' => $data['expected_close'] ?? null,
            'stage_entered_at' => now(),
        ]);

        Activity::record($deal->company_id, $deal, 'note', $request->user()->id, ['event' => 'deal_created']);

        return redirect()->route('deals.show', $deal);
    }

    /**
     * PATCH /deals/{id}/stage — Rep (owner) or Manager.
     */
    public function changeStage(Request $request, Deal $deal): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isManager() && $deal->owner_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'stage_id' => ['required', 'integer'],
            'lost_reason' => ['nullable', 'string', 'max:120'],
        ]);

        $stage = Stage::whereHas('pipeline', fn ($q) => $q->where('company_id', app('relay.company')->id))
            ->findOrFail($data['stage_id']);

        $this->deals->changeStage($deal, $stage, $data['lost_reason'] ?? null, $user->id);

        return back();
    }

    public function addNote(Request $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        Activity::record($deal->company_id, $deal, 'note', $request->user()->id, ['text' => $validated['body']]);

        $this->notifyMentions($deal, $validated['body']);

        return back();
    }

    public function addTask(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'assignee_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
        ]);

        Task::create([
            'company_id' => $deal->company_id,
            'assignee_id' => $data['assignee_id'] ?? $request->user()->id,
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
            'title' => $data['title'],
            'kind' => 'manual',
            'due_at' => $data['due_at'] ?? null,
        ]);

        Activity::record($deal->company_id, $deal, 'task', $request->user()->id, ['event' => 'task_created', 'title' => $data['title']]);

        return back();
    }

    /**
     * @mention notification: in-app notification for mentioned team members.
     */
    private function notifyMentions(Deal $deal, string $body): void
    {
        preg_match_all('/@([\w.]+)/', $body, $matches);

        if (empty($matches[1])) {
            return;
        }

        $users = User::whereHas('companies', fn ($q) => $q->whereKey($deal->company_id))
            ->get()
            ->filter(fn (User $u) => in_array(
                strtolower(str_replace(' ', '.', $u->name)),
                array_map('strtolower', $matches[1]),
            ) || in_array(strtolower(explode(' ', $u->name)[0]), array_map('strtolower', $matches[1]), true));

        foreach ($users as $user) {
            $user->notify(new Mentioned($deal));
        }
    }

    private function boardCard(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => (float) $deal->value,
            'currency' => $deal->currency,
            'account' => $deal->account_id !== null ? $deal->account->name : $deal->contact->name,
            'owner' => $deal->owner?->name,
            'score' => $deal->contact?->score,
            'days_in_stage' => floor($deal->daysInStage()),
            'stale' => $deal->isStale(),
            'stale_after_days' => $deal->stage->stale_after_days,
        ];
    }

    private function users(): array
    {
        return User::whereHas('companies', fn ($q) => $q->whereKey(app('relay.company')->id))
            ->orderBy('name')->get(['id', 'name'])->toArray();
    }
}
