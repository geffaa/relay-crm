<?php

namespace App\Notifications;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class Mentioned extends Notification
{
    use Queueable;

    public function __construct(public readonly Deal $deal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'deal_id' => $this->deal->id,
            'title' => "You were mentioned on {$this->deal->title}",
            'href' => "/deals/{$this->deal->id}",
        ];
    }
}
