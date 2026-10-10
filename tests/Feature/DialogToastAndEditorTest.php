<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\User;
use App\Models\VoucherTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DialogToastAndEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_uses_the_native_confirm_any_more(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => preg_match('/(?<![.\w])confirm\(/', $file->getContents()))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()->all();

        $this->assertSame([], $offenders);
    }

    public function test_layout_renders_one_confirm_dialog_and_the_toast_region(): void
    {
        $html = $this->actingAs(User::factory()->role(Role::VoucherAdmin)->create())->get(route('brands.index'))->getContent();

        $this->assertSame(1, substr_count($html, '<dialog id="confirm-dialog"'));
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    public function test_delete_button_asks_through_the_dialog(): void
    {
        Brand::factory()->create(['name' => 'Brand Uji']);

        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())->get(route('brands.index'))
            ->assertOk()
            ->assertSee('$store.confirm.ask(', false)
            ->assertSee('ok && $el.submit()', false);
    }

    public function test_flash_status_becomes_a_toast(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();

        $this->actingAs($admin)->post(route('brands.store'), ['name' => 'Bebek Baru', 'code_prefix' => 'BBR', 'is_active' => '1'])
            ->assertRedirect();

        $this->actingAs($admin)->get(route('brands.index'))
            ->assertSee('$store.toasts.push(', false)
            ->assertSee('Brand', false);
    }

    public function test_editor_groups_slot_chips_and_offers_auto_render(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('templates/test.jpg', UploadedFile::fake()->image('t.jpg', 800, 375)->getContent());
        $template = VoucherTemplate::factory()->create(['image_width' => 800, 'image_height' => 375]);

        $this->actingAs(User::factory()->role(Role::VoucherAdmin)->create())->get(route('templates.editor', $template))
            ->assertOk()
            ->assertSee('x-for="group in unusedGroups"', false)
            ->assertSee('Isi voucher')
            ->assertSee('x-model="autoPreview"', false)
            ->assertSee('guideStyle(guide)', false)
            // Preview and save/upload report errors separately.
            ->assertSee('x-for="error in previewErrors"', false)
            ->assertSee('x-for="error in errors"', false);
    }

    public function test_every_field_belongs_to_a_known_group(): void
    {
        $groups = array_keys(config('voucher.field_groups'));

        foreach (config('voucher.fields') as $key => $field) {
            $this->assertContains($field['group'] ?? null, $groups, $key);
        }
    }
}
