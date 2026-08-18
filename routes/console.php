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

// Procesa la cola (correos) aprovechando el cron de schedule:run ya configurado en Hostinger
Schedule::command('queue:work --stop-when-empty --tries=3 --backoff=90 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
