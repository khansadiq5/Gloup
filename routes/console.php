<?php

use App\Console\Commands\PruneExpiredReservations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the prune expired reservations command to run every minute
Schedule::command(PruneExpiredReservations::class)->everyMinute();
