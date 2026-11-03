<?php

namespace App\Services\AI;

use App\Models\Activity;
use App\Models\AiRun;
use App\Models\AiSummary;
use App\Models\Conversation;
use App\Models\Message;
use Throwable;

/**
 * Summarizes a conversation into sourced points: every point MUST cite the
 * ids of the messages it is based on; points without valid ids are dropped.
 */
class ConversationSummarizer
{
    public const PROMPT_VERSION = 'v1';

    /** Keep the transcript bounded. */
    private const MAX_MESSAGES = 80;

    public function __construct(private readonly AIClient $client) {}

    public function summarize(Conversation $conversation, int $companyId): ?AiSummary
    {
        $lastCovered = $conversation->latestSummary()?->covers_until_message_id;

        $messages = $conversation->messages()
            ->when($lastCovered, fn ($q) => $q->where('id', '>', $lastCovered))
            ->orderBy('created_at')
            ->limit(self::MAX_MESSAGES)
            ->get(['id', 'direction', 'body', 'type', 'created_at']);

        if ($messages->count() < 3) {
            return null; // Too little to summarize honestly.
        }

        $transcript = $messages
            ->map(fn (Message $m) => sprintf(
                '[m%d] %s (%s, %s): %s',
                $m->id,
                $m->created_at->format('M j H:i'),
                $m->direction === 'in' ? 'customer' : 'agent',
                $m->type,
                str($m->body ?? '[attachment]')->limit(300),
            ))
            ->implode("\n");

        $system = <<<'TXT'
            You are a CRM conversation summarizer. Summarize the WhatsApp transcript.
            Rules:
            - Return ONLY JSON: {"points":[{"text":"...","message_ids":[123]}],"next_step":"...","confidence":0.0}
            - Every point MUST cite the numeric message ids ([mNNN]) it is grounded in.
            - Never invent facts that are not in the transcript.
            - 2 to 5 points, each one sentence.
            - next_step: the single most useful next action for the sales rep, or "" if unclear.
            - confidence: 0..1; low when the transcript is short or ambiguous.
            TXT;

        $user = "Transcript (customer messages are the leads):\n\n{$transcript}";

        try {
            [$text, $inTok, $outTok] = $this->client->complete($system, $user);
        } catch (Throwable) {
            $this->logRun($companyId, 'summarize', 'error', 0, 0);

            return null; // AI failure must not block the inbox.
        }

        $parsed = $this->parse($text, $messages->pluck('id')->all());

        if ($parsed === null) {
            $this->logRun($companyId, 'summarize', 'error', $inTok, $outTok);

            return null;
        }

        $summary = new AiSummary([
            'points' => $parsed['points'],
            'next_step' => $parsed['next_step'] ?? null,
            'confidence' => $parsed['confidence'] ?? null,
            'model' => $this->client->name(),
            'prompt_version' => self::PROMPT_VERSION,
            'covers_until_message_id' => $messages->last()->id,
        ]);
        $summary->conversation_id = $conversation->id;
        $summary->save();

        Activity::record($companyId, $conversation->contact, 'ai_summary', null, [
            'conversation_id' => $conversation->id,
            'summary_id' => $summary->id,
            'points' => count($parsed['points']),
        ]);

        $this->logRun($companyId, 'summarize', 'ok', $inTok, $outTok);

        return $summary;
    }

    /**
     * Validates the model output: drops points whose message_ids do not all
     * reference real messages of this conversation (anti-hallucination).
     */
    private function parse(string $text, array $validIds): ?array
    {
        $json = $this->extractJson($text);

        if ($json === null || ! isset($json['points']) || ! is_array($json['points'])) {
            return null;
        }

        $valid = array_fill_keys(array_map('intval', $validIds), true);
        $points = [];

        foreach ($json['points'] as $point) {
            $ids = collect($point['message_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => isset($valid[$id]))
                ->unique()
                ->values()
                ->all();

            if (blank($point['text'] ?? null) || $ids === []) {
                continue; // Unsourced point: rejected.
            }

            $points[] = ['text' => $point['text'], 'message_ids' => $ids];
        }

        if ($points === []) {
            return null;
        }

        return [
            'points' => $points,
            'next_step' => $json['next_step'] ?? null,
            'confidence' => isset($json['confidence']) ? (float) $json['confidence'] : null,
        ];
    }

    private function extractJson(string $text): ?array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function logRun(int $companyId, string $kind, string $status, int $in, int $out): void
    {
        AiRun::forceCreate([
            'company_id' => $companyId,
            'kind' => $kind,
            'model' => $this->client->name(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'status' => $status,
        ]);
    }
}
