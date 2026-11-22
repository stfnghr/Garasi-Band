<?php

use App\Services\GarageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kembalikan status garasi yang masa sewanya habis menjadi 'available' setiap hari.
Schedule::call(fn () => app(GarageService::class)->syncStatuses())
    ->dailyAt('00:01')->name('garages:sync-status');
