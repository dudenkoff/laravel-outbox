<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping makes sure only one relay runs at a time,
// so the relay query does not need row locking.
//
// A failed run means the queue could not be reached. Nothing is lost, the
// events stay pending and the next run picks them up, but somebody should
// know that events are not going out right now.
Schedule::command('outbox:relay')
    ->everyMinute()
    ->withoutOverlapping()
    ->onFailure(fn () => Log::critical('Outbox relay failed, events are not being dispatched.'));

Schedule::command('outbox:prune')->dailyAt('03:00');
