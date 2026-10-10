<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\ExportVoucherBatch;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTemplate;
use App\Services\Voucher\VoucherContent;
use App\Services\Voucher\VoucherRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesVouchers;
use Tests\TestCase;

class StructuredVoucherContentTest extends TestCase
{
    use CreatesVouchers;
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

    private function template(array $keys = ['voucher_code']): VoucherTemplate
    {
        $fields = array_map(fn (string $key, int $i) => [
            'key' => $key, 'type' => 'text', 'x' => 0.05, 'y' => 0.05 + $i * 0.12, 'width' => 0.9, 'height' => 0.1,
            'font_family' => 'poppins', 'font_weight' => 'bold', 'font_size' => 24, 'color' => '#E30613', // fake template image is black
            'align' => 'left', 'vertical_align' => 'middle',
        ], $keys, array_keys($keys));

        return VoucherTemplate::factory()->create([
            'image_width' => 800, 'image_height' => 375,
            'fields_json' => VoucherTemplate::wrapFields($fields),
        ]);
    }

    private function payload(VoucherTemplate $template, array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $template->brand_id,
            'voucher_template_id' => $template->id,
            'name' => 'Promo Oktober',
            'benefit_title' => 'GRATIS',
            'starts_at' => now()->subDay()->format('Y-m-d H:i'),
            'expires_at' => now()->addMonth()->format('Y-m-d H:i'),
            'code_prefix' => 'BB-VM',
            'code_preset' => 'example_random',
            'applies_to_all_outlets' => '1',
            'menu_items' => [
                ['name' => ' Bebek Goreng ', 'qty' => '1'],
                ['name' => '', 'qty' => '3'], // blank repeater row: dropped
                ['name' => 'Es Teh Manis', 'qty' => '2'],
            ],
            'min_purchase' => '100000',
            'normal_value' => '45000',
            'service_type' => 'dine_in',
            'valid_days' => ['1', '2', '3', '4', '5'],
            'valid_time_from' => '10:00',
            'valid_time_until' => '17:00',
        ], $overrides);
    }

    public function test_texts_format_menu_portions_money_and_schedule(): void
    {
        $texts = VoucherContent::texts([
            'menu_items' => [['name' => 'Bebek Goreng', 'qty' => 1], ['name' => 'Es Teh Manis', 'qty' => 2]],
            'min_purchase' => 100000,
            'normal_value' => 45000,
            'service_type' => 'takeaway',
            'valid_days' => [1, 2, 3, 4, 5],
            'valid_time_from' => '10:00',
            'valid_time_until' => '17:00',
        ]);

        $this->assertSame('1x Bebek Goreng · 2x Es Teh Manis', $texts['menu_items']);
        $this->assertSame('GRATIS 3 PORSI', $texts['free_portion']);
        $this->assertSame('Min. belanja Rp100.000', $texts['min_purchase']);
        $this->assertSame('Senilai Rp45.000', $texts['normal_value']);
        $this->assertSame('Khusus takeaway', $texts['service_type']);
        $this->assertSame('Sen–Jum · 10.00–17.00', $texts['valid_schedule']);
    }

    public function test_schedule_collapses_day_runs(): void
    {
        $this->assertSame('Sab, Min', VoucherContent::schedule([6, 7], null, null));
        $this->assertSame('Sen, Rab–Jum', VoucherContent::schedule([5, 1, 3, 4], null, null));
        $this->assertSame('', VoucherContent::schedule([1, 2, 3, 4, 5, 6, 7], null, null), 'every day prints nothing');
        $this->assertSame('08.00–11.00', VoucherContent::schedule(null, '08:00', '11:00'));
    }

    public function test_empty_content_prints_nothing(): void
    {
        $this->assertSame(array_fill_keys(VoucherContent::SLOTS, ''), VoucherContent::texts(null));
        $this->assertSame('', VoucherContent::texts(['service_type' => 'any'])['service_type']);
    }

    public function test_campaign_stores_structured_content(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin())->post(route('campaigns.store'), $this->payload($template))->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame([['name' => 'Bebek Goreng', 'qty' => 1], ['name' => 'Es Teh Manis', 'qty' => 2]], $campaign->menu_items);
        $this->assertSame(100000, $campaign->min_purchase);
        $this->assertSame(45000, $campaign->normal_value);
        $this->assertSame('dine_in', $campaign->service_type);
        $this->assertSame([1, 2, 3, 4, 5], $campaign->valid_days);
        $this->assertSame('10:00', $campaign->valid_time_from);
    }

    public function test_structured_content_is_optional(): void
    {
        $template = $this->template();
        $payload = $this->payload($template);
        foreach (['menu_items', 'min_purchase', 'normal_value', 'service_type', 'valid_days', 'valid_time_from', 'valid_time_until'] as $key) {
            unset($payload[$key]);
        }

        $this->actingAs($this->admin())->post(route('campaigns.store'), $payload)->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail()->fresh();
        $this->assertNull($campaign->menu_items);
        $this->assertSame('any', $campaign->service_type);
    }

    public function test_invalid_structured_content_is_rejected(): void
    {
        $template = $this->template();
        $admin = $this->admin();

        $cases = [
            'menu_items.0.qty' => ['menu_items' => [['name' => 'Bebek', 'qty' => '0']]],
            'menu_items' => ['menu_items' => array_fill(0, 11, ['name' => 'Bebek', 'qty' => '1'])],
            'service_type' => ['service_type' => 'delivery'],
            'valid_days.0' => ['valid_days' => ['8']],
            'valid_time_until' => ['valid_time_from' => '17:00', 'valid_time_until' => '10:00'],
            'min_purchase' => ['min_purchase' => '-5'],
        ];

        foreach ($cases as $field => $override) {
            $this->actingAs($admin)->post(route('campaigns.store'), $this->payload($template, $override))
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($admin)->post(route('campaigns.store'), $this->payload($template, ['valid_time_from' => '10:00', 'valid_time_until' => null]))
            ->assertSessionHasErrors('valid_time_until');

        $this->assertSame(0, Campaign::count());
    }

    public function test_generated_vouchers_snapshot_content_and_ignore_later_edits(): void
    {
        Bus::fake([ExportVoucherBatch::class]);
        $template = $this->template();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('campaigns.store'), $this->payload($template));
        $campaign = Campaign::firstOrFail();
        $campaign->update(['status' => 'active']);

        $this->actingAs($admin)->postJson(route('campaigns.batches.store', $campaign), [
            'quantity' => 3, 'idempotency_key' => (string) Str::uuid(), 'export_format' => 'jpg', 'export_scale' => 100,
        ])->assertStatus(202);

        $campaign->update(['menu_items' => [['name' => 'Ayam', 'qty' => 5]], 'service_type' => 'takeaway']);

        $voucher = Voucher::firstOrFail();
        $this->assertSame([['name' => 'Bebek Goreng', 'qty' => 1], ['name' => 'Es Teh Manis', 'qty' => 2]], $voucher->content_snapshot['menu_items']);
        $values = $voucher->renderValues(null);
        $this->assertSame('1x Bebek Goreng · 2x Es Teh Manis', $values['menu_items']);
        $this->assertSame('GRATIS 3 PORSI', $values['free_portion']);
        $this->assertSame('Khusus dine-in', $values['service_type']);
    }

    public function test_legacy_voucher_without_snapshot_renders_identically(): void
    {
        // A template that only maps the original slots, like every template
        // in use before structured content existed.
        $template = $this->template(['voucher_code', 'benefit_title', 'outlet_scope_text']);
        $campaign = Campaign::factory()->create(['brand_id' => $template->brand_id, 'voucher_template_id' => $template->id]);
        $legacy = $this->voucherFor($campaign);
        $this->assertNull($legacy->content_snapshot);

        $renderer = app(VoucherRenderer::class);
        $before = $renderer->encode($legacy, 'png');

        // Same voucher with content: slots not on the template change nothing.
        $legacy->content_snapshot = ['menu_items' => [['name' => 'Bebek', 'qty' => 2]], 'service_type' => 'dine_in'];
        $this->assertSame($before, $renderer->encode($legacy, 'png'));
        $this->assertSame('', VoucherContent::texts(null)['free_portion']);
    }

    public function test_new_slots_render_onto_the_voucher(): void
    {
        $template = $this->template(['voucher_code', 'free_portion', 'menu_items']);
        $campaign = Campaign::factory()->create(['brand_id' => $template->brand_id, 'voucher_template_id' => $template->id]);

        $plain = $this->voucherFor($campaign);
        $withContent = $this->voucherFor($campaign, ['code' => $plain->code.'X', 'content_snapshot' => ['menu_items' => [['name' => 'Bebek Goreng', 'qty' => 3]]]]);
        $plain->code = $withContent->code; // same code: only the content differs

        $renderer = app(VoucherRenderer::class);
        $this->assertNotSame($renderer->encode($plain, 'png'), $renderer->encode($withContent, 'png'));
    }

    public function test_detail_shows_menu_content(): void
    {
        $campaign = $this->activeCampaign(['status' => 'active']);
        $voucher = $this->voucherFor($campaign, ['content_snapshot' => ['menu_items' => [['name' => 'Bebek Goreng', 'qty' => 2]], 'service_type' => 'dine_in']]);

        $this->actingAs($this->admin())->get(route('vouchers.show', $voucher))
            ->assertOk()->assertSee('2x Bebek Goreng')->assertSee('GRATIS 2 PORSI')->assertSee('Khusus dine-in');
    }

    public function test_preview_renders_unsaved_form_values_for_managers_only(): void
    {
        $template = $this->template(['voucher_code', 'free_portion']);
        $payload = $this->payload($template, ['code_example' => 'BB-VM0810ABC']);

        $response = $this->actingAs($this->admin())->post(route('campaigns.preview'), $payload);
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertGreaterThan(1000, strlen($response->getContent()));

        $this->actingAs(User::factory()->role(Role::OutletStaff)->create())
            ->post(route('campaigns.preview'), $payload)->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Viewer)->create())
            ->post(route('campaigns.preview'), $payload)->assertForbidden();
    }

    public function test_preview_tolerates_half_filled_and_hostile_input(): void
    {
        $template = $this->template(['voucher_code', 'menu_items', 'valid_schedule']);

        $this->actingAs($this->admin())->post(route('campaigns.preview'), [
            'voucher_template_id' => $template->id,
            'menu_items' => [['name' => str_repeat('A', 500), 'qty' => '9999'], 'not-an-array', ['qty' => '2']],
            'valid_days' => ['x', '9', '3'],
            'valid_time_from' => '99:99',
            'min_purchase' => 'abc',
            'service_type' => '<script>',
        ])->assertOk();

        $content = VoucherContent::fromInput([
            'menu_items' => [['name' => str_repeat('A', 500), 'qty' => '9999']],
            'valid_days' => ['x', '9', '3'],
            'valid_time_from' => '99:99',
            'service_type' => '<script>',
        ]);
        $this->assertSame(60, mb_strlen($content['menu_items'][0]['name']));
        $this->assertSame(99, $content['menu_items'][0]['qty']);
        $this->assertSame([3], $content['valid_days']);
        $this->assertNull($content['valid_time_from']);
        $this->assertSame('any', $content['service_type']);
    }

    public function test_saved_campaign_preview_opens_full_size_in_its_own_tab(): void
    {
        $template = $this->template(['voucher_code', 'free_portion']);
        $campaign = Campaign::factory()->create([
            'brand_id' => $template->brand_id, 'voucher_template_id' => $template->id,
            'menu_items' => [['name' => 'Bebek', 'qty' => 2]],
        ]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('campaigns.saved-preview', $campaign));
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([800, 375], array_slice(getimagesizefromstring($response->getContent()), 0, 2), 'native size, not scaled down');

        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('href="'.route('campaigns.saved-preview', $campaign).'" target="_blank" rel="noopener"', false);

        foreach ([Role::OutletStaff, Role::Viewer] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('campaigns.saved-preview', $campaign))->assertForbidden();
        }
    }

    public function test_form_preview_links_to_a_new_tab(): void
    {
        $this->actingAs($this->admin())->get(route('campaigns.create'))->assertOk()
            ->assertSee(':href="src" target="_blank" rel="noopener"', false)
            ->assertSee('Buka di tab baru');
    }

    public function test_content_slots_seeder_adds_a_new_version_once(): void
    {
        $brand = \App\Models\Brand::factory()->create(['slug' => 'bts']);
        $v1 = VoucherTemplate::factory()->create(['brand_id' => $brand->id, 'name' => 'Voucher Makan FREE', 'version' => 1]);
        $original = $v1->fields_json;

        $this->seed(\Database\Seeders\TemplateContentSlotsSeeder::class);
        $this->seed(\Database\Seeders\TemplateContentSlotsSeeder::class);

        $versions = VoucherTemplate::where('brand_id', $brand->id)->orderBy('version')->get();
        $this->assertCount(2, $versions, 'second run is a no-op');
        $this->assertSame($original, $versions[0]->fresh()->fields_json, 'v1 untouched (may be locked)');
        $keys = array_column($versions[1]->fields(), 'key');
        $this->assertContains('menu_items', $keys);
        $this->assertContains('valid_schedule', $keys);
        $this->assertSame($v1->image_path, $versions[1]->image_path);
    }

    public function test_form_knows_which_inputs_each_template_prints(): void
    {
        $template = $this->template(['voucher_code', 'menu_items']);

        $this->actingAs($this->admin())->get(route('campaigns.create'))->assertOk()
            ->assertSee('Belum tampil di gambar')
            // @js (Js::from) escapes double quotes as unicode escapes and slashes as \\\/.
            ->assertSee(str_replace('"', '\\'.'u0022', '"slots":["voucher_code","menu_items"]'), false)
            ->assertSee(str_replace('/', '\\\\\\/', route('templates.editor', $template)), false);
    }

    public function test_preview_needs_a_template(): void
    {
        $this->actingAs($this->admin())->postJson(route('campaigns.preview'), [])->assertUnprocessable();
    }

    public function test_campaign_form_and_page_render_the_new_sections(): void
    {
        $admin = $this->admin();
        $template = $this->template();
        $this->actingAs($admin)->post(route('campaigns.store'), $this->payload($template));
        $campaign = Campaign::firstOrFail();

        $this->actingAs($admin)->get(route('campaigns.create'))->assertOk()->assertSee('Menu & porsi gratis')->assertSee('Hari berlaku');
        $this->actingAs($admin)->get(route('campaigns.edit', $campaign))->assertOk()->assertSee('Bebek Goreng');
        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Bebek Goreng')->assertSee('GRATIS 3 PORSI')->assertSee('Sen–Jum · 10.00–17.00');
        $this->actingAs($admin)->get(route('campaigns.index'))->assertOk()->assertSee('GRATIS 3 PORSI');
    }
}
