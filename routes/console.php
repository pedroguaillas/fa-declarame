<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sri:daily-scrape')
    ->dailyAt('00:37')
    ->timezone('America/Guayaquil')
    ->withoutOverlapping();
