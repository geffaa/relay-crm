<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Meta WhatsApp Cloud API client. Token is the encrypted number token;
 * endpoint version pinned via config. Network errors throw so the queue can
 * retry with backoff.
 */
class CloudApiClient implements MessagingChannel
{
    public function sendText(WhatsAppNumber $number, string $toE164, string $body): string
    {
        $response = $this->post($number, [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($toE164, '+'),
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        return $response['messages'][0]['id'] ?? 'unknown';
    }

    public function sendTemplate(WhatsAppNumber $number, string $toE164, string $templateName, string $language, array $bodyParams = []): string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($toE164, '+'),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if ($bodyParams !== []) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => collect($bodyParams)
                        ->map(fn ($text) => ['type' => 'text', 'text' => $text])
                        ->values()
                        ->all(),
                ],
            ];
        }

        $response = $this->post($number, $payload);

        return $response['messages'][0]['id'] ?? 'unknown';
    }

    public function isSimulated(): bool
    {
        return false;
    }

    private function post(WhatsAppNumber $number, array $payload): array
    {
        $version = config('services.whatsapp.api_version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$number->phone_number_id}/messages";

        $response = Http::withToken($number->access_token)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::warning('WhatsApp Cloud API send failed', [
                'phone_number_id' => $number->phone_number_id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        }

        return $response->json() ?? [];
    }
}
