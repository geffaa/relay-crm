<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppNumber;

/**
 * Picks the channel per number: live numbers go through the Meta Cloud API,
 * simulated numbers (demo) stay local.
 */
class MessagingChannelManager
{
    public function for(WhatsAppNumber $number): MessagingChannel
    {
        return $number->isLive()
            ? app(CloudApiClient::class)
            : app(SimulatedClient::class);
    }
}
