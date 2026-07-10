<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\SriScrapeController;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class SriScrapeSemesterTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_months_returns_single_month_when_no_end_month(): void
    {
        $job = new SriScrapeJob;
        $job->month = 4;
        $job->end_month = null;

        $this->assertSame([4], $job->months());
    }

    public function test_months_returns_full_range_for_semester_job(): void
    {
        $job = new SriScrapeJob;
        $job->month = 7;
        $job->end_month = 12;

        $this->assertSame([7, 8, 9, 10, 11, 12], $job->months());
    }

    public function test_first_semester_derived_from_any_month_between_january_and_june(): void
    {
        Carbon::setTestNow('2026-09-15');

        $this->assertSame([1, 6], SriScrapeController::resolveSemesterRange(2026, 3));
        $this->assertSame([1, 6], SriScrapeController::resolveSemesterRange(2026, 6));
    }

    public function test_second_semester_derived_from_any_month_between_july_and_december(): void
    {
        Carbon::setTestNow('2027-01-10');

        $this->assertSame([7, 12], SriScrapeController::resolveSemesterRange(2026, 7));
        $this->assertSame([7, 12], SriScrapeController::resolveSemesterRange(2026, 11));
    }

    public function test_current_year_semester_is_capped_at_current_month(): void
    {
        Carbon::setTestNow('2026-09-15');

        $this->assertSame([7, 9], SriScrapeController::resolveSemesterRange(2026, 8));
    }

    public function test_past_year_semester_is_not_capped(): void
    {
        Carbon::setTestNow('2026-09-15');

        $this->assertSame([7, 12], SriScrapeController::resolveSemesterRange(2025, 10));
    }
}
