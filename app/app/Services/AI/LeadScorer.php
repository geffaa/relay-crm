<?php

namespace App\Services\AI;

use App\Models\Account;
use App\Models\AiRun;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\LeadScore;
use App\Models\Message;
use App\Models\ScoreOverride;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Explainable lead scoring: a deterministic structured part (0–70) from
 * signals, plus a bounded AI adjustment (-15..+30) with reasons. The final
 * score is clamped to 0..100; manager overrides win and are recorded.
 */
class LeadScorer
{
    public const PROMPT_VERSION = 'v1';

    /** Recompute at most once per 10 minutes per contact (PRD F7). */
    private const THROTTLE_KEY = 'relay:score:';

    public function __construct(private readonly AIClient $client) {}

    public function score(Contact $contact, int $companyId, bool $force = false): ?LeadScore
    {
        if (! $force && ! $this->shouldRun($contact)) {
            return $contact->latestScore();
        }

        $signals = $this->structuredSignals($contact);
        $structured = $this->structuredPart($signals);

        [$adjustment, $aiReasons, $inTok, $outTok, $status] = $this->aiPart($contact, $companyId, $signals);

        $score = max(0, min(100, $structured + $adjustment));

        $leadScore = new LeadScore([
            'score' => $score,
            'reasons' => [...$signals['reasons'], ...$aiReasons],
            'structured_part' => $structured,
            'ai_adjustment' => $adjustment,
            'model' => $status === 'ok' ? $this->client->name() : null,
        ]);
        $leadScore->contact_id = $contact->id;
        $leadScore->save();

        // The override (manager correction) wins over any recomputation.
        $override = ScoreOverride::where('contact_id', $contact->id)->latest('id')->first();
        $final = $override ? $override->to : $score;

        $contact->update(['score' => $final, 'score_updated_at' => now()]);

        if ($status === 'ok') {
            AiRun::forceCreate([
                'company_id' => $companyId,
                'kind' => 'score',
                'model' => $this->client->name(),
                'input_tokens' => $inTok,
                'output_tokens' => $outTok,
                'status' => 'ok',
            ]);
        }

        Cache::put(self::THROTTLE_KEY.$contact->id, now(), now()->addMinutes(10));

        return $leadScore;
    }

    /**
     * Manager correction: stored, audited, and displayed with the score.
     */
    public function override(Contact $contact, int $userId, int $to, string $reason): void
    {
        $from = $contact->score;

        ScoreOverride::create([
            'contact_id' => $contact->id,
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);

        $contact->update(['score' => $to, 'score_updated_at' => now()]);
    }

    private function shouldRun(Contact $contact): bool
    {
        if ($contact->score_updated_at === null) {
            return true;
        }

        // New inbound message since the last scoring forces a refresh.
        $newMessages = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->where('direction', 'in')
            ->where('created_at', '>', $contact->score_updated_at)
            ->exists();

        if ($newMessages) {
            return true;
        }

        return Cache::get(self::THROTTLE_KEY.$contact->id) === null;
    }

    private function structuredSignals(Contact $contact): array
    {
        $reasons = [];
        $points = 0;

        // Source quality (max 10).
        $sourcePoints = match ($contact->source) {
            'whatsapp' => 10, 'referral' => 8, 'web' => 5, default => 2,
        };
        $points += $sourcePoints;
        $reasons[] = ['label' => 'Source: '.ucfirst($contact->source), 'signal' => 'source', 'weight' => $sourcePoints];

        // Best open deal value (max 20).
        $bestDeal = $contact->deals()->whereHas('stage', fn ($q) => $q->where('kind', 'open'))
            ->orderByDesc('value')->first();
        if ($bestDeal?->value) {
            $dealPoints = min(20, (int) floor(((float) $bestDeal->value) / 2500));
            $points += $dealPoints;
            $reasons[] = [
                'label' => sprintf('Open deal %s (USD %s)', $bestDeal->title, number_format((float) $bestDeal->value)),
                'signal' => 'deal_value',
                'weight' => $dealPoints,
            ];
        }

        // Account size band (max 10).
        $account = $contact->account;
        if ($account?->size_band) {
            $bandPoints = match ($account->size_band) {
                '51-200', '200+' => 10, '11-50' => 6, default => 3,
            };
            $points += $bandPoints;
            $reasons[] = ['label' => "Account size {$account->size_band}", 'signal' => 'account_size', 'weight' => $bandPoints];
        }

        // Responsiveness: inbound message count (max 10).
        $inbound = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->where('direction', 'in')->count();
        if ($inbound >= 3) {
            $points += 10;
            $reasons[] = ['label' => "{$inbound} inbound messages", 'signal' => 'engagement', 'weight' => 10];
        } elseif ($inbound > 0) {
            $points += $inbound * 3;
            $reasons[] = ['label' => "{$inbound} inbound message(s)", 'signal' => 'engagement', 'weight' => $inbound * 3];
        }

        // Recency: last inbound within 7 days (max 10).
        $lastInbound = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->where('direction', 'in')->latest('created_at')->first()?->created_at;
        if ($lastInbound?->gt(now()->subDays(7))) {
            $points += 10;
            $reasons[] = ['label' => 'Active in the last 7 days', 'signal' => 'recency', 'weight' => 10];
        }

        // Email present (max 10) — often correlates with B2B seriousness.
        if ($contact->email) {
            $points += 10;
            $reasons[] = ['label' => 'Shared their email', 'signal' => 'email', 'weight' => 10];
        }

        return [
            'points' => min(70, $points),
            'reasons' => $reasons,
        ];
    }

    private function structuredPart(array $signals): int
    {
        return $signals['points'];
    }

    /**
     * AI reads recent conversation and may adjust -15..+30 with reasons.
     * Never fails the scoring: on error returns adjustment 0.
     */
    private function aiPart(Contact $contact, int $companyId, array $signals): array
    {
        $transcript = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['direction', 'body'])
            ->reverse()
            ->map(fn ($m) => ($m->direction === 'in' ? 'customer' : 'agent').': '.str($m->body ?? '[attachment]')->limit(200))
            ->implode("\n");

        if (blank($transcript)) {
            return [0, [], 0, 0, 'no-transcript'];
        }

        $system = <<<'TXT'
            You are a lead scorer for a sales agency. Read the transcript and judge buying intent.
            Return ONLY JSON: {"adjustment":-15..30,"reasons":[{"label":"...","signal":"..."}]}
            - adjustment: 0 when unclear; positive for budget/deadline/purchase intent; negative for explicit no-budget.
            - reasons: 1-3 short, concrete, human-readable labels citing what was said.
            TXT;

        $user = "Current structured score: {$signals['points']}/70.\n\nTranscript:\n{$transcript}";

        try {
            [$text, $inTok, $outTok] = $this->client->complete($system, $user);
        } catch (Throwable) {
            return [0, [], 0, 0, 'error'];
        }

        $json = null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
        }

        if (! is_array($json)) {
            return [0, [], $inTok, $outTok, 'error'];
        }

        $adjustment = max(-15, min(30, (int) ($json['adjustment'] ?? 0)));
        $reasons = collect($json['reasons'] ?? [])
            ->filter(fn ($r) => filled($r['label'] ?? null))
            ->take(3)
            ->map(fn ($r) => ['label' => $r['label'], 'signal' => $r['signal'] ?? 'ai', 'weight' => $adjustment])
            ->values()
            ->all();

        return [$adjustment, $reasons, $inTok, $outTok, 'ok'];
    }
}
