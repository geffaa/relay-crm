<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
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

        // Open pipeline value + weighted (fixed probability per stage kind/position).
        $stageRows = Stage::query()
            ->join('pipelines', 'pipelines.id', '=', 'stages.pipeline_id')
            ->where('pipelines.company_id', $company->id)
            ->get(['stages.id', 'stages.name', 'stages.kind', 'stages.position']);

        $cur = $company->currency;
        $toCompany = fn (Deal $d): float => Money::convert((float) $d->value, $d->currency ?? $cur, $cur);

        $openStages = $stageRows->where('kind', 'open');
        $openDeals = Deal::query()
            ->whereIn('stage_id', $openStages->pluck('id'))
            ->get(['id', 'stage_id', 'value', 'currency']);

        $pipelineValue = (float) $openDeals->sum($toCompany);
        $weightedValue = (float) $openDeals->sum(fn (Deal $d) => $toCompany($d) * ($openStages->firstWhere('id', $d->stage_id)?->probability() ?? 0.2));

        // Win rate this quarter: won / (won + lost).
        $wonCount = Deal::where('closed_at', '>=', $quarterStart)
            ->whereHas('stage', fn ($q) => $q->where('kind', 'won'))->count();
        $lostCount = Deal::where('closed_at', '>=', $quarterStart)
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
        $reps = User::whereHas('companies', fn ($q) => $q->whereKey($company->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $user) use ($company, $quarterStart, $toCompany) {
                $won = (float) Deal::forCompany($company)
                    ->where('owner_id', $user->id)
                    ->where('closed_at', '>=', $quarterStart)
                    ->whereHas('stage', fn ($q) => $q->where('kind', 'won'))
                    ->get(['value', 'currency'])
                    ->sum($toCompany);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'won' => $won,
                ];
            })
            ->filter(fn ($r) => $r['won'] > 0)
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

        $attention = $unreplied->concat($stale)->take(8)->values();

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
        $lostBase = Deal::where('closed_at', '>=', now()->subDays(90))
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
            ],
            'reps' => $reps,
            'attention' => $attention,
            'byStage' => $byStage,
            'lostReasons' => $lostReasons,
        ]);
    }
}
