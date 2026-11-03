<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppEvent;
use App\Models\WhatsAppNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook verification (GET): echo hub.challenge when the verify
     * token matches.
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Inbound events (POST). Signature verified against the app secret
     * (X-Hub-Signature-256, HMAC-SHA256 of the raw body). Payloads are
     * queued raw; processing is idempotent per wamid.
     */
    public function receive(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256', '');
        $appSecret = config('services.whatsapp.app_secret');

        if ($appSecret && ! $this->signatureValid($request->getContent(), $signature, $appSecret)) {
            Log::warning('WhatsApp webhook signature mismatch');

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();

        $phoneNumbers = collect($payload['entry'] ?? [])
            ->flatMap(fn ($entry) => collect($entry['changes'] ?? [])
                ->pluck('value.metadata.phone_number_id'))
            ->filter()
            ->unique();

        $companies = WhatsAppNumber::whereIn('phone_number_id', $phoneNumbers)
            ->get()
            ->unique('company_id');

        if ($companies->isEmpty()) {
            // Unknown number: accept (200) but drop, as Meta retries otherwise.
            Log::info('WhatsApp webhook for unknown phone_number_id dropped');

            return response('EVENT_RECEIVED', 200);
        }

        foreach ($companies as $number) {
            ProcessWhatsAppEvent::dispatch($number->company_id, $payload);
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function signatureValid(string $rawBody, string $signature, string $appSecret): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expected, $signature);
    }
}
