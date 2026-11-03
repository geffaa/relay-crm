<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * S06 — manager dashboard. Every number here has a matching test
     * asserting it equals a direct query (MILESTONES verifikasi).
     */
    public function index(Request $request): Response
    {
        $company = app('relay.company');
        $period = in_array($request->input('period'), ['week', 'month'], true) ? $request->input('period') : 'quarter';
        $quarterStart = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfQuarter(),
        };

        $ownerId = $request->filled('owner') ? (int) $request->input('owner') : null;
        $byOwner = fn ($q) => $ownerId ? $q->where('owner_id', $ownerId) : $q;

        // Open pipeline value + weighted (fixed probability per stage kind/position).
        $stageRows = Stage::query()
            ->join('pipelines', 'pipelines.id', '=', 'stages.pipeline_id')
            ->where('pipelines.company_id', $company->id)
            ->get(['stages.id', 'stages.name', 'stages.kind', 'stages.position']);

        $cur = $company->currency;
        $toCompany = fn (Deal $d): float => Money::convert((float) $d->value, $d->currency ?? $cur, $cur);

        $openStages = $stageRows->where('kind', 'open');
        $openDeals = $byOwner(Deal::query())
            ->whereIn('stage_id', $openStages->pluck('id'))
            ->get(['id', 'stage_id', 'value', 'currency']);

        $pipelineValue = (float) $openDeals->sum($toCompany);
        $weightedValue = (float) $openDeals->sum(fn (Deal $d) => $toCompany($d) * ($openStages->firstWhere('id', $d->stage_id)?->probability() ?? 0.2));

        // Win rate this quarter: won / (won + lost).
        $wonCount = $byOwner(Deal::query())->where('closed_at', '>=', $quarterStart)
            ->whereHas('stage', fn ($q) => $q->where('kind', 'won'))->count();
        $lostCount = $byOwner(Deal::query())->where('closed_at', '>=', $quarterStart)
            ->whereHas('stage', fn ($q) => $q->where('kind', 'lost'))->count();
        $winRate = ($wonCount + $lostCount) > 0
            ? (int) round(100 * $wonCount / ($wonCount + $lostCount))
            : null;

        // Median first reply (minutes): first customer message to the first team reply after it,
        // one sample per conversation opened this quarter.
        $firstReply = DB::table('conversations as c')
            ->join('messages as m_in', 'm_in.conversation_id', '=', 'c.id')
            ->join('messages as m_out', function ($join) {
                $join->on('m_out.conversation_id', '=', 'c.id')
                    ->where('m_out.direction', 'out')
                    ->whereColumn('m_out.created_at', '>', 'm_in.created_at');
            })
            ->where('m_in.direction', 'in')
            ->where('c.company_id', $company->id)
            ->where('c.created_at', '>=', $quarterStart)
            ->whereRaw('m_in.created_at = (select min(x.created_at) from messages x where x.conversation_id = c.id and x.direction = \'in\')')
            ->groupBy('c.id', 'm_in.created_at')
            ->selectRaw('EXTRACT(EPOCH FROM (MIN(m_out.created_at) - m_in.created_at)) as reply_seconds')
            ->pluck('reply_seconds')
            ->map(fn ($v) => (float) $v)
            ->sort()
            ->values();
        $n = $firstReply->count();
        $medianFirstReply = $n === 0 ? null : (int) round((($n % 2) ? $firstReply[intdiv($n, 2)] : ($firstReply[$n / 2 - 1] + $firstReply[$n / 2]) / 2) / 60);

        // Won value per rep this quarter (no targets in MVP: pace vs last quarter).
        $quotas = DB::table('company_user')->where('company_id', $company->id)->pluck('quarterly_quota', 'user_id');
        $factor = ['week' => 1 / 13, 'month' => 1 / 3, 'quarter' => 1][$period];
        $reps = User::whereHas('companies', fn ($q) => $q->whereKey($company->id))
            ->when($ownerId, fn ($q) => $q->whereKey($ownerId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $user) use ($company, $quarterStart, $toCompany, $quotas, $factor) {
                $won = (float) Deal::forCompany($company)
                    ->where('owner_id', $user->id)
                    ->where('closed_at', '>=', $quarterStart)
                    ->whereHas('stage', fn ($q) => $q->where('kind', 'won'))
                    ->get(['value', 'currency'])
                    ->sum($toCompany);

                $target = isset($quotas[$user->id]) ? (float) $quotas[$user->id] * $factor : null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'won' => $won,
                    'target' => $target,
                    'pct' => $target ? (int) round(100 * $won / $target) : null,
                ];
            })
            ->filter(fn ($r) => $r['won'] > 0 || $r['target'] !== null)
            ->values();

        $totalWon = (float) $reps->sum('won');

        // Needs attention: unreplied chats (hot) + stale deals.
        $unreplied = Conversation::query()
            ->with('contact:id,name,score')
            ->view('unreplied')
            ->orderBy('last_inbound_at')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'kind' => 'unreplied',
                'id' => $c->id,
                'name' => $c->contact->name,
                'detail' => 'waiting '.now()->longAbsoluteDiffForHumans($c->last_inbound_at, 1),
                'score' => $c->contact->score,
                'href' => "/inbox/{$c->id}",
            ]);

        $stale = Deal::stale()
            ->with(['stage:id,name', 'contact:id,name'])
            ->orderBy('stage_entered_at')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'kind' => 'stale',
                'id' => $d->id,
                'name' => $d->title,
                'detail' => 'stuck '.floor($d->daysInStage()).' days · '.$d->stage->name,
                'score' => null,
                'href' => "/deals/{$d->id}",
            ]);

        $attention = $unreplied->concat($stale)->take(5)->values();

        // Pipeline by stage (open only).
        $byStage = $stageRows
            ->filter(fn ($s) => $s->kind === 'open')
            ->sortBy('position')
            ->map(fn ($s) => [
                'name' => $s->name,
                'value' => (float) $openDeals->where('stage_id', $s->id)->sum($toCompany),
                'count' => $openDeals->where('stage_id', $s->id)->count(),
            ])
            ->values();

        // Lost reasons, last 90 days.
        // Lost reasons, last 90 days (typed via pluck; verified against tests).
        $lostBase = $byOwner(Deal::query())->where('closed_at', '>=', now()->subDays(90))
            ->whereHas('stage', fn ($q) => $q->where('kind', 'lost'))
            ->whereNotNull('lost_reason');

        $lostCounts = (clone $lostBase)
            ->selectRaw('lost_reason, count(*) as n')
            ->groupBy('lost_reason')
            ->orderByDesc('n')
            ->pluck('n', 'lost_reason');
        $lostValues = (clone $lostBase)->get(['lost_reason', 'value', 'currency'])
            ->groupBy('lost_reason')
            ->map(fn ($g) => (float) $g->sum($toCompany));

        $lostReasons = $lostCounts
            ->map(fn (int $count, string $reason) => [
                'reason' => $reason,
                'count' => $count,
                'value' => (float) ($lostValues[$reason] ?? 0),
            ])
            ->values();

        $hotLeads = Contact::whereNotNull('score')->where('score', '>=', 70)->count();

        return Inertia::render('Overview', [
            'period' => $period,
            'kpi' => [
                'pipeline_value' => $pipelineValue,
                'weighted_value' => $weightedValue,
                'win_rate' => $winRate,
                'won_count' => $wonCount,
                'lost_count' => $lostCount,
                'median_first_reply_minutes' => $medianFirstReply,
                'won_total' => $totalWon,
                'currency' => $company->currency,
                'hot_leads' => $hotLeads,
                'win_rate_delta' => $this->winRateDelta($ownerId, $quarterStart, $winRate),
                'reply_delta' => $this->replyDelta($company->id, $quarterStart, $medianFirstReply),
            ],
            'reps' => $reps,
            'owners' => User::whereHas('companies', fn ($q) => $q->whereKey($company->id))->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->prepend(['value' => 0, 'label' => 'Team: all reps'])->values(),
            'owner' => $ownerId ?? 0,
            'target' => $this->target($reps, $period, $quarterStart, $totalWon),
            'series' => $this->series($company->id, $ownerId, $cur),
            'attention' => $attention,
            'byStage' => $byStage,
            'lostReasons' => $lostReasons,
        ]);
    }

    /** @param Collection<int, array{id: int, name: string, won: float, target: float|null, pct: int|null}> $reps */
    private function target(Collection $reps, string $period, Carbon $start, float $won): ?array
    {
        $sum = (float) $reps->sum(fn ($r) => $r['target'] ?? 0);
        if ($sum <= 0) {
            return null;
        }
        $end = match ($period) {
            'week' => $start->copy()->endOfWeek(),
            'month' => $start->copy()->endOfMonth(),
            default => $start->copy()->endOfQuarter(),
        };

        return [
            'amount' => $sum,
            'pct' => (int) round(100 * $won / $sum),
            'expected_pct' => (int) round(100 * $start->diffInSeconds(now()) / max(1, $start->diffInSeconds($end))),
            'day' => (int) $start->diffInDays(now()) + 1,
            'days' => (int) $start->diffInDays($end) + 1,
        ];
    }

    /** Win-rate change in points against the window of the same length just before. */
    private function winRateDelta(?int $ownerId, Carbon $start, ?int $current): ?int
    {
        $len = (int) $start->diffInDays(now()) + 1;
        $rate = function ($from, $to) use ($ownerId): ?int {
            $q = fn ($kind) => Deal::query()->when($ownerId, fn ($x) => $x->where('owner_id', $ownerId))
                ->whereBetween('closed_at', [$from, $to])->whereHas('stage', fn ($s) => $s->where('kind', $kind))->count();
            [$w, $l] = [$q('won'), $q('lost')];

            return $w + $l > 0 ? (int) round(100 * $w / ($w + $l)) : null;
        };
        $prev = $rate($start->copy()->subDays($len), $start);

        return $current !== null && $prev !== null ? $current - $prev : null;
    }

    /** @return int|null minutes faster (positive) or slower (negative) than the previous window */
    private function replyDelta(int $companyId, Carbon $start, ?int $current): ?int
    {
        $len = (int) $start->diffInDays(now()) + 1;
        $prev = $this->median($this->firstReplies($companyId, $start->copy()->subDays($len), $start)->pluck('minutes')->all());

        return $current !== null && $prev !== null ? $prev - $current : null;
    }

    /** @return Collection<int, \stdClass> */
    private function firstReplies(int $companyId, mixed $from, mixed $to): Collection
    {
        return DB::table('conversations as c')
            ->join('messages as m_in', 'm_in.conversation_id', '=', 'c.id')
            ->join('messages as m_out', fn ($j) => $j->on('m_out.conversation_id', '=', 'c.id')->where('m_out.direction', 'out')->whereColumn('m_out.created_at', '>', 'm_in.created_at'))
            ->where('m_in.direction', 'in')->where('c.company_id', $companyId)
            ->whereBetween('m_in.created_at', [$from, $to])
            ->whereRaw("m_in.created_at = (select min(x.created_at) from messages x where x.conversation_id = c.id and x.direction = 'in')")
            ->groupBy('c.id', 'm_in.created_at')
            ->selectRaw('m_in.created_at as at, EXTRACT(EPOCH FROM (MIN(m_out.created_at) - m_in.created_at)) / 60 as minutes')
            ->get();
    }

    /** @param array<int, mixed> $values */
    private function median(array $values): ?int
    {
        $v = collect($values)->map(fn ($x) => (float) $x)->sort()->values();
        $n = $v->count();

        return $n === 0 ? null : (int) round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
    }

    /**
     * Eight weekly points for the sparklines. Pipeline value is rebuilt from created and closed dates,
     * so it shows what was open at each week's end rather than an invented trend.
     *
     * @return array<string, array<int, mixed>>
     */
    private function series(int $companyId, ?int $ownerId, string $cur): array
    {
        $deals = Deal::query()->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->with('stage:id,kind')->get(['id', 'stage_id', 'value', 'currency', 'created_at', 'closed_at']);
        $replies = $this->firstReplies($companyId, now()->subWeeks(8), now());
        $out = ['pipeline' => [], 'win_rate' => [], 'reply' => []];

        for ($i = 7; $i >= 0; $i--) {
            $t = now()->subWeeks($i);
            $out['pipeline'][] = (float) $deals->filter(fn (Deal $d) => $d->created_at <= $t && ($d->closed_at === null || $d->closed_at > $t))
                ->sum(fn (Deal $d) => Money::convert((float) $d->value, $d->currency ?? $cur, $cur));
            $window = $deals->filter(fn (Deal $d) => $d->closed_at !== null && $d->closed_at <= $t && $d->closed_at > $t->copy()->subDays(30));
            $w = $window->filter(fn (Deal $d) => $d->stage->kind === 'won')->count();
            $l = $window->filter(fn (Deal $d) => $d->stage->kind === 'lost')->count();
            $out['win_rate'][] = $w + $l > 0 ? (int) round(100 * $w / ($w + $l)) : null;
            $week = $replies->filter(fn ($r) => $r->at > $t->copy()->subWeek()->toDateTimeString() && $r->at <= $t->toDateTimeString());
            $out['reply'][] = $this->median($week->pluck('minutes')->all());
        }

        return $out;
    }
}
