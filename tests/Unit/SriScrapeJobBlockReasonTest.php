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

    public function test_blocks_when_completed_without_errors_and_with_records(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', ['imported' => 380, 'skipped' => 20, 'errors' => 0]),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('satisfactoriamente', $reason);
        $this->assertStringContainsString('380 importados', $reason);
        $this->assertStringContainsString('20 omitidos', $reason);
    }

    public function test_blocks_when_completed_with_all_zero_counters(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', ['imported' => 0, 'skipped' => 0, 'errors' => 0]),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('satisfactoriamente', $reason);
    }

    public function test_blocks_when_completed_with_null_result(): void
    {
        $jobs = new Collection([
            $this->makeJob('completed', null),
        ]);

        $reason = SriScrapeJob::blockReason($jobs);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('satisfactoriamente', $reason);
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
}
