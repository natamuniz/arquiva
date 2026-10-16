<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('arquiva:check-fixity')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('arquiva:prune-orphans')->dailyAt('03:00')->withoutOverlapping();
