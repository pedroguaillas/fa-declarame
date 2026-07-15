<?php

namespace Tests\Unit;

use App\Models\Tenant\SriScrapeJob;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SriScrapeJobBlockReasonTest extends TestCase
{
    /**
     * @param  array<string, int>|null  $result
     */
    private function makeJob(string $status, ?array $result): SriScrapeJob
    {
        $job = new SriScrapeJob;
        $job->status = $status;
        $job->result = $result;

        return $job;
    }

    public function test_allows_download_when_no_previous_jobs(): void
    {
        $this->assertNull(SriScrapeJob::blockReason(new Collection));
    }

    public function test_allows_retry_after_single_satisfactory_completion(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', ['imported' => 380, 'skipped' => 20, 'errors' => 0]),
        ]);

        $this->assertNull(SriScrapeJob::blockReason($jobs));
    }

    public function test_allows_retry_after_two_satisfactory_completions(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', ['imported' => 264, 'skipped' => 0, 'errors' => 0]),
            $this->makeJob('completed', ['imported' => 38, 'skipped' => 264, 'errors' => 0]),
        ]);

        $this->assertNull(SriScrapeJob::blockReason($jobs));
    }

    public function test_allows_retry_with_two_failed_attempts(): void
    {
        $jobs = new Collection([
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
        ]);

        $this->assertNull(SriScrapeJob::blockReason($jobs));
    }

    public function test_blocks_after_three_failed_attempts(): void
    {
        $jobs = new Collection([
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('3 intentos', $reason);
    }

    public function test_completed_with_errors_counts_as_failed_attempt(): void
    {
        $jobs = new Collection([
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
            $this->makeJob('completed', ['imported' => 10, 'skipped' => 2, 'errors' => 5]),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('3 intentos', $reason);
    }

    public function test_allows_retry_when_single_completed_has_errors(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', ['imported' => 10, 'skipped' => 2, 'errors' => 5]),
        ]);

        $this->assertNull(SriScrapeJob::blockReason($jobs));
    }

    public function test_blocks_after_five_total_attempts(): void
    {
        $jobs = new Collection([
            $this->makeJob('failed', null),
            $this->makeJob('completed', ['imported' => 200, 'skipped' => 0, 'errors' => 0]),
            $this->makeJob('completed', ['imported' => 50, 'skipped' => 200, 'errors' => 0]),
            $this->makeJob('completed', ['imported' => 10, 'skipped' => 250, 'errors' => 0]),
            $this->makeJob('completed', ['imported' => 5, 'skipped' => 260, 'errors' => 0]),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('5 intentos', $reason);
    }

    public function test_blocks_takes_precedence_when_both_limits_hit(): void
    {
        $jobs = new Collection([
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
            $this->makeJob('failed', null),
            $this->makeJob('completed', ['imported' => 10, 'skipped' => 0, 'errors' => 0]),
            $this->makeJob('completed', ['imported' => 5, 'skipped' => 10, 'errors' => 0]),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('5 intentos', $reason);
    }
}
