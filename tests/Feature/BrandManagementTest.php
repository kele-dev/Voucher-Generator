<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function managerRoles(): array
    {
        return [[Role::SuperAdmin], [Role::VoucherAdmin]];
    }

    public static function readOnlyRoles(): array
    {
        return [[Role::OutletStaff], [Role::Viewer]];
    }

    #[DataProvider('managerRoles')]
    public function test_managers_can_create_update_and_delete_brands(Role $role): void
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->post('/brands', [
            'name' => 'Bebek Boedjang',
            'code_prefix' => 'bb-vm',
            'is_active' => '1',
        ])->assertRedirect('/brands');

        $brand = Brand::firstOrFail();
        $this->assertSame('bebek-boedjang', $brand->slug);
        $this->assertSame('BB-VM', $brand->code_prefix);

        $this->put("/brands/{$brand->id}", [
            'name' => 'Bebek Boedjang Baru',
            'slug' => 'bebek-boedjang',
            'code_prefix' => 'BB',
        ])->assertRedirect('/brands');

        $brand->refresh();
        $this->assertSame('Bebek Boedjang Baru', $brand->name);
        $this->assertFalse($brand->is_active);

        $this->delete("/brands/{$brand->id}")->assertRedirect('/brands');
        $this->assertModelMissing($brand);
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_can_list_but_not_modify_brands(Role $role): void
    {
        $user = User::factory()->role($role)->create();
        $brand = Brand::factory()->create();

        $this->actingAs($user)->get('/brands')->assertOk()->assertSee($brand->name);
        $this->get('/brands/create')->assertForbidden();
        $this->post('/brands', ['name' => 'X', 'code_prefix' => 'X'])->assertForbidden();
        $this->get("/brands/{$brand->id}/edit")->assertForbidden();
        $this->put("/brands/{$brand->id}", ['name' => 'X', 'code_prefix' => 'X'])->assertForbidden();
        $this->delete("/brands/{$brand->id}")->assertForbidden();

        $this->assertModelExists($brand);
    }

    public function test_code_prefix_must_be_uppercase_alphanumeric_groups(): void
    {
        $user = User::factory()->role(Role::SuperAdmin)->create();

        foreach (['BB VM', 'BB--VM', '-BB', 'BB_VM', 'TOOLONGPREFIX'] as $prefix) {
            $this->actingAs($user)
                ->post('/brands', ['name' => 'Brand '.$prefix, 'code_prefix' => $prefix])
                ->assertSessionHasErrors('code_prefix');
        }

        $this->assertDatabaseCount('brands', 0);
    }

    public function test_slug_must_be_unique(): void
    {
        $user = User::factory()->role(Role::SuperAdmin)->create();
        Brand::factory()->create(['slug' => 'warung-nini']);

        $this->actingAs($user)
            ->post('/brands', ['name' => 'Warung Nini', 'code_prefix' => 'WN'])
            ->assertSessionHasErrors('slug');
    }

    public function test_brand_with_outlets_cannot_be_deleted(): void
    {
        $user = User::factory()->role(Role::SuperAdmin)->create();
        $outlet = Outlet::factory()->create();

        $this->actingAs($user)
            ->delete("/brands/{$outlet->brand_id}")
            ->assertSessionHasErrors('brand');

        $this->assertModelExists($outlet->brand);
    }

    public function test_manager_can_upload_four_voucher_templates_on_brand(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('voucher.templates.disk'));
        $user = User::factory()->role(Role::SuperAdmin)->create();
        $brand = Brand::factory()->create(['name' => 'Bebek Boedjang', 'code_prefix' => 'BB']);

        $physicalFile = \Illuminate\Http\UploadedFile::fake()->image('fisik.png', 800, 400);
        $electronicFile = \Illuminate\Http\UploadedFile::fake()->image('electronic.png', 800, 400);
        $nominalFile = \Illuminate\Http\UploadedFile::fake()->image('nominal.png', 800, 400);
        $electronicNominalFile = \Illuminate\Http\UploadedFile::fake()->image('electronic-nominal.png', 800, 400);

        $response = $this->actingAs($user)->put("/brands/{$brand->id}", [
            'name' => 'Bebek Boedjang',
            'slug' => 'bebek-boedjang',
            'code_prefix' => 'BB',
            'is_active' => '1',
            'template_physical' => $physicalFile,
            'template_electronic' => $electronicFile,
            'template_nominal' => $nominalFile,
            'template_electronic_nominal' => $electronicNominalFile,
        ]);

        $response->assertRedirect('/brands');

        $brand->refresh();
        $this->assertNotNull($brand->template_physical_path);
        $this->assertNotNull($brand->template_electronic_path);
        $this->assertNotNull($brand->template_nominal_path);
        $this->assertNotNull($brand->template_electronic_nominal_path);

        $disk = \Illuminate\Support\Facades\Storage::disk(config('voucher.templates.disk'));
        $disk->assertExists($brand->template_physical_path);
        $disk->assertExists($brand->template_electronic_path);
        $disk->assertExists($brand->template_nominal_path);
        $disk->assertExists($brand->template_electronic_nominal_path);

        // VoucherTemplates should be created/synced for the brand
        $this->assertDatabaseHas('voucher_templates', [
            'brand_id' => $brand->id,
            'name' => 'Voucher Fisik',
            'image_path' => $brand->template_physical_path,
        ]);
        $this->assertDatabaseHas('voucher_templates', [
            'brand_id' => $brand->id,
            'name' => 'e-Voucher Fisik',
            'image_path' => $brand->template_electronic_path,
        ]);
        $this->assertDatabaseHas('voucher_templates', [
            'brand_id' => $brand->id,
            'name' => 'Voucher Nominal',
            'image_path' => $brand->template_nominal_path,
        ]);
        $this->assertDatabaseHas('voucher_templates', [
            'brand_id' => $brand->id,
            'name' => 'e-Voucher Nominal',
            'image_path' => $brand->template_electronic_nominal_path,
        ]);

        // Stream route test
        $this->get("/brands/{$brand->id}/template-image/physical")->assertOk();
        $this->get("/brands/{$brand->id}/template-image/electronic")->assertOk();
        $this->get("/brands/{$brand->id}/template-image/nominal")->assertOk();
        $this->get("/brands/{$brand->id}/template-image/electronic_nominal")->assertOk();
    }
}
