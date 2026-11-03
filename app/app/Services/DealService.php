<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealService
{
    /**
     * Move a deal to another stage: resets stage_entered_at, records a
     * stage_change activity, sets closed_at and the mandatory lost reason.
     */
    public function changeStage(Deal $deal, Stage $stage, ?string $lostReason = null, ?int $actorId = null): Deal
    {
        if ($deal->stage_id === $stage->id) {
            return $deal;
        }

        if ($stage->kind === 'lost' && blank($lostReason)) {
            throw ValidationException::withMessages([
                'lost_reason' => 'A lost reason is required when marking a deal Lost.',
            ]);
        }

        return DB::transaction(function () use ($deal, $stage, $lostReason, $actorId) {
            $from = $deal->stage->name;
            $wasOpen = $deal->isOpen();

            $deal->stage_id = $stage->id;
            $deal->stage_entered_at = now();

            if ($stage->kind === 'lost') {
                $deal->lost_reason = $lostReason;
            }

            if ($stage->isClosed() && $wasOpen) {
                $deal->closed_at = now();
            }

            if (! $stage->isClosed()) {
                $deal->closed_at = null;
                $deal->lost_reason = null;
            }

            $deal->save();

            Activity::record($deal->company_id, $deal, 'stage_change', $actorId, [
                'from' => $from,
                'to' => $stage->name,
                'kind' => $stage->kind,
                'lost_reason' => $lostReason,
            ]);

            // Reopening a closed deal clears done auto-tasks about staleness.
            if (! $stage->isClosed()) {
                Task::where('subject_type', Deal::class)
                    ->where('subject_id', $deal->id)
                    ->where('kind', 'stale_deal')
                    ->whereNull('done_at')
                    ->delete();
            }

            return $deal->refresh();
        });
    }

    /**
     * Create (or refresh) a task for a stale deal on its owner. Idempotent:
     * one open stale_deal task per deal.
     */
    public function createStaleTask(Deal $deal): Task
    {
        $existing = Task::where('subject_type', Deal::class)
            ->where('subject_id', $deal->id)
            ->where('kind', 'stale_deal')
            ->whereNull('done_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $task = new Task([
            'assignee_id' => $deal->owner_id,
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
            'title' => "Stale deal: {$deal->title} ({$deal->stage->name}, limit {$deal->stage->stale_after_days}d)",
            'kind' => 'stale_deal',
            'due_at' => now(),
        ]);
        $task->company_id = $deal->company_id;
        $task->save();

        return $task;
    }
}
