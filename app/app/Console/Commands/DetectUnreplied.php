<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Task;
use App\Services\RoundRobinAssigner;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * F8: leads not replied within the company's working-hour threshold get a
 * task for the assignee. Escalation counts working hours (spec: >2 working
 * hours), approximated here as: unreplied conversation whose inbound arrived
 * more than 2 working hours ago per the company schedule.
 */
class DetectUnreplied extends Command
{
    protected $signature = 'relay:detect-unreplied';

    protected $description = 'Create follow-up tasks for WhatsApp leads that went unreplied past the working-hour threshold';

    public function handle(RoundRobinAssigner $assigner): int
    {
        $count = 0;

        Company::query()->each(function (Company $company) use (&$count) {
            Conversation::withoutGlobalScopes()
                ->forCompany($company)
                ->view('unreplied')
                ->with('assignee')
                ->get()
                ->each(function (Conversation $conversation) use ($company, &$count) {
                    $deadline = $this->workingHoursDeadline($company, $conversation->last_inbound_at, 2);

                    if (now($company->timezone)->lt($deadline)) {
                        return;
                    }

                    if (! $conversation->assignee_id) {
                        return;
                    }

                    $existing = Task::where('subject_type', Conversation::class)
                        ->where('subject_id', $conversation->id)
                        ->where('kind', 'unreplied')
                        ->whereNull('done_at')
                        ->exists();

                    if ($existing) {
                        return;
                    }

                    Task::create([
                        'company_id' => $company->id,
                        'assignee_id' => $conversation->assignee_id,
                        'subject_type' => Conversation::class,
                        'subject_id' => $conversation->id,
                        'title' => "Reply to {$conversation->contact->name} (waiting "
                            .(int) $conversation->last_inbound_at->diffInHours(now()).'h)',
                        'kind' => 'unreplied',
                        'due_at' => now(),
                    ]);

                    $count++;
                });
        });

        $this->info("{$count} unreplied task(s) created.");

        return self::SUCCESS;
    }

    /**
     * Returns the moment inbound + N working hours was reached, walking the
     * company schedule in its timezone. Null when the schedule has no hours.
     */
    private function workingHoursDeadline(Company $company, CarbonInterface $inbound, int $hours): CarbonInterface
    {
        $cursor = $inbound->copy()->timezone($company->timezone);
        $remaining = $hours;

        for ($i = 0; $i < 14 && $remaining > 0; $i++) {
            $hoursRow = $company->workingHours()->where('weekday', (int) $cursor->format('w'))->first();

            if ($hoursRow) {
                $dayStart = $cursor->copy()->setTimeFromTimeString($hoursRow->start_time->format('H:i'));
                $dayEnd = $cursor->copy()->setTimeFromTimeString($hoursRow->end_time->format('H:i'));

                if ($cursor->lt($dayStart)) {
                    $cursor = $dayStart->copy();
                }

                if ($cursor->lt($dayEnd)) {
                    $availableMinutes = $cursor->diffInMinutes($dayEnd); // Carbon 3: signed, from cursor to dayEnd

                    if ($availableMinutes >= $remaining * 60) {
                        return $cursor->copy()->addMinutes($remaining * 60);
                    }

                    $remaining -= $availableMinutes / 60;
                    $cursor = $dayEnd;
                }
            }

            $cursor = $cursor->addDay()->setTime(0, 0);
        }

        return $cursor;
    }
}
