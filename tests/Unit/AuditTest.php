<?php

use App\Models\Audit;
use Carbon\Carbon;

test('it calculates calendar week correctly', function () {
    // Test January 1st - should be Week 1
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-01'));
    expect($result['year'])->toBe(2025)
        ->and($result['week'])->toBe(1);

    // Test mid-year date
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-06-15'));
    expect($result['year'])->toBe(2025)
        ->and($result['week'])->toBe(24);

    // Test December 31st - should be Week 52
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-31'));
    expect($result['year'])->toBe(2025)
        ->and($result['week'])->toBe(52);
});

test('it handles year boundary correctly', function () {
    // Last days of 2025 should be Week 52 of 2025
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-30'));
    expect($result['year'])->toBe(2025)
        ->and($result['week'])->toBe(52);

    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-31'));
    expect($result['year'])->toBe(2025)
        ->and($result['week'])->toBe(52);

    // First day of 2026 should be Week 1 of 2026
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2026-01-01'));
    expect($result['year'])->toBe(2026)
        ->and($result['week'])->toBe(1);
});

test('it handles leap year correctly', function () {
    // 2024 is a leap year
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2024-12-31'));
    expect($result['year'])->toBe(2024)
        ->and($result['week'])->toBe(52);

    // Feb 29 exists in leap year
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2024-02-29'));
    expect($result['year'])->toBe(2024)
        ->and($result['week'])->toBe(9);
});

test('it caps week at 52', function () {
    $dates = [
        '2025-12-28',
        '2025-12-29',
        '2025-12-30',
        '2025-12-31',
    ];

    foreach ($dates as $dateStr) {
        $result = Audit::getCalendarYearAndWeek(Carbon::parse($dateStr));
        expect($result['week'])->toBeLessThanOrEqual(52, "Week should not exceed 52 for date: $dateStr");
    }
});

test('it uses current date when null', function () {
    $result = Audit::getCalendarYearAndWeek(null);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['year', 'week'])
        ->and($result['year'])->toBe(now()->year)
        ->and($result['week'])->toBeGreaterThanOrEqual(1)
        ->and($result['week'])->toBeLessThanOrEqual(52);
});

test('it calculates weeks consistently', function () {
    // Week 1 should contain days 1-7
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-07'));
    expect($result['week'])->toBe(1);

    // Week 2 should start on day 8
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-08'));
    expect($result['week'])->toBe(2);

    // Week 52 should contain the last days
    $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-25'));
    expect($result['week'])->toBe(52);
});
