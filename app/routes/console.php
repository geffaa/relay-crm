<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat otomatis (F8): deal macet — tiap 5 menit. Unreplied hadir di M3.
Schedule::command('relay:detect-stale-deals')->everyFiveMinutes();
