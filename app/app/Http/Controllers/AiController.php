<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Services\AI\ConversationSummarizer;
use App\Services\AI\LeadScorer;
use App\Services\AI\ReplyDrafter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiController extends Controller
{
    public function __construct(
        private readonly ConversationSummarizer $summarizer,
        private readonly ReplyDrafter $drafter,
        private readonly LeadScorer $scorer,
    ) {}

    /**
     * POST /inbox/{id}/summarize
     */
    public function summarize(Request $request, Conversation $conversation)
    {
        $summary = $this->summarizer->summarize($conversation, app('relay.company')->id);

        if (! $summary) {
            throw ValidationException::withMessages([
                'summary' => 'Not enough new messages to summarize (or AI failed). Try again later.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'points' => $summary->points,
                'next_step' => $summary->next_step,
                'model' => $summary->model,
                'created_at' => $summary->created_at->toISOString(),
            ]);
        }

        return back();
    }

    /**
     * POST /inbox/{id}/draft-reply — returns a draft; nothing is sent.
     */
    public function draftReply(Request $request, Conversation $conversation)
    {
        $result = $this->drafter->draft($conversation, app('relay.company')->id);

        if ($result['draft'] === null) {
            throw ValidationException::withMessages(['draft' => $result['error'] ?? 'Draft failed.']);
        }

        return response()->json(['draft' => $result['draft']]);
    }

    /**
     * POST /contacts/{id}/score — recompute (throttled per contact).
     */
    public function score(Request $request, Contact $contact)
    {
        $this->scorer->score($contact, app('relay.company')->id);

        return back();
    }

    /**
     * POST /contacts/{id}/score-override — manager correction, audited.
     */
    public function scoreOverride(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'to' => ['required', 'integer', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'max:300'],
        ]);

        $this->scorer->override($contact, $request->user()->id, $data['to'], $data['reason']);

        return back();
    }
}
