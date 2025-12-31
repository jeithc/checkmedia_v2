<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Audit;
use Carbon\Carbon;

class AuditTest extends TestCase
{
    /**
     * Test calendar-based week calculation for various dates.
     *
     * @test
     */
    public function it_calculates_calendar_week_correctly()
    {
        // Test January 1st - should be Week 1
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-01'));
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(1, $result['week']);

        // Test mid-year date
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-06-15'));
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(24, $result['week']); // Day 166, week 24

        // Test December 31st - should be Week 52
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-31'));
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(52, $result['week']);
    }

    /**
     * Test year boundary transitions.
     *
     * @test
     */
    public function it_handles_year_boundary_correctly()
    {
        // Last days of 2025 should be Week 52 of 2025
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-30'));
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(52, $result['week']);

        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-31'));
        $this->assertEquals(2025, $result['year']);
        $this->assertEquals(52, $result['week']);

        // First day of 2026 should be Week 1 of 2026
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2026-01-01'));
        $this->assertEquals(2026, $result['year']);
        $this->assertEquals(1, $result['week']);
    }

    /**
     * Test leap year handling (366 days).
     *
     * @test
     */
    public function it_handles_leap_year_correctly()
    {
        // 2024 is a leap year
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2024-12-31'));
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(52, $result['week']); // Day 366, should cap at 52

        // Feb 29 exists in leap year
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2024-02-29'));
        $this->assertEquals(2024, $result['year']);
        $this->assertEquals(9, $result['week']); // Day 60
    }

    /**
     * Test that week number never exceeds 52.
     *
     * @test
     */
    public function it_caps_week_at_52()
    {
        // Test various end-of-year dates
        $dates = [
            '2025-12-28',
            '2025-12-29',
            '2025-12-30',
            '2025-12-31',
        ];

        foreach ($dates as $dateStr) {
            $result = Audit::getCalendarYearAndWeek(Carbon::parse($dateStr));
            $this->assertLessThanOrEqual(52, $result['week'], "Week should not exceed 52 for date: $dateStr");
        }
    }

    /**
     * Test using current date (null parameter).
     *
     * @test
     */
    public function it_uses_current_date_when_null()
    {
        $result = Audit::getCalendarYearAndWeek(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('week', $result);
        $this->assertEquals(now()->year, $result['year']);
        $this->assertGreaterThanOrEqual(1, $result['week']);
        $this->assertLessThanOrEqual(52, $result['week']);
    }

    /**
     * Test week calculation consistency.
     *
     * @test
     */
    public function it_calculates_weeks_consistently()
    {
        // Week 1 should contain days 1-7
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-07'));
        $this->assertEquals(1, $result['week']);

        // Week 2 should start on day 8
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-01-08'));
        $this->assertEquals(2, $result['week']);

        // Week 52 should contain the last days
        $result = Audit::getCalendarYearAndWeek(Carbon::parse('2025-12-25'));
        $this->assertEquals(52, $result['week']);
    }
}
