<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Groq's OpenAI-compatible chat completions API. Throws on failure so callers degrade gracefully. */
class GroqClient implements AIClient
{
    public const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function name(): string
    {
        return (string) config('services.groq.model', 'openai/gpt-oss-120b');
    }

    public function complete(string $system, string $user): array
    {
        $response = Http::withToken((string) config('services.groq.key'))->acceptJson()->timeout(45)->retry(2, 500, throw: false)
            ->post(self::ENDPOINT, [
                'model' => $this->name(),
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                'max_completion_tokens' => 1500,
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            Log::warning('Groq API error', ['status' => $response->status()]);
            $response->throw();
        }

        return [
            trim((string) $response->json('choices.0.message.content', '')),
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
        ];
    }
}
