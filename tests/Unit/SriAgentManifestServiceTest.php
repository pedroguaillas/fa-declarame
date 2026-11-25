<?php

namespace Tests\Unit;

use App\Services\SriAgentManifestService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SriAgentManifestServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/sri-agent-manifest-'.uniqid();
        mkdir($this->directory);

        $this->writeAgent('1.2.3', 'print("scraper")');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*'));
        rmdir($this->directory);

        parent::tearDown();
    }

    private function writeAgent(string $version, string $scraper): void
    {
        file_put_contents($this->directory.'/server.py', "import os\n\nAGENT_VERSION = \"{$version}\"\n");
        file_put_contents($this->directory.'/test-scraper.py', $scraper);
    }

    public function test_manifest_reads_version_from_server_py(): void
    {
        $manifest = (new SriAgentManifestService($this->directory))->manifest();

        $this->assertSame('1.2.3', $manifest['version']);
        $this->assertSame(['server.py', 'test-scraper.py'], $manifest['files']);
    }

    public function test_hash_matches_concatenated_file_bytes(): void
    {
        $expected = hash('sha256', "import os\n\nAGENT_VERSION = \"1.2.3\"\n".'print("scraper")');

        $this->assertSame($expected, (new SriAgentManifestService($this->directory))->contentHash());
    }

    public function test_hash_changes_when_files_change_without_version_bump(): void
    {
        $service = new SriAgentManifestService($this->directory);
        $before = $service->contentHash();

        $this->writeAgent('1.2.3', 'print("scraper corregido")');

        $this->assertNotSame($before, $service->contentHash());
        $this->assertSame('1.2.3', $service->version());
    }

    public function test_missing_version_throws(): void
    {
        file_put_contents($this->directory.'/server.py', "print('sin version')\n");

        $this->expectException(RuntimeException::class);

        (new SriAgentManifestService($this->directory))->version();
    }

    public function test_real_agent_files_produce_a_manifest(): void
    {
        $manifest = (new SriAgentManifestService(dirname(__DIR__, 2).'/scripts/sri-agent'))->manifest();

        $this->assertMatchesRegularExpression('/^\d+(\.\d+)*$/', $manifest['version']);
        $this->assertSame(64, strlen($manifest['sha256']));
    }
}
