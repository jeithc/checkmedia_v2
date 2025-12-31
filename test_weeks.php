<?php
require 'vendor/autoload.php';
use Carbon\Carbon;
Carbon::setLocale('es');
$dates = [
    '2025-01-01',
    '2025-12-25',
    '2025-12-28',
    '2025-12-29',
    '2025-12-30',
    '2025-12-31',
    '2026-01-01',
];
foreach ($dates as $dateStr) {
    $d = Carbon::parse($dateStr);
    echo $d->format('Y-m-d') . ' (' . $d->dayName . '):' . PHP_EOL;
    echo '  weekOfYear: ' . $d->weekOfYear . PHP_EOL;
    echo '  week:       ' . $d->week . PHP_EOL;
    echo '  isoWeek:    ' . $d->isoWeek . PHP_EOL;
    echo '  week (en-US context?): ' . $d->copy()->locale('en_US')->week . PHP_EOL;
}
