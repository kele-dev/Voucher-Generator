<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Outlet;
use App\Models\User;
use App\Models\VoucherTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('templates/test.jpg', UploadedFile::fake()->image('t.jpg', 1600, 750)->getContent());
    }

    private function admin(): User
    {
        return User::factory()->role(Role::VoucherAdmin)->create();
    }

    private function payload(VoucherTemplate $template, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $template->brand_id,
            'voucher_template_id' => $template->id,
            'name' => 'Promo Oktober',
            'benefit_title' => '1 PORSI',
            'benefit_description' => 'NASI BEBEK + ES TEH',
            'starts_at' => '2026-10-01 00:00',
            'expires_at' => '2026-10-31 23:59',
            'code_prefix' => 'bb-vm',
            'code_preset' => 'example_random',
            'applies_to_all_outlets' => '1',
        ], $overrides);
    }

    /**
     * Issue one voucher for a campaign directly, without the queue.
     */
    private function issueVoucher(Campaign $campaign): void
    {
        $campaign->vouchers()->create([
            'code' => 'BB-VM-TEST'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'issued',
            'voucher_template_id' => $campaign->voucher_template_id,
            'valid_from' => $campaign->starts_at,
            'valid_until' => $campaign->expires_at,
            'benefit_title' => $campaign->benefit_title,
            'issued_at' => now(),
        ]);
    }

    public function test_admin_creates_draft_campaign_with_preset_pattern(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())->post('/campaigns', $this->payload($template))
            ->assertRedirect();

        $campaign = Campaign::firstOrFail();
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame('BB-VM', $campaign->code_prefix);
        $this->assertSame(config('voucher.codes.presets.example_random.pattern'), $campaign->code_pattern);
    }

    public function test_custom_pattern_is_validated(): void
    {
        $template = VoucherTemplate::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/campaigns', $this->payload($template, ['code_preset' => 'custom', 'code_pattern' => '{PREFIX}{DDMM}']))
            ->assertSessionHasErrors('code_pattern');

        $this->actingAs($admin)
            ->post('/campaigns', $this->payload($template, ['code_preset' => 'custom', 'code_pattern' => '{prefix}-{rand:6}']))
            ->assertRedirect();

        $this->assertSame('{PREFIX}-{RAND:6}', Campaign::firstOrFail()->code_pattern);
    }

    public function test_overlong_codes_are_rejected(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->post('/campaigns', $this->payload($template, [
                'code_prefix' => 'ABCDEFGHIJ-KLMNOPQRS',
                'code_preset' => 'custom',
                'code_pattern' => '{PREFIX}-{DATE:Ymd}-{RAND:16}',
            ]))
            ->assertSessionHasErrors('code_pattern');
    }

    public static function invalidPayloads(): array
    {
        return [
            'expires before start' => [['expires_at' => '2026-09-01 00:00'], 'expires_at'],
            'bad prefix' => [['code_prefix' => 'BB VM'], 'code_prefix'],
            'unknown preset' => [['code_preset' => 'nope'], 'code_preset'],
            'no outlets when not all' => [['applies_to_all_outlets' => '0'], 'outlet_ids'],
            'zero limit' => [['generation_limit' => 0], 'generation_limit'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_campaigns_are_rejected(array $override, string $field): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->post('/campaigns', $this->payload($template, $override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('voucher_campaigns', 0);
    }

    public function test_template_and_outlets_must_belong_to_campaign_brand(): void
    {
        $template = VoucherTemplate::factory()->create();
        $otherTemplate = VoucherTemplate::factory()->create();
        $otherOutlet = Outlet::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/campaigns', $this->payload($template, ['voucher_template_id' => $otherTemplate->id]))
            ->assertSessionHasErrors('voucher_template_id');

        $this->actingAs($admin)
            ->post('/campaigns', $this->payload($template, ['applies_to_all_outlets' => '0', 'outlet_ids' => [$otherOutlet->id]]))
            ->assertSessionHasErrors('outlet_ids');
    }

    public function test_status_transitions_follow_lifecycle(): void
    {
        $campaign = Campaign::factory()->status(CampaignStatus::Draft)->create();
        $this->actingAs($this->admin());

        $this->patch(route('campaigns.status', $campaign), ['status' => 'paused'])->assertSessionHasErrors('campaign');
        $this->patch(route('campaigns.status', $campaign), ['status' => 'active']);
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);

        $this->patch(route('campaigns.status', $campaign), ['status' => 'closed']);
        $this->patch(route('campaigns.status', $campaign), ['status' => 'active'])->assertSessionHasErrors('campaign');
        $this->assertSame(CampaignStatus::Closed, $campaign->fresh()->status);
    }

    public function test_brand_and_template_are_frozen_once_vouchers_exist(): void
    {
        $campaign = Campaign::factory()->create();
        $this->issueVoucher($campaign);
        $newTemplate = VoucherTemplate::factory()->create(['brand_id' => $campaign->brand_id]);

        $this->actingAs($this->admin())
            ->put(route('campaigns.update', $campaign), $this->payload($campaign->template, [
                'voucher_template_id' => $newTemplate->id,
            ]))
            ->assertSessionHasErrors('voucher_template_id');
    }

    public function test_campaign_with_vouchers_cannot_be_deleted(): void
    {
        $campaign = Campaign::factory()->create();
        $this->issueVoucher($campaign);

        $this->actingAs($this->admin())->delete(route('campaigns.destroy', $campaign))->assertForbidden();
        $this->assertModelExists($campaign);
    }

    public function test_template_used_by_issued_voucher_becomes_locked(): void
    {
        $campaign = Campaign::factory()->create();
        $template = $campaign->template;
        $admin = $this->admin();

        $this->assertFalse($template->isLocked());
        $this->issueVoucher($campaign);
        $this->assertTrue($template->fresh()->isLocked());

        $this->actingAs($admin)->putJson(route('templates.fields', $template), ['fields' => []])->assertForbidden();
        $this->actingAs($admin)->delete(route('templates.destroy', $template))->assertForbidden();

        // Superseding with a new version is still allowed.
        $this->actingAs($admin)->post(route('templates.versions', $template))->assertRedirect();
    }

    public function test_template_referenced_by_campaign_cannot_be_deleted(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('templates.destroy', $campaign->template))
            ->assertSessionHasErrors('template');

        $this->assertModelExists($campaign->template);
    }

    public function test_brand_with_campaigns_cannot_be_deleted(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())
            ->delete("/brands/{$campaign->brand_id}")
            ->assertSessionHasErrors('brand');
    }

    public function test_outlet_staff_only_see_campaigns_for_their_outlets(): void
    {
        $outlet = Outlet::factory()->create();
        $staff = User::factory()->role(Role::OutletStaff)->create();
        $staff->outlets()->attach($outlet);

        $allOutlets = Campaign::factory()->create(['brand_id' => $outlet->brand_id, 'name' => 'Semua cabang']);
        $mine = Campaign::factory()->create(['brand_id' => $outlet->brand_id, 'applies_to_all_outlets' => false, 'name' => 'Cabang saya']);
        $mine->outlets()->attach($outlet);
        $otherBrand = Campaign::factory()->create(['name' => 'Brand lain']);

        $this->actingAs($staff)->get('/campaigns')
            ->assertOk()
            ->assertSee('Semua cabang')
            ->assertSee('Cabang saya')
            ->assertDontSee('Brand lain');

        $this->get(route('campaigns.show', $allOutlets))->assertOk();
        $this->get(route('campaigns.show', $otherBrand))->assertForbidden();
    }

    public function test_read_only_roles_cannot_manage_campaigns(): void
    {
        $campaign = Campaign::factory()->create();
        $this->actingAs(User::factory()->role(Role::Viewer)->create());

        $this->get(route('campaigns.show', $campaign))->assertOk();
        $this->get('/campaigns/create')->assertForbidden();
        $this->get(route('campaigns.edit', $campaign))->assertForbidden();
        $this->patch(route('campaigns.status', $campaign), ['status' => 'paused'])->assertForbidden();
        $this->delete(route('campaigns.destroy', $campaign))->assertForbidden();
    }

    public function test_replaced_product_image_kept_while_vouchers_reference_it(): void
    {
        $template = VoucherTemplate::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/campaigns', $this->payload($template, [
            'product_image' => UploadedFile::fake()->image('a.png', 400, 400),
        ]));
        $campaign = Campaign::firstOrFail();
        $first = $campaign->product_image_path;

        $campaign->vouchers()->create([
            'code' => 'BB-VM-KEEP', 'status' => 'issued', 'voucher_template_id' => $template->id,
            'valid_from' => now(), 'valid_until' => now()->addDay(), 'benefit_title' => 'X',
            'product_image_path' => $first, 'issued_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('campaigns.update', $campaign), $this->payload($template, [
            'product_image' => UploadedFile::fake()->image('b.png', 400, 400),
        ]))->assertRedirect();

        $this->assertNotSame($first, $campaign->fresh()->product_image_path);
        Storage::disk('local')->assertExists($first);
    }

    public function test_can_create_campaign_with_nominal_voucher_kind(): void
    {
        $template = VoucherTemplate::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/campaigns', $this->payload($template, [
            'voucher_kind' => 'nominal',
            'voucher_type' => 'VM',
        ]));

        $response->assertRedirect();
        $campaign = Campaign::latest('id')->firstOrFail();
        $this->assertSame(\App\Enums\VoucherKind::Nominal, $campaign->voucher_kind);
    }

    public function test_can_create_campaign_with_electronic_voucher_kind(): void
    {
        $template = VoucherTemplate::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/campaigns', $this->payload($template, [
            'voucher_kind' => 'electronic',
            'voucher_type' => 'VM',
        ]));

        $response->assertRedirect();
        $campaign = Campaign::latest('id')->firstOrFail();
        $this->assertSame(\App\Enums\VoucherKind::Electronic, $campaign->voucher_kind);
    }

    public function test_can_create_campaign_with_electronic_nominal_voucher_kind(): void
    {
        $template = VoucherTemplate::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/campaigns', $this->payload($template, [
            'voucher_kind' => 'electronic_nominal',
            'voucher_type' => 'VM',
        ]));

        $response->assertRedirect();
        $campaign = Campaign::latest('id')->firstOrFail();
        $this->assertSame(\App\Enums\VoucherKind::ElectronicNominal, $campaign->voucher_kind);
    }
}
