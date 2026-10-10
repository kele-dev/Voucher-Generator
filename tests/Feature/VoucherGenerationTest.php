<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CampaignStatus;
use App\Enums\Role;
use App\Enums\VoucherStatus;
use App\Jobs\ExportVoucherBatch;
use App\Jobs\GenerateVoucherBatch;
use App\Models\Campaign;
use App\Models\GenerationBatch;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTemplate;
use App\Services\Voucher\VoucherCodeGenerator;
use App\Services\Voucher\VoucherRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Queue runs "sync" in tests, so a generate request completes the whole
 * pipeline (codes, then ZIP export) before it returns.
 */
class VoucherGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('templates/test.jpg', UploadedFile::fake()->image('t.jpg', 800, 375)->getContent());
    }

    private function admin(): User
    {
        return User::factory()->role(Role::VoucherAdmin)->create();
    }

    private function campaign(array $attributes = []): Campaign
    {
        $template = VoucherTemplate::factory()->create([
            'image_width' => 800, 'image_height' => 375,
            'fields_json' => VoucherTemplate::wrapFields([[
                'key' => 'voucher_code', 'type' => 'text', 'x' => 0.5, 'y' => 0.8, 'width' => 0.45, 'height' => 0.15,
                'font_family' => 'poppins', 'font_weight' => 'bold', 'font_size' => 30, 'color' => '#E30613',
                'align' => 'center', 'vertical_align' => 'middle',
            ]]),
        ]);

        return Campaign::factory()->create(['brand_id' => $template->brand_id, 'voucher_template_id' => $template->id, ...$attributes]);
    }

    private function generate(Campaign $campaign, int $quantity, ?string $key = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin())->postJson(route('campaigns.batches.store', $campaign), [
            'quantity' => $quantity,
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'export_format' => 'jpg',
            'export_scale' => 100,
        ]);
    }

    public static function quantities(): array
    {
        return [[1], [100], [1000]];
    }

    #[DataProvider('quantities')]
    public function test_generates_requested_number_of_unique_codes(int $quantity): void
    {
        Bus::fake([ExportVoucherBatch::class]); // codes only; export is covered below
        $campaign = $this->campaign();

        $this->generate($campaign, $quantity)->assertStatus(202);

        $batch = GenerationBatch::firstOrFail();
        $this->assertSame(BatchStatus::Completed, $batch->status);
        $this->assertSame($quantity, $batch->generated_count);
        $this->assertSame($quantity, Voucher::count());
        $this->assertSame($quantity, Voucher::distinct()->count('code'));
        Bus::assertDispatched(ExportVoucherBatch::class);
    }

    public function test_vouchers_snapshot_campaign_at_issue_time(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign(['benefit_title' => '1 PORSI']);

        $this->generate($campaign, 1);
        $campaign->update(['benefit_title' => '2 PORSI', 'expires_at' => now()->addYear()]);

        $voucher = Voucher::firstOrFail();
        $this->assertSame('1 PORSI', $voucher->benefit_title);
        $this->assertTrue($voucher->valid_until->lt(now()->addMonths(2)));
        $this->assertSame($campaign->voucher_template_id, $voucher->voucher_template_id);
        $this->assertStringStartsWith('BERLAKU DI SEMUA CABANG', $voucher->outlet_scope_text);
    }

    public function test_same_idempotency_key_returns_same_batch(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign();
        $key = (string) Str::uuid();

        $first = $this->generate($campaign, 10, $key)->assertStatus(202);
        $second = $this->generate($campaign, 10, $key)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, GenerationBatch::count());
        $this->assertSame(10, Voucher::count());
    }

    public function test_idempotency_key_cannot_be_reused_for_another_campaign(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $key = (string) Str::uuid();

        $this->generate($this->campaign(), 1, $key);
        $this->generate($this->campaign(), 1, $key)->assertStatus(409);
    }

    public function test_generation_limit_is_enforced(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign(['generation_limit' => 15]);

        $this->generate($campaign, 10)->assertStatus(202);
        $this->generate($campaign, 6)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->generate($campaign, 5)->assertStatus(202);

        $this->assertSame(15, Voucher::count());
    }

    public static function blockedCampaigns(): array
    {
        return [
            'paused' => [['status' => CampaignStatus::Paused]],
            'closed' => [['status' => CampaignStatus::Closed]],
            'expired' => [['expires_at' => now()->subMinute()]],
        ];
    }

    #[DataProvider('blockedCampaigns')]
    public function test_cannot_generate_for_inactive_or_expired_campaigns(array $attributes): void
    {
        $this->generate($this->campaign($attributes), 1)->assertUnprocessable();
        $this->assertSame(0, GenerationBatch::count());
    }

    public function test_exact_example_pattern_refuses_more_than_its_capacity(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign(['code_prefix' => 'WN-VM', 'code_pattern' => '{PREFIX}{DDMM}{SEQ:3}']);

        $this->generate($campaign, 1000)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->generate($campaign, 3)->assertStatus(202);

        $this->assertSame(
            ['WN-VM'.now()->format('dm').'001', 'WN-VM'.now()->format('dm').'002', 'WN-VM'.now()->format('dm').'003'],
            Voucher::orderBy('id')->pluck('code')->all(),
        );
    }

    public function test_export_builds_zip_with_one_readable_image_per_voucher(): void
    {
        $campaign = $this->campaign();

        $this->generate($campaign, 3)->assertStatus(202);

        $batch = GenerationBatch::firstOrFail();
        $this->assertSame(BatchStatus::Completed, $batch->export_status);
        $this->assertSame(3, $batch->exported_count);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($batch->export_path)));
        $this->assertSame(3, $zip->numFiles);

        $codes = Voucher::pluck('code')->all();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $this->assertContains(basename($name, '.jpg'), $codes);
            $this->assertSame("\xFF\xD8", substr($zip->getFromIndex($i), 0, 2));
        }
        $zip->close();

        $this->get(route('batches.download', $batch))->assertOk()->assertDownload($batch->exportFilename());
    }

    public function test_re_export_replaces_zip_without_touching_vouchers(): void
    {
        $campaign = $this->campaign();
        $this->generate($campaign, 2);
        $batch = GenerationBatch::firstOrFail();
        $firstZip = $batch->export_path;
        $codes = Voucher::orderBy('id')->pluck('code')->all();

        $this->actingAs($this->admin())
            ->postJson(route('batches.export', $batch), ['export_format' => 'png', 'export_scale' => 50])
            ->assertStatus(202);

        $batch->refresh();
        $this->assertNotSame($firstZip, $batch->export_path);
        Storage::disk('local')->assertMissing($firstZip);
        $this->assertSame($codes, Voucher::orderBy('id')->pluck('code')->all());

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($batch->export_path));
        $this->assertStringEndsWith('.png', $zip->getNameIndex(0));
        $this->assertSame(400, getimagesizefromstring($zip->getFromIndex(0))[0]);
        $zip->close();
    }

    public function test_single_voucher_renders_on_demand(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $this->generate($this->campaign(), 1);
        $voucher = Voucher::firstOrFail();

        $response = $this->actingAs(User::factory()->role(Role::Viewer)->create())
            ->get(route('vouchers.download', [$voucher, 'format' => 'png']));

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($response->getContent(), 0, 4));
    }

    public function test_bulk_export_and_zip_are_manager_only(): void
    {
        $campaign = $this->campaign();
        $this->generate($campaign, 1);
        $batch = GenerationBatch::firstOrFail();

        $this->actingAs(User::factory()->role(Role::Viewer)->create());
        $this->get(route('batches.download', $batch))->assertForbidden();
        $this->postJson(route('batches.export', $batch), ['export_format' => 'jpg', 'export_scale' => 100])->assertForbidden();
        $this->getJson(route('batches.show', $batch))->assertOk();
        $this->postJson(route('campaigns.batches.store', $campaign), [])->assertForbidden();
    }

    public function test_staff_cannot_reach_vouchers_of_other_outlets(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign(['applies_to_all_outlets' => false]);
        $campaign->outlets()->attach(Outlet::factory()->create(['brand_id' => $campaign->brand_id]));
        $this->generate($campaign, 1);
        $voucher = Voucher::firstOrFail();

        $staff = User::factory()->role(Role::OutletStaff)->create();
        $staff->outlets()->attach(Outlet::factory()->create(['brand_id' => $campaign->brand_id]));

        $this->actingAs($staff);
        $this->get(route('vouchers.show', $voucher))->assertForbidden();
        $this->get(route('vouchers.download', $voucher))->assertForbidden();
        $this->get('/vouchers')->assertOk()->assertDontSee($voucher->code);
    }

    public function test_expired_status_is_derived_and_filterable(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $this->generate($this->campaign(), 2);
        [$expired, $active] = Voucher::orderBy('id')->get();
        $expired->update(['valid_until' => now()->subDay()]);

        $this->assertSame(VoucherStatus::Expired, $expired->fresh()->effectiveStatus());
        $this->assertSame(VoucherStatus::Issued, $expired->fresh()->status);

        $this->actingAs($this->admin())->get('/vouchers?status=expired')
            ->assertSee($expired->code)
            ->assertDontSee($active->code);
    }

    public function test_limit_cannot_drop_below_pending_batch_commitment(): void
    {
        $campaign = $this->campaign(['generation_limit' => 100]);
        // A batch accepted but not yet processed: no vouchers exist yet.
        $campaign->batches()->create(['idempotency_key' => (string) Str::uuid(), 'requested_count' => 80, 'status' => BatchStatus::Pending]);

        $this->actingAs($this->admin())
            ->put(route('campaigns.update', $campaign), [
                'brand_id' => $campaign->brand_id,
                'voucher_template_id' => $campaign->voucher_template_id,
                'name' => $campaign->name,
                'benefit_title' => $campaign->benefit_title,
                'starts_at' => $campaign->starts_at->format('Y-m-d H:i'),
                'expires_at' => $campaign->expires_at->format('Y-m-d H:i'),
                'code_prefix' => $campaign->code_prefix,
                'code_preset' => 'random',
                'applies_to_all_outlets' => '1',
                'generation_limit' => 50,
            ])
            ->assertSessionHasErrors('generation_limit');

        $this->assertSame(100, $campaign->fresh()->generation_limit);
    }

    public function test_running_the_generate_job_twice_never_over_issues(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $campaign = $this->campaign();
        $generator = app(VoucherCodeGenerator::class);

        // First run "crashes" after 100 vouchers: emulate by issuing 100
        // and leaving the batch mid-flight with 250 requested.
        $batch = $campaign->batches()->create(['idempotency_key' => (string) Str::uuid(), 'requested_count' => 100, 'status' => BatchStatus::Pending]);
        (new GenerateVoucherBatch($batch->id))->handle($generator);
        $batch->update(['requested_count' => 250, 'status' => BatchStatus::Processing]);

        // Redelivered copy resumes from the stored count, then a late
        // duplicate after completion does nothing.
        (new GenerateVoucherBatch($batch->id))->handle($generator);
        (new GenerateVoucherBatch($batch->id))->handle($generator);

        $this->assertSame(250, Voucher::count());
        $this->assertSame(250, $batch->fresh()->generated_count);
        $this->assertSame(BatchStatus::Completed, $batch->fresh()->status);
    }

    public function test_stuck_batch_can_be_retried(): void
    {
        $campaign = $this->campaign();
        // Pending batch whose job was lost: nothing will ever process it.
        $batch = $campaign->batches()->create(['idempotency_key' => (string) Str::uuid(), 'requested_count' => 3, 'status' => BatchStatus::Pending]);

        $this->actingAs($this->admin())->postJson(route('batches.retry', $batch))->assertStatus(202);

        $batch->refresh();
        $this->assertSame(BatchStatus::Completed, $batch->status);
        $this->assertSame(BatchStatus::Completed, $batch->export_status);
        $this->assertSame(3, Voucher::count());

        $this->postJson(route('batches.retry', $batch))->assertUnprocessable();
    }

    /**
     * A completed batch with codes issued and its export queued but not run.
     */
    private function batchAwaitingExport(): GenerationBatch
    {
        Bus::fake([ExportVoucherBatch::class]);
        $this->generate($this->campaign(), 2);

        return GenerationBatch::firstOrFail();
    }

    public function test_duplicate_export_jobs_run_only_once(): void
    {
        $batch = $this->batchAwaitingExport();
        $first = new ExportVoucherBatch($batch->id);
        $second = new ExportVoucherBatch($batch->id);

        $first->handle(app(VoucherRenderer::class));
        $path = $batch->fresh()->export_path;
        $second->handle(app(VoucherRenderer::class));

        $batch->refresh();
        $this->assertSame(BatchStatus::Completed, $batch->export_status);
        $this->assertSame($path, $batch->export_path);
        $this->assertSame(2, $batch->exported_count);
        $this->assertCount(1, Storage::disk('local')->files('exports'));
    }

    public function test_running_export_cannot_be_started_again(): void
    {
        $batch = $this->batchAwaitingExport();
        $batch->update(['export_status' => BatchStatus::Processing, 'export_claim' => 'live-job']);
        $this->actingAs($this->admin());

        $this->postJson(route('batches.export', $batch), ['export_format' => 'jpg', 'export_scale' => 100])->assertUnprocessable();
        $this->postJson(route('batches.retry', $batch))->assertUnprocessable();

        $this->assertSame('live-job', $batch->fresh()->export_claim);
    }

    public function test_stale_export_is_taken_over_and_old_job_cannot_overwrite(): void
    {
        // Real (sync) pipeline, then rewind to "export hung half-way".
        $this->generate($this->campaign(), 2);
        $batch = GenerationBatch::firstOrFail();
        $stale = new ExportVoucherBatch($batch->id);
        $stale->claim = 'dead-job';
        DB::table('generation_batches')->where('id', $batch->id)->update([
            'export_status' => 'processing',
            'export_claim' => 'dead-job',
            'updated_at' => now()->subSeconds(GenerationBatch::EXPORT_STALE_AFTER_SECONDS + 60),
        ]);

        $this->actingAs($this->admin())->postJson(route('batches.retry', $batch))->assertStatus(202);

        $this->assertSame(BatchStatus::Completed, $batch->fresh()->export_status);

        // The dead job finally reports failure: it no longer owns the export.
        $stale->failed(new \RuntimeException('late failure'));
        $this->assertSame(BatchStatus::Completed, $batch->fresh()->export_status);
        $this->assertNull($batch->fresh()->export_error);
    }

    public function test_campaign_pages_render(): void
    {
        $campaign = $this->campaign();
        $this->generate($campaign, 2);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/campaigns')->assertOk()->assertSee($campaign->name);
        $this->get('/campaigns/create')->assertOk();
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Batch #')
            // Progress is exposed to assistive tech, not only drawn as a bar.
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-label="Kemajuan pembuatan kode voucher"', false)
            ->assertSee('aria-valuemin="0"', false)
            // Re-export locks its button while the request is in flight.
            ->assertSee(":disabled=\"sending\"", false)
            ->assertSee('min-h-11', false);
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee('sudah menerbitkan voucher');
        $this->get('/vouchers')->assertOk();
        $this->get(route('vouchers.show', Voucher::first()))->assertOk();
    }
}
