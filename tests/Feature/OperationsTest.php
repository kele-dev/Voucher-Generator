<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Http\Controllers\HealthController;
use App\Jobs\ExportVoucherBatch;
use App\Jobs\GenerateVoucherBatch;
use App\Jobs\QueueHeartbeat;
use App\Models\Campaign;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_retry_after_exceeds_every_job_timeout(): void
    {
        // Otherwise a still-running job is handed to a second worker.
        $retryAfter = config('queue.connections.database.retry_after');

        foreach ([new GenerateVoucherBatch(0), new ExportVoucherBatch(0), new QueueHeartbeat] as $job) {
            $this->assertGreaterThan($job->timeout, $retryAfter, $job::class);
        }
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', $csp)[1], 'no inline scripts');
    }

    public function test_preflight_fails_on_a_development_configuration(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost']);

        $this->artisan('voucher:preflight')
            ->expectsOutputToContain('Belum siap produksi')
            ->assertFailed();
    }

    public function test_preflight_rejects_cache_inside_the_sqlite_database(): void
    {
        // queue:restart signals would be written into the file restore replaces.
        config(['database.default' => 'sqlite', 'cache.default' => 'database']);

        $this->artisan('voucher:preflight')
            ->expectsOutputToContain('Cache store tidak di database SQLite')
            ->assertFailed();
    }

    public function test_health_hides_details_without_token_and_reports_problems(): void
    {
        config(['voucher.health_token' => 'rahasia', 'voucher.backups.path' => storage_path('framework/testing/none-'.uniqid())]);
        Cache::flush();

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'degraded']);

        $this->getJson('/health', ['X-Health-Token' => 'rahasia'])
            ->assertStatus(503)
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.queue_worker.ok', false)
            ->assertJsonPath('checks.backup.ok', false);
    }

    public function test_health_is_ok_when_everything_is_fresh(): void
    {
        $root = storage_path('framework/testing/health-'.uniqid());
        File::ensureDirectoryExists($root.'/20261008-020000');
        File::put($root.'/20261008-020000/manifest.json', json_encode(['created_at' => now()->subHour()->toIso8601String(), 'files' => []]));
        config(['voucher.health_token' => 'rahasia', 'voucher.backups.path' => $root]);
        Cache::forever(QueueHeartbeat::CACHE_KEY, now()->getTimestamp());
        Cache::forever(HealthController::SCHEDULER_KEY, now()->getTimestamp());

        $this->getJson('/health', ['X-Health-Token' => 'rahasia'])->assertOk()->assertJsonPath('status', 'ok');
        // Tokens in URLs leak into access logs, so they are not accepted.
        $this->getJson('/health?token=rahasia')->assertOk()->assertJsonMissingPath('checks');

        File::deleteDirectory($root);
    }

    public function test_health_does_not_create_sessions(): void
    {
        $this->get('/health')->assertCookieMissing(config('session.cookie'));
    }

    public function test_prune_exports_removes_old_zips_and_resets_their_batches(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $campaign = Campaign::factory()->create();

        $make = fn (string $path, $exportedAt) => tap($campaign->batches()->create([
            'idempotency_key' => (string) Str::uuid(), 'requested_count' => 1, 'status' => BatchStatus::Completed,
            'export_status' => BatchStatus::Completed, 'export_path' => $path, 'exported_at' => $exportedAt,
        ]), fn () => $disk->put($path, 'zip'));

        $old = $make('exports/old.zip', now()->subDays(40));
        $fresh = $make('exports/fresh.zip', now()->subDays(2));
        $disk->put('exports/orphan.zip', 'zip');
        touch($disk->path('exports/orphan.zip'), now()->subDays(3)->getTimestamp());

        $this->artisan('voucher:prune-exports')->assertSuccessful();

        $this->assertFalse($disk->exists('exports/old.zip'));
        $this->assertFalse($disk->exists('exports/orphan.zip'));
        $this->assertTrue($disk->exists('exports/fresh.zip'));
        $this->assertNull($old->fresh()->export_path);
        $this->assertNull($old->fresh()->export_status);
        $this->assertSame('exports/fresh.zip', $fresh->fresh()->export_path);
    }

    public function test_prune_leaves_a_batch_that_is_being_re_exported_alone(): void
    {
        Storage::fake('local');
        $campaign = Campaign::factory()->create();
        // Old export, but a re-export has since been queued.
        $batch = $campaign->batches()->create([
            'idempotency_key' => (string) Str::uuid(), 'requested_count' => 1, 'status' => BatchStatus::Completed,
            'export_status' => BatchStatus::Pending, 'export_path' => 'exports/old.zip', 'exported_at' => now()->subDays(40),
        ]);
        Storage::disk('local')->put('exports/old.zip', 'zip');

        $this->artisan('voucher:prune-exports')->assertSuccessful();

        $this->assertSame(BatchStatus::Pending, $batch->fresh()->export_status);
        $this->assertSame('exports/old.zip', $batch->fresh()->export_path);
    }

    public function test_scheduler_registers_operational_tasks(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => trim(preg_replace('/^.*artisan\S*\s+/', '', (string) $event->command)))
            ->filter()
            ->values();

        foreach (['voucher:backup', 'voucher:backup:verify', 'voucher:prune-exports', 'queue:prune-failed --hours=720'] as $expected) {
            $this->assertTrue(
                $commands->contains(fn ($c) => str_replace(["'", '"'], '', $c) === $expected),
                "{$expected} not scheduled; got: ".$commands->join(', '),
            );
        }
    }
}
