<?php
require 'vendor/autoload.php';

// Test the new calendar-based week calculation
$testDates = [
    '2025-01-01',
    '2025-12-25',
    '2025-12-28',
    '2025-12-29',
    '2025-12-30',
    '2025-12-31',
    '2026-01-01',
    '2026-01-02',
];

echo "Testing Calendar-Based Week Calculation:\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($testDates as $dateStr) {
    $date = \Carbon\Carbon::parse($dateStr);
    $weekData = \App\Models\Audit::getCalendarYearAndWeek($date);

    echo sprintf(
        "%s (%s): Year %d, Week %d\n",
        $date->format('Y-m-d'),
        $date->locale('es')->dayName,
        $weekData['year'],
        $weekData['week']
    );
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Expected: Dec 31, 2025 should be Year 2025, Week 53\n";
echo "Expected: Jan 1, 2026 should be Year 2026, Week 1\n";
