<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

/**
 * Round-robin lead assignment among active reps, respecting company working
 * hours. Outside working hours the company's timezone is used to still hand
 * out owners (queue for the next morning) — but the "unreplied" escalation
 * timer only counts working time.
 */
class RoundRobinAssigner
{
    private const ROTATION_KEY = 'relay:rr:';

    public function nextRep(Company $company): ?User
    {
        if (! $company->round_robin) {
            return null; // Admin turned automatic assignment off: new chats stay unassigned.
        }

        $reps = $company->users()
            ->wherePivot('is_active', true)
            ->whereIn('company_user.role', ['rep', 'manager'])
            ->orderBy('users.id')
            ->get();

        if ($reps->isEmpty()) {
            return null;
        }

        $key = self::ROTATION_KEY.$company->id;
        $lastId = cache()->get($key);

        // Round-robin: pick the next rep after the last assigned one.
        $index = 0;
        if ($lastId) {
            $position = $reps->search(fn (User $u) => $u->id === $lastId);
            if ($position !== false) {
                $index = ($position + 1) % $reps->count();
            }
        }

        $chosen = $reps[$index];
        cache()->forever($key, $chosen->id);

        return $chosen;
    }

    /**
     * Working-hours check per company (weekday + time window in company tz).
     */
    public function withinWorkingHours(Company $company, ?\DateTimeInterface $when = null): bool
    {
        $when ??= now($company->timezone);
        $weekday = (int) $when->format('w'); // 0=Sunday

        $hours = $company->workingHours()->where('weekday', $weekday)->first();

        if (! $hours) {
            return false;
        }

        $minutes = ((int) $when->format('H')) * 60 + (int) $when->format('i');
        $start = ((int) $hours->start_time->format('H')) * 60 + (int) $hours->start_time->format('i');
        $end = ((int) $hours->end_time->format('H')) * 60 + (int) $hours->end_time->format('i');

        return $minutes >= $start && $minutes <= $end;
    }
}
