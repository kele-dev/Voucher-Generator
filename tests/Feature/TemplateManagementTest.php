<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\User;
use App\Models\VoucherTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
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

    private function textField(array $overrides = []): array
    {
        return array_merge([
            'key' => 'voucher_code', 'type' => 'text',
            'x' => 0.7, 'y' => 0.9, 'width' => 0.25, 'height' => 0.08,
            'font_family' => 'poppins', 'font_weight' => 'bold', 'font_size' => 36,
            'color' => '#E30613', 'align' => 'center', 'vertical_align' => 'middle',
        ], $overrides);
    }

    public static function readOnlyRoles(): array
    {
        return [[Role::OutletStaff], [Role::Viewer]];
    }

    public function test_manager_can_upload_template(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->actingAs($this->admin())->post('/templates', [
            'brand_id' => $brand->id,
            'name' => 'Voucher Makan',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('voucher.jpg', 1600, 750),
        ]);

        $template = VoucherTemplate::firstOrFail();
        $response->assertRedirect(route('templates.editor', $template));

        $this->assertSame(1, $template->version);
        $this->assertSame([1600, 750], [$template->image_width, $template->image_height]);
        $this->assertSame([], $template->fields());
        Storage::disk('local')->assertExists($template->image_path);
    }

    public function test_upload_rejects_non_images_and_bad_dimensions(): void
    {
        $brand = Brand::factory()->create();
        $admin = $this->admin();

        $cases = [
            UploadedFile::fake()->create('voucher.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('tiny.jpg', 200, 100),
            UploadedFile::fake()->image('huge.jpg', 1600, 750)->size(config('voucher.templates.max_upload_kb') + 1),
        ];

        foreach ($cases as $file) {
            $this->actingAs($admin)
                ->post('/templates', ['brand_id' => $brand->id, 'name' => 'X', 'image' => $file])
                ->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('voucher_templates', 0);
    }

    public function test_template_image_requires_login(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->get(route('templates.image', $template))->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get(route('templates.image', $template))
            ->assertOk();
    }

    public function test_fields_are_saved_with_normalized_shape(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->putJson(route('templates.fields', $template), ['fields' => [
                ['key' => 'voucher_code', 'type' => 'text', 'x' => 0.7, 'y' => 0.9, 'width' => 0.25, 'height' => 0.08, 'junk' => 'dropped'],
                ['key' => 'product_image', 'type' => 'image', 'x' => 0.1, 'y' => 0.2, 'width' => 0.4, 'height' => 0.5, 'font_size' => 99],
            ]])
            ->assertOk();

        [$text, $image] = $template->fresh()->fields();

        $this->assertSame(1, $template->fresh()->fields_json['schema_version']);
        $this->assertArrayNotHasKey('junk', $text);
        $this->assertSame('poppins', $text['font_family']);
        $this->assertSame('center', $text['align']);
        $this->assertFalse($text['cover_enabled']);
        $this->assertSame('contain', $image['fit']);
        $this->assertArrayNotHasKey('font_size', $image);
    }

    public static function invalidFields(): array
    {
        return [
            'unknown key' => [['key' => 'hack']],
            'box past right edge' => [['x' => 0.9, 'width' => 0.2]],
            'box past bottom edge' => [['y' => 0.95, 'height' => 0.1]],
            'wrong type for key' => [['type' => 'image']],
            'bad color' => [['color' => 'red']],
            'unknown font' => [['font_family' => 'comic-sans']],
            'unknown weight' => [['font_weight' => 'black']],
            'font size too big' => [['font_size' => 1000]],
            'font size below renderer minimum' => [['font_size' => 6]],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_rejected(array $override): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->putJson(route('templates.fields', $template), ['fields' => [$this->textField($override)]])
            ->assertUnprocessable();

        $this->assertSame([], $template->fresh()->fields());
    }

    public function test_duplicate_field_keys_are_rejected(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->putJson(route('templates.fields', $template), ['fields' => [$this->textField(), $this->textField()]])
            ->assertUnprocessable();
    }

    public function test_preview_renders_jpeg_without_saving(): void
    {
        $template = VoucherTemplate::factory()->create();

        $response = $this->actingAs(User::factory()->role(Role::Viewer)->create())
            ->postJson(route('templates.preview', $template), ['fields' => [$this->textField()]]);

        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame("\xFF\xD8", substr($response->getContent(), 0, 2));
        $this->assertSame([], $template->fresh()->fields());
    }

    public function test_date_slot_is_stored_with_its_render_type_and_keeps_the_format(): void
    {
        $template = VoucherTemplate::factory()->create();

        // The editor translates a date slot into a text render type; the
        // semantic "date" type lives only in config.
        $this->actingAs($this->admin())
            ->putJson(route('templates.fields', $template), ['fields' => [
                $this->textField(['key' => 'expires_at', 'date_format' => 'd/m/Y']),
            ]])
            ->assertOk();

        $field = $template->fresh()->fields()[0];

        $this->assertSame('text', $field['type']);
        $this->assertSame('d/m/Y', $field['date_format']);
    }

    public function test_date_slot_preview_renders(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->postJson(route('templates.preview', $template), ['fields' => [
                $this->textField(['key' => 'expires_at']),
            ]])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_can_view_but_not_modify(Role $role): void
    {
        $template = VoucherTemplate::factory()->create();
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/templates')->assertOk();
        $this->get(route('templates.editor', $template))->assertOk();
        $this->get('/templates/create')->assertForbidden();
        $this->get(route('templates.edit', $template))->assertForbidden();
        $this->putJson(route('templates.fields', $template), ['fields' => []])->assertForbidden();
        $this->post(route('templates.versions', $template))->assertForbidden();
        $this->post(route('templates.sample-image.upload', $template), [
            'sample_image' => UploadedFile::fake()->image('p.png', 400, 400),
        ])->assertForbidden();
        $this->delete(route('templates.destroy', $template))->assertForbidden();
    }

    public function test_new_version_copies_fields_and_leaves_original_untouched(): void
    {
        $template = VoucherTemplate::factory()->create([
            'fields_json' => VoucherTemplate::wrapFields([$this->textField()]),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('templates.versions', $template))->assertRedirect();

        $copy = VoucherTemplate::where('version', 2)->firstOrFail();
        $this->assertSame($template->name, $copy->name);
        $this->assertSame($template->image_path, $copy->image_path);
        $this->assertEquals($template->fields(), $copy->fields());

        $this->putJson(route('templates.fields', $copy), ['fields' => []])->assertOk();

        $this->assertCount(1, $template->fresh()->fields());
        $this->assertSame([], $copy->fresh()->fields());
    }

    public function test_shared_image_is_kept_until_last_version_is_deleted(): void
    {
        $v1 = VoucherTemplate::factory()->create();
        $v2 = VoucherTemplate::factory()->create([
            'brand_id' => $v1->brand_id, 'name' => $v1->name, 'version' => 2, 'image_path' => $v1->image_path,
        ]);
        $this->actingAs($this->admin());

        $this->delete(route('templates.destroy', $v1))->assertRedirect('/templates');
        Storage::disk('local')->assertExists($v2->image_path);

        $this->delete(route('templates.destroy', $v2))->assertRedirect('/templates');
        Storage::disk('local')->assertMissing($v2->image_path);
    }

    public function test_sample_image_upload_is_stored_privately(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('templates.sample-image.upload', $template), [
                'sample_image' => UploadedFile::fake()->image('produk.png', 600, 600),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Gambar contoh diunggah.');

        $path = $template->fresh()->sample_image_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('templates.sample-image', $template))->assertOk();
    }

    public function test_oversized_sample_image_is_rejected(): void
    {
        $template = VoucherTemplate::factory()->create();
        $max = config('voucher.templates.overlay_max_width');

        $this->actingAs($this->admin())
            ->postJson(route('templates.sample-image.upload', $template), [
                'sample_image' => UploadedFile::fake()->image('besar.png', $max + 1, 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sample_image');

        $this->assertNull($template->fresh()->sample_image_path);
    }

    public function test_version_number_collision_is_retried_instead_of_failing(): void
    {
        $template = VoucherTemplate::factory()->create();
        $collided = false;

        // Simulate a concurrent request winning version 2 between our
        // max(version) read and our insert, on the first attempt only.
        VoucherTemplate::creating(function (VoucherTemplate $model) use ($template, &$collided) {
            if ($collided || $model->version !== 2) {
                return;
            }
            $collided = true;
            DB::table('voucher_templates')->insert([
                'brand_id' => $template->brand_id, 'name' => $template->name, 'version' => 2,
                'image_path' => $template->image_path, 'image_width' => 1600, 'image_height' => 750,
                'fields_json' => json_encode(VoucherTemplate::wrapFields([])), 'is_active' => true,
            ]);
        });

        $this->actingAs($this->admin())
            ->post(route('templates.versions', $template))
            ->assertRedirect();

        $this->assertTrue($collided);
        $this->assertSame(1, VoucherTemplate::where('name', $template->name)->where('version', 2)->count());
    }

    public function test_brand_with_templates_cannot_be_deleted(): void
    {
        $template = VoucherTemplate::factory()->create();

        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())
            ->delete("/brands/{$template->brand_id}")
            ->assertSessionHasErrors('brand');

        $this->assertModelExists($template->brand);
    }
}
