<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNumber;

/**
 * Contract for sending WhatsApp messages. Implemented by the real Meta
 * Cloud API client and by the simulated client used for the public demo
 * (assumption A4: no verified Meta business number for demos).
 */
interface MessagingChannel
{
    /**
     * Send a free-form text message. Returns the provider message id.
     */
    public function sendText(WhatsAppNumber $number, string $toE164, string $body): string;

    /**
     * Send an approved template with named/body params.
     */
    public function sendTemplate(WhatsAppNumber $number, string $toE164, string $templateName, string $language, array $bodyParams = []): string;

    /**
     * Mark a locally queued message as delivered (simulation only).
     */
    public function isSimulated(): bool;
}
