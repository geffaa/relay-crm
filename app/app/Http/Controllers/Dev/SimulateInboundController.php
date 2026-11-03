<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppEvent;
use App\Models\WhatsAppNumber;
use Illuminate\Http\Request;

/**
 * Demo-only endpoint: fabricates a Meta-shaped inbound message and runs it
 * through the real ProcessWhatsAppEvent job. Admin-only; the honest way to
 * demo the ingest pipeline without a verified Meta number (assumption A4).
 */
class SimulateInboundController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
            'text' => ['nullable', 'string', 'max:2000'],
            'whatsapp_number_id' => ['nullable', 'integer'],
        ]);

        $number = ! empty($data['whatsapp_number_id'])
            ? WhatsAppNumber::findOrFail($data['whatsapp_number_id'])
            : WhatsAppNumber::query()->firstOrFail();

        $from = preg_replace('/^\+/', '', $data['from']);
        $wamid = 'sim.'.bin2hex(random_bytes(12));

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => $number->waba_id ?? '0',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => preg_replace('/^\+/', '', $number->display_phone),
                                    'phone_number_id' => $number->phone_number_id,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => $data['name'] ?? 'Simulated lead'],
                                        'wa_id' => $from,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'id' => $wamid,
                                        'timestamp' => (string) now()->getTimestamp(),
                                        'text' => ['body' => $data['text'] ?? 'Hi, I saw your portfolio and want a quote.'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        ProcessWhatsAppEvent::dispatchSync($number->company_id, $payload);

        return response()->json(['ok' => true, 'wamid' => $wamid]);
    }
}
