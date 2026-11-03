<?php

namespace App\Services\AI;

use App\Models\AiRun;
use App\Models\Conversation;
use Throwable;

/**
 * Proposes a reply draft. The draft is returned as text only — nothing is
 * ever sent without a human clicking Send (PRD A5).
 */
class ReplyDrafter
{
    public const PROMPT_VERSION = 'v1';

    private const MAX_MESSAGES = 30;

    public function __construct(private readonly AIClient $client) {}

    /**
     * @return array{draft: ?string, error: ?string}
     */
    public function draft(Conversation $conversation, int $companyId): array
    {
        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(self::MAX_MESSAGES)
            ->get(['id', 'direction', 'body'])
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return ['draft' => null, 'error' => 'Nothing to draft from yet.'];
        }

        $transcript = $messages
            ->map(fn ($m) => sprintf('%s: %s', $m->direction === 'in' ? 'customer' : 'agent', str($m->body ?? '[attachment]')->limit(240)))
            ->implode("\n");

        $system = <<<'TXT'
            You are a reply drafter for a sales agency rep on WhatsApp.
            Write ONE short ready-to-send reply (max 90 words, warm, concrete).
            Match the language of the customer (Indonesian or English).
            Return ONLY JSON: {"draft":"..."}
            TXT;

        $user = "Conversation:\n\n{$transcript}";

        try {
            [$text, $inTok, $outTok] = $this->client->complete($system, $user);
        } catch (Throwable) {
            $this->logRun($companyId, 'error', 0, 0);

            return ['draft' => null, 'error' => 'AI is unavailable right now — the inbox still works.'];
        }

        $json = null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
        }

        $draft = is_array($json) ? ($json['draft'] ?? null) : null;

        if (! is_string($draft) || blank($draft)) {
            $this->logRun($companyId, 'error', $inTok, $outTok);

            return ['draft' => null, 'error' => 'The model returned no usable draft.'];
        }

        $this->logRun($companyId, 'ok', $inTok, $outTok);

        // Draft only. Sending stays a manual action.
        return ['draft' => $draft, 'error' => null];
    }

    private function logRun(int $companyId, string $status, int $in, int $out): void
    {
        AiRun::forceCreate([
            'company_id' => $companyId,
            'kind' => 'draft_reply',
            'model' => $this->client->name(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'status' => $status,
        ]);
    }
}
