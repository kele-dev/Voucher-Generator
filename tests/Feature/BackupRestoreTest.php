<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\GenerationBatch;
use App\Services\Backup\BackupManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\UsesFileDatabase;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use UsesFileDatabase;

    private string $backupRoot;

    private string $privateRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFileDatabase();

        $this->backupRoot = storage_path('framework/testing/backups-'.uniqid());
        config(['voucher.backups.path' => $this->backupRoot]);

        // Isolated private disk on real files (VACUUM INTO and zip need paths).
        $this->privateRoot = storage_path('framework/testing/private-'.uniqid());
        config(['filesystems.disks.local.root' => $this->privateRoot]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupRoot);
        File::deleteDirectory($this->privateRoot);
        $this->tearDownFileDatabase();

        parent::tearDown();
    }

    private function backups(): BackupManager
    {
        return app(BackupManager::class);
    }

    public function test_backup_contains_consistent_database_and_storage_but_not_exports(): void
    {
        Brand::factory()->create(['name' => 'Bebek Boedjang']);
        Storage::disk('local')->put('templates/a.jpg', 'template-bytes');
        Storage::disk('local')->put('exports/batch-1.zip', 'rebuildable');

        $this->artisan('voucher:backup')->assertSuccessful();

        $backup = $this->backups()->latest();
        $this->assertNotNull($backup);
        $this->assertSame([], $this->backups()->verify($backup['path']));

        $pdo = new \PDO('sqlite:'.$backup['path'].'/database.sqlite');
        $this->assertSame('Bebek Boedjang', $pdo->query('select name from brands')->fetchColumn());
        $pdo = null;

        $zip = new \ZipArchive;
        $zip->open($backup['path'].'/storage.zip');
        $this->assertNotFalse($zip->locateName('templates/a.jpg'));
        $this->assertFalse($zip->locateName('exports/batch-1.zip'), 'exports are rebuildable and excluded');
        $zip->close();
    }

    public function test_tampered_backup_fails_verification_and_is_never_restored(): void
    {
        Brand::factory()->create(['name' => 'Asli']);
        $dir = $this->backups()->create();
        file_put_contents($dir.'/database.sqlite', 'garbage', FILE_APPEND);

        $this->artisan('voucher:backup:verify', ['backup' => basename($dir)])->assertFailed();
        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true])->assertFailed();

        $this->assertSame(1, Brand::count());
    }

    public function test_restore_brings_back_data_and_files_and_keeps_a_safety_backup(): void
    {
        $brand = Brand::factory()->create(['name' => 'Sebelum']);
        Storage::disk('local')->put('templates/a.jpg', 'original');
        $dir = $this->backups()->create();

        // Damage after the backup: data deleted, file changed, file added.
        $brand->delete();
        Brand::factory()->create(['name' => 'Sesudah']);
        Storage::disk('local')->put('templates/a.jpg', 'corrupted');
        Storage::disk('local')->put('templates/new.jpg', 'added later');

        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true])->assertSuccessful();

        $this->assertSame(['Sebelum'], Brand::pluck('name')->all());
        $this->assertSame('original', Storage::disk('local')->get('templates/a.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('templates/new.jpg'));
        $this->assertCount(2, $this->backups()->list(), 'restore must first take a safety backup');
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_restore_clears_links_to_exports_that_no_longer_exist(): void
    {
        $campaign = Campaign::factory()->create();
        $batch = $campaign->batches()->create([
            'idempotency_key' => (string) Str::uuid(), 'requested_count' => 1, 'status' => BatchStatus::Completed,
            'export_status' => BatchStatus::Completed, 'export_path' => 'exports/gone.zip', 'exported_at' => now(),
        ]);
        $dir = $this->backups()->create();

        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true])->assertSuccessful();

        $batch = GenerationBatch::find($batch->id);
        $this->assertNull($batch->export_path);
        $this->assertNull($batch->export_status);
    }

    public function test_restore_waits_for_running_jobs_and_aborts_safely(): void
    {
        Brand::factory()->create(['name' => 'Tetap']);
        $dir = $this->backups()->create();
        Brand::factory()->create(['name' => 'Baru']);

        // A job a worker is processing right now.
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 1,
            'reserved_at' => now()->getTimestamp(), 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp(),
        ]);

        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true, '--wait' => 0])
            ->expectsOutputToContain('Masih ada job queue yang berjalan')
            ->assertFailed();

        $this->assertSame(2, Brand::count(), 'nothing restored while a job was running');
        $this->assertFalse(app()->isDownForMaintenance(), 'app back online after aborting');
    }

    public function test_restore_migrates_and_asks_workers_to_reconnect(): void
    {
        $dir = $this->backups()->create();
        Cache::forget('illuminate:queue:restart');

        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true])->assertSuccessful();

        $this->assertNotNull(Cache::get('illuminate:queue:restart'), 'queue:restart signal must be sent');
        $this->assertSame(DB::table('migrations')->count(), count(File::files(database_path('migrations'))));
    }

    public function test_backup_with_empty_storage_is_valid_and_restorable(): void
    {
        Brand::factory()->create(['name' => 'Instalasi baru']);

        $dir = $this->backups()->create();

        $this->assertFileDoesNotExist($dir.'/storage.zip');
        $this->assertSame([], $this->backups()->verify($dir));

        Storage::disk('local')->put('templates/later.jpg', 'x');
        $this->artisan('voucher:restore', ['backup' => basename($dir), '--force' => true])->assertSuccessful();
        $this->assertFalse(Storage::disk('local')->exists('templates/later.jpg'));
    }

    public function test_retention_keeps_dailies_and_one_per_recent_week(): void
    {
        config(['voucher.backups.keep_daily' => 3, 'voucher.backups.keep_weekly' => 2]);

        // 21 daily backups, newest first: 2026-10-21 … 2026-10-01.
        foreach (range(1, 21) as $day) {
            $date = now()->setDate(2026, 10, $day)->setTime(2, 0);
            $dir = $this->backupRoot.'/'.$date->format('Ymd-His');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/manifest.json', json_encode(['created_at' => $date->toIso8601String(), 'files' => []]));
        }

        $this->backups()->prune();

        $kept = $this->backups()->list()->pluck('name')->all();
        // 3 newest days + newest of the 2 most recent ISO weeks (one overlaps).
        $this->assertContains('20261021-020000', $kept);
        $this->assertContains('20261020-020000', $kept);
        $this->assertContains('20261019-020000', $kept);
        $this->assertContains('20261018-020000', $kept); // newest of the previous ISO week (Sun 18 Oct)
        $this->assertCount(4, $kept);
    }
}
