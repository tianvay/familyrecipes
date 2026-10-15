<?php

namespace Tests\Unit;

use App\Support\Week;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WeekTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-14 18:00'); // Mittwoch, KW 42
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_current_week_starts_on_monday(): void
    {
        $week = Week::current();

        $this->assertSame('2026-10-12', $week->start->toDateString());
        $this->assertSame('2026-10-18', $week->end()->toDateString());
        $this->assertSame('2026-W42', $week->iso());
        $this->assertSame('KW 42 · 12.10. – 18.10.2026', $week->label());
    }

    public function test_iso_round_trip_including_year_boundaries(): void
    {
        $this->assertSame('2026-W01', Week::fromIso('2026-W01')->iso());
        $this->assertSame('2025-12-29', Week::fromIso('2026-W01')->start->toDateString());
        $this->assertSame('2026-W53', Week::fromIso('2026-W53')->iso());
        $this->assertSame('2026-W53', Week::containing('2027-01-01')->iso());
    }

    public function test_invalid_weeks_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Week::fromIso('2025-W53');
    }

    public function test_only_this_and_next_week_are_open(): void
    {
        $this->assertFalse(Week::fromIso('2026-W41')->isOpen());
        $this->assertTrue(Week::fromIso('2026-W42')->isOpen());
        $this->assertTrue(Week::fromIso('2026-W43')->isOpen());
        $this->assertFalse(Week::fromIso('2026-W44')->isOpen());
    }

    public function test_days_are_numbered_monday_to_sunday(): void
    {
        $days = Week::current()->days();

        $this->assertSame(range(1, 7), $days->keys()->all());
        $this->assertSame('2026-10-12', $days[1]->toDateString());
        $this->assertSame('2026-10-18', $days[7]->toDateString());
        $this->assertSame('Sonntag', Week::dayName(7));
    }

    public function test_relative_names(): void
    {
        $this->assertSame('Diese Woche', Week::current()->relativeName());
        $this->assertSame('Nächste Woche', Week::current()->next()->relativeName());
        $this->assertSame('Letzte Woche', Week::current()->previous()->relativeName());
        $this->assertNull(Week::fromIso('2026-W30')->relativeName());
    }
}
