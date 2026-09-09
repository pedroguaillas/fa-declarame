<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('sri:daily-scrape')
//     ->dailyAt('00:03')
//     ->timezone('America/Guayaquil')
//     ->withoutOverlapping();

Schedule::command('sri:rescue-stuck-jobs --hours=1 --mark-failed')
    ->everyFifteenMinutes()
    ->timezone('America/Guayaquil')
    ->withoutOverlapping();
