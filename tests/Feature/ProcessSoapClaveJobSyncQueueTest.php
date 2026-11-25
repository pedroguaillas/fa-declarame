<?php

namespace Tests\Feature;

use App\Jobs\ProcessSoapClaveJob;
use App\Models\Tenant\SriScrapeJob;
use App\Services\ShopImportService;
use App\Services\SriSoapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Con QUEUE_CONNECTION=sync, release() no reencola el job: antes un throttle
 * o un SOAP nulo hacía que la clave se perdiera sin sumar imported ni errors.
 */
class ProcessSoapClaveJobSyncQueueTest extends TestCase
{
    private const COMPANY_ID = 1;

    private const CLAVE = '3010202601179009835400120010060266772050000000112';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['sri_scrape_jobs', 'shops', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('autorization')->nullable();
            $table->timestamps();
        });

        Schema::create('sri_scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });

        DB::table('companies')->insert(['id' => self::COMPANY_ID, 'ruc' => '0993403772001']);

        Sleep::fake();
        Redis::shouldReceive('throttle')->andThrow(new \RuntimeException('Redis no disponible'));
    }

    public function test_sync_queue_retries_soap_inline_and_imports_when_sri_eventually_answers(): void
    {
        $scrapeJob = SriScrapeJob::create(['company_id' => self::COMPANY_ID, 'type' => 'compras', 'status' => 'completed', 'result' => []]);
        $autorizacion = (object) ['estado' => 'AUTORIZADO'];

        $this->mock(SriSoapService::class, function (MockInterface $mock) use ($autorizacion) {
            $mock->shouldReceive('authorize')->with(self::CLAVE)->times(3)->andReturn(null, null, $autorizacion);
        });
        $this->mock(ShopImportService::class, function (MockInterface $mock) use ($autorizacion) {
            $mock->shouldReceive('processFromAutorizacion')->once()->with($autorizacion, self::CLAVE, self::COMPANY_ID, '0993403772001')->andReturn(['imported' => 1]);
        });

        ProcessSoapClaveJob::dispatchSync(self::CLAVE, self::COMPANY_ID, 'compras', $scrapeJob->id);

        $this->assertSame(1, $scrapeJob->fresh()->result['imported'] ?? null);
        $this->assertArrayNotHasKey('errors', $scrapeJob->fresh()->result);
        Sleep::assertSleptTimes(2);
    }

    public function test_sync_queue_counts_error_when_sri_never_answers(): void
    {
        $scrapeJob = SriScrapeJob::create(['company_id' => self::COMPANY_ID, 'type' => 'compras', 'status' => 'completed', 'result' => []]);

        $this->mock(SriSoapService::class, function (MockInterface $mock) {
            $mock->shouldReceive('authorize')->times(3)->andReturn(null);
        });
        $this->mock(ShopImportService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('processFromAutorizacion');
        });

        ProcessSoapClaveJob::dispatchSync(self::CLAVE, self::COMPANY_ID, 'compras', $scrapeJob->id);

        $this->assertSame(1, $scrapeJob->fresh()->result['errors'] ?? null);
        $this->assertArrayNotHasKey('imported', $scrapeJob->fresh()->result);
    }

    public function test_already_imported_clave_is_counted_as_skipped(): void
    {
        $scrapeJob = SriScrapeJob::create(['company_id' => self::COMPANY_ID, 'type' => 'compras', 'status' => 'completed', 'result' => []]);
        DB::table('shops')->insert(['company_id' => self::COMPANY_ID, 'autorization' => self::CLAVE]);

        $this->mock(SriSoapService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('authorize');
        });

        ProcessSoapClaveJob::dispatchSync(self::CLAVE, self::COMPANY_ID, 'compras', $scrapeJob->id);

        $this->assertSame(1, $scrapeJob->fresh()->result['skipped'] ?? null);
    }
}
