<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backstop for the turn clock. Phases roll over on a delayed queue job; this
// catches any that were missed because the worker was not running.
Schedule::command('game:tick')->everyMinute()->withoutOverlapping();
