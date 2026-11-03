<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Snooze;
use App\Models\Stage;
use App\Models\Task;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = app('relay.company');
        $cur = $company->currency;
        $team = $user->isManager() && $request->input('scope') === 'team';
        $mine = fn ($q, string $col) => $team ? $q : $q->where($col, $user->id);

        $snoozedChats = Snooze::activeIds($user->id, Conversation::class);
        $snoozedDeals = Snooze::activeIds($user->id, Deal::class);

        $replies = $mine(Conversation::query()->view('unreplied')->whereNotIn('conversations.id', $snoozedChats)->with(['contact:id,name,score,account_id', 'contact.account:id,name', 'assignee:id,name']), 'assignee_id')
            ->orderBy('last_inbound_at')->limit(12)->get()
            ->map(function (Conversation $c) {
                $last = $c->messages()->where('direction', 'in')->latest('created_at')->value('body');

                return [
                    'id' => $c->id, 'name' => $c->contact->name, 'company' => $c->contact->account?->name,
                    'score' => $c->contact->score, 'text' => $last, 'since' => $c->last_inbound_at?->toISOString(),
                    'owner' => $c->assignee?->name, 'href' => "/inbox/{$c->id}",
                ];
            });

        $taskRows = $mine(Task::query()->open()->whereNotIn('kind', ['unreplied', 'stale_deal']), 'assignee_id')
            ->orderByRaw('due_at NULLS LAST')->limit(20)->get();
        $taskDeals = Deal::with(['account:id,name', 'stage:id,name'])->whereIn('id', $taskRows->where('subject_type', Deal::class)->pluck('subject_id'))->get()->keyBy('id');
        $taskContacts = Contact::whereIn('id', $taskRows->where('subject_type', Contact::class)->pluck('subject_id'))->get(['id', 'name'])->keyBy('id');
        $tasks = $taskRows->map(function (Task $t) use ($taskDeals, $taskContacts) {
            $deal = $t->subject_type === Deal::class ? $taskDeals->get($t->subject_id) : null;
            $contact = $t->subject_type === Contact::class ? $taskContacts->get($t->subject_id) : null;

            return [
                'id' => $t->id, 'title' => $t->title, 'kind' => $t->kind, 'due_at' => $t->due_at?->toISOString(),
                'subject' => $deal ? ($deal->account_id ? $deal->account->name : $deal->title) : $contact?->name,
                'deal' => $deal?->title, 'stage' => $deal?->stage->name,
                'href' => $deal ? "/deals/{$deal->id}" : ($contact ? "/contacts/{$contact->id}" : null),
                'deal_id' => $deal?->id,
            ];
        });

        $stuck = $mine(Deal::stale()->whereNotIn('deals.id', $snoozedDeals)->with(['stage:id,name,stale_after_days', 'contact:id,name', 'account:id,name']), 'deals.owner_id')
            ->orderBy('stage_entered_at')->limit(8)->get()
            ->map(fn (Deal $d) => [
                'id' => $d->id, 'name' => $d->account_id ? $d->account->name : ($d->contact_id ? $d->contact->name : $d->title), 'title' => $d->title,
                'stage' => $d->stage->name, 'days' => (int) floor($d->daysInStage()), 'limit' => $d->stage->stale_after_days,
                'value' => $d->value, 'currency' => $d->currency, 'href' => "/deals/{$d->id}",
                'last_touch' => $d->contact_id ? Activity::where('subject_type', Contact::class)->where('subject_id', $d->contact_id)->where('type', 'message')->max('occurred_at') : null,
            ]);

        // Side cards.
        $qStart = now()->startOfQuarter();
        $wonIn = fn ($from, $to) => (float) $mine(Deal::query(), 'owner_id')
            ->whereBetween('closed_at', [$from, $to])->whereHas('stage', fn ($q) => $q->where('kind', 'won'))
            ->get(['value', 'currency'])->sum(fn (Deal $d) => Money::convert((float) $d->value, $d->currency ?? $cur, $cur));
        $prevStart = $qStart->copy()->subQuarter();
        $dayOfQ = (int) $qStart->diffInDays(now()) + 1;
        $prevToDate = $wonIn($prevStart, $prevStart->copy()->addDays($dayOfQ));

        $open = $mine(Deal::query(), 'owner_id')->whereHas('stage', fn ($q) => $q->where('kind', 'open'))->get(['stage_id', 'value', 'currency']);
        $pipeline = Stage::whereIn('id', $open->pluck('stage_id')->unique())->orderBy('position')->get(['id', 'name'])
            ->map(fn ($s) => [
                'name' => $s->name,
                'count' => $open->where('stage_id', $s->id)->count(),
                'value' => (float) $open->where('stage_id', $s->id)->sum(fn (Deal $d) => Money::convert((float) $d->value, $d->currency ?? $cur, $cur)),
            ])->values();

        $doneToday = $mine(Task::query(), 'assignee_id')->where('done_at', '>=', now()->startOfDay())->count();
        $lastDone = $mine(Task::query(), 'assignee_id')->where('done_at', '>=', now()->startOfDay())->latest('done_at')->first(['title', 'done_at']);

        return Inertia::render('Today', [
            'scope' => $team ? 'team' : 'mine',
            'canTeam' => $user->isManager(),
            'replies' => $replies,
            'tasks' => $tasks,
            'stuck' => $stuck,
            'doneToday' => ['count' => $doneToday, 'last' => $lastDone ? ['title' => $lastDone->title, 'at' => $lastDone->done_at->toISOString()] : null],
            'pace' => [
                'won' => $wonIn($qStart, now()), 'previous' => $prevToDate,
                'quota' => $this->quota($user->id, $team),
                'day' => $dayOfQ, 'days' => (int) $qStart->diffInDays($qStart->copy()->endOfQuarter()) + 1,
            ],
            'pipeline' => $pipeline,
            'replyTime' => $this->replyTime($user->id, $team),
            'currency' => $cur,
        ]);
    }

    private function quota(int $userId, bool $team): ?float
    {
        $q = DB::table('company_user')->where('company_id', app('relay.company')->id)->where('is_active', true);
        $sum = $team ? $q->sum('quarterly_quota') : $q->where('user_id', $userId)->sum('quarterly_quota');

        return $sum > 0 ? (float) $sum : null;
    }

    public function snooze(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:conversation,deal'], 'id' => ['required', 'integer'], 'hours' => ['nullable', 'integer', 'min:1', 'max:168']]);
        $class = $data['type'] === 'deal' ? Deal::class : Conversation::class;
        $class::findOrFail($data['id']); // tenant scope: 404 for other companies

        Snooze::create(['user_id' => $request->user()->id, 'subject_type' => $class, 'subject_id' => $data['id'], 'until' => now()->addHours($data['hours'] ?? 4)]);

        return back()->with('success', 'Snoozed. It will come back later.');
    }

    /** @return array{mine: int|null, team: int|null, series: array<int, int>} */
    private function replyTime(int $userId, bool $team): array
    {
        $rows = DB::table('conversations as c')
            ->join('messages as m_in', 'm_in.conversation_id', '=', 'c.id')
            ->join('messages as m_out', fn ($j) => $j->on('m_out.conversation_id', '=', 'c.id')->where('m_out.direction', 'out')->whereColumn('m_out.created_at', '>', 'm_in.created_at'))
            ->where('m_in.direction', 'in')->where('c.company_id', app('relay.company')->id)
            ->where('m_in.created_at', '>=', now()->subDays(14))
            ->whereRaw("m_in.created_at = (select min(x.created_at) from messages x where x.conversation_id = c.id and x.direction = 'in')")
            ->groupBy('c.id', 'c.assignee_id', 'm_in.created_at')
            ->selectRaw('c.assignee_id, m_in.created_at as at, EXTRACT(EPOCH FROM (MIN(m_out.created_at) - m_in.created_at)) / 60 as minutes')
            ->get();

        $median = function ($c): ?int {
            $v = $c->pluck('minutes')->map(fn ($x) => (float) $x)->sort()->values();
            $n = $v->count();

            return $n === 0 ? null : (int) round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
        };
        $mine = $rows->where('assignee_id', $userId);

        return [
            'mine' => $median($team ? $rows : $mine),
            'team' => $median($rows),
            'series' => $rows->sortBy('at')->pluck('minutes')->map(fn ($m) => (int) round((float) $m))->take(-12)->values()->all(),
        ];
    }

    public function done(Request $request, Task $task): RedirectResponse
    {
        abort_unless($request->user()->isManager() || $task->assignee_id === $request->user()->id, 404);
        $task->update(['done_at' => now()]);

        return back();
    }
}
