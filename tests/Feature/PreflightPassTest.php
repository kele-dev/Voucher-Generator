<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Concerns\UsesFileDatabase;
use Tests\TestCase;

/**
 * The happy path needs a real WAL-mode SQLite file, so it lives apart from
 * the in-memory OperationsTest.
 */
class PreflightPassTest extends TestCase
{
    use UsesFileDatabase;

    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFileDatabase();
        $this->backupRoot = storage_path('framework/testing/backups-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupRoot);
        $this->tearDownFileDatabase();
        parent::tearDown();
    }

    public function test_preflight_passes_on_a_production_configuration(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.url' => 'https://voucher.example.com',
            'session.secure' => true,
            'session.http_only' => true,
            'logging.channels.single.level' => 'warning',
            'logging.channels.daily.level' => 'warning',
            'queue.default' => 'database',
            'cache.default' => 'file',
            'voucher.backups.path' => $this->backupRoot,
        ]);

        $this->artisan('voucher:preflight')
            ->expectsOutputToContain('Siap produksi')
            ->assertSuccessful();
    }
}
