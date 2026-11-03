<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNumber;

/**
 * Demo-mode client: no network calls. Message ids are generated locally and
 * deliveries are optimistic. The ingest side of simulation is handled by the
 * /dev/simulate-inbound endpoint, which produces Meta-shaped payloads and
 * runs them through the very same ProcessWhatsAppEvent job as live traffic.
 */
class SimulatedClient implements MessagingChannel
{
    public function sendText(WhatsAppNumber $number, string $toE164, string $body): string
    {
        return 'sim.'.bin2hex(random_bytes(8));
    }

    public function sendTemplate(WhatsAppNumber $number, string $toE164, string $templateName, string $language, array $bodyParams = []): string
    {
        return 'sim.'.bin2hex(random_bytes(8));
    }

    public function isSimulated(): bool
    {
        return true;
    }
}
