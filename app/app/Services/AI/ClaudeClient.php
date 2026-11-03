<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude Messages API client. Throws on transport/API errors so
 * callers can degrade gracefully (AI never blocks the inbox).
 */
class ClaudeClient implements AIClient
{
    public function name(): string
    {
        return (string) config('services.anthropic.model', 'claude-sonnet-4-5');
    }

    public function complete(string $system, string $user): array
    {
        $response = Http::withHeaders(['x-api-key' => (string) config('services.anthropic.key')])
            ->acceptJson()
            ->withHeaders(['anthropic-version' => '2023-06-01'])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-sonnet-4-5'),
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Claude API error', ['status' => $response->status(), 'body' => $response->json()]);
            $response->throw();
        }

        $json = $response->json();

        return [
            $json['content'][0]['text'] ?? '',
            (int) ($json['usage']['input_tokens'] ?? 0),
            (int) ($json['usage']['output_tokens'] ?? 0),
        ];
    }
}
