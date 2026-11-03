<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConversationAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly Conversation $conversation, public readonly string $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'assignment',
            'title' => "{$this->by} assigned you a chat with {$this->conversation->contact->name}",
            'href' => "/inbox/{$this->conversation->id}",
        ];
    }
}
