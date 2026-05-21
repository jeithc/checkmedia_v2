<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('checkmedia:check-preventive')
    ->dailyAt('06:00')
    ->appendOutputTo(storage_path('logs/preventive_schedules.log'));

Schedule::command('checkmedia:sync-purchase-orders') // pragma: allowlist secret
    ->everyFourHours()
    ->appendOutputTo(storage_path('logs/purchase_order_sync.log'));
