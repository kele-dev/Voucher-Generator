<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia-Kuat-123';

    public static function nonSuperAdminRoles(): array
    {
        return [[Role::VoucherAdmin], [Role::OutletStaff], [Role::Viewer]];
    }

    public function test_super_admin_can_create_outlet_staff_with_outlets(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $outlet = Outlet::factory()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Kasir Kemang',
            'email' => 'Kasir@Example.com',
            'role' => Role::OutletStaff->value,
            'is_active' => '1',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'outlet_ids' => [$outlet->id],
        ])->assertRedirect('/users');

        $user = User::where('email', 'kasir@example.com')->firstOrFail();
        $this->assertSame(Role::OutletStaff, $user->role);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertTrue($user->outlets->contains($outlet));
    }

    public function test_outlet_staff_requires_at_least_one_outlet(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Kasir',
            'email' => 'kasir@example.com',
            'role' => Role::OutletStaff->value,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertSessionHasErrors('outlet_ids');
    }

    public function test_changing_role_away_from_staff_clears_outlets(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $staff = User::factory()->role(Role::OutletStaff)->create();
        $staff->outlets()->attach(Outlet::factory()->create());

        $this->actingAs($admin)->put("/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => Role::Viewer->value,
            'is_active' => '1',
        ])->assertRedirect('/users');

        $this->assertSame(Role::Viewer, $staff->fresh()->role);
        $this->assertCount(0, $staff->fresh()->outlets);
    }

    public function test_update_without_password_keeps_existing_password(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'role' => Role::Viewer->value,
            'is_active' => '1',
            'password' => '',
        ])->assertRedirect('/users');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_super_admin_cannot_demote_deactivate_or_delete_self(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => Role::Viewer->value,
        ])->assertSessionHasErrors(['role', 'is_active']);

        $this->delete("/users/{$admin->id}")->assertForbidden();

        $this->assertSame(Role::SuperAdmin, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_other_roles_cannot_manage_users(Role $role): void
    {
        $user = User::factory()->role($role)->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->get("/users/{$other->id}/edit")->assertForbidden();
        $this->delete("/users/{$other->id}")->assertForbidden();
    }

    public function test_dashboard_hides_user_count_from_non_super_admin(): void
    {
        $this->actingAs(User::factory()->role(Role::Viewer)->create())
            ->get('/dashboard')->assertOk()->assertDontSee('User aktif');

        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())
            ->get('/dashboard')->assertOk()->assertSee('User aktif');
    }
}
