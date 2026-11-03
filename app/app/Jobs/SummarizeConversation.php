<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AI\ConversationSummarizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PRD F6: auto-summarize when a conversation passes 30 messages since the
 * last summary. Queued so the ingest path stays fast.
 */
class SummarizeConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $conversationId, public readonly int $companyId) {}

    public function handle(ConversationSummarizer $summarizer): void
    {
        $conversation = Conversation::find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $lastCovered = $conversation->latestSummary()?->covers_until_message_id;

        $since = $conversation->messages()
            ->when($lastCovered, fn ($q) => $q->where('id', '>', $lastCovered))
            ->count();

        if ($since >= 30) {
            $summarizer->summarize($conversation, $this->companyId);
        }
    }
}
