<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_admin_can_manage_outlets(): void
    {
        $user = User::factory()->role(Role::VoucherAdmin)->create();
        $brand = Brand::factory()->create();

        $this->actingAs($user)->post('/outlets', [
            'brand_id' => $brand->id,
            'name' => 'Kemang',
            'code' => 'bb-kmg-01',
            'city' => 'Jakarta',
            'is_active' => '1',
        ])->assertRedirect('/outlets');

        $outlet = Outlet::firstOrFail();
        $this->assertSame('BB-KMG-01', $outlet->code);

        $this->put("/outlets/{$outlet->id}", [
            'brand_id' => $brand->id,
            'name' => 'Kemang Raya',
            'code' => 'BB-KMG-01',
            'is_active' => '1',
        ])->assertRedirect('/outlets');
        $this->assertSame('Kemang Raya', $outlet->fresh()->name);

        $this->delete("/outlets/{$outlet->id}")->assertRedirect('/outlets');
        $this->assertModelMissing($outlet);
    }

    public function test_outlet_code_must_be_unique(): void
    {
        $user = User::factory()->role(Role::SuperAdmin)->create();
        $existing = Outlet::factory()->create(['code' => 'BB-01']);

        $this->actingAs($user)->post('/outlets', [
            'brand_id' => $existing->brand_id,
            'name' => 'Duplikat',
            'code' => 'bb-01',
        ])->assertSessionHasErrors('code');
    }

    public function test_outlet_staff_only_sees_assigned_outlets(): void
    {
        [$mine, $other] = Outlet::factory()->count(2)->create();
        $staff = User::factory()->role(Role::OutletStaff)->create();
        $staff->outlets()->attach($mine);

        $this->actingAs($staff)->get('/outlets')
            ->assertOk()
            ->assertSee($mine->code)
            ->assertDontSee($other->code);
    }

    public function test_viewer_sees_all_outlets_but_cannot_modify(): void
    {
        [$a, $b] = Outlet::factory()->count(2)->create();
        $viewer = User::factory()->role(Role::Viewer)->create();

        $this->actingAs($viewer)->get('/outlets')->assertOk()->assertSee($a->code)->assertSee($b->code);
        $this->get('/outlets/create')->assertForbidden();
        $this->get("/outlets/{$a->id}/edit")->assertForbidden();
        $this->delete("/outlets/{$a->id}")->assertForbidden();
    }

    public function test_outlet_staff_cannot_modify_even_assigned_outlet(): void
    {
        $outlet = Outlet::factory()->create();
        $staff = User::factory()->role(Role::OutletStaff)->create();
        $staff->outlets()->attach($outlet);

        $this->actingAs($staff)->get("/outlets/{$outlet->id}/edit")->assertForbidden();
        $this->delete("/outlets/{$outlet->id}")->assertForbidden();
    }
}
