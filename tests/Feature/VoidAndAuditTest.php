<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VoucherStatus;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesVouchers;
use Tests\TestCase;

class VoidAndAuditTest extends TestCase
{
    use CreatesVouchers, RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::VoucherAdmin)->create();
    }

    public function test_admin_voids_issued_voucher_with_reason(): void
    {
        $voucher = $this->voucherFor($this->activeCampaign());
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('vouchers.void', $voucher), ['void_reason' => 'Dicetak ganda'])
            ->assertSessionHas('status');

        $voucher->refresh();
        $this->assertSame(VoucherStatus::Voided, $voucher->status);
        $this->assertSame('Dicetak ganda', $voucher->void_reason);
        $this->assertSame($admin->id, $voucher->voided_by);
        $this->assertSame(1, ActivityLog::where('action', 'voucher.voided')->count());
    }

    public function test_void_requires_a_reason(): void
    {
        $voucher = $this->voucherFor($this->activeCampaign());

        $this->actingAs($this->admin())
            ->post(route('vouchers.void', $voucher), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->assertSame(VoucherStatus::Issued, $voucher->fresh()->status);
    }

    public function test_voided_voucher_cannot_be_voided_again(): void
    {
        $voucher = $this->voucherFor($this->activeCampaign(), ['status' => 'voided']);
        $voucher->forceFill(['void_reason' => 'Sudah batal'])->save();

        $this->actingAs($this->admin())
            ->post(route('vouchers.void', $voucher), ['void_reason' => 'Coba batalkan lagi'])
            ->assertSessionHasErrors(['voucher' => 'Voucher sudah dibatalkan.']);

        $this->assertSame('Sudah batal', $voucher->fresh()->void_reason);
        $this->assertSame(0, ActivityLog::where('action', 'voucher.voided')->count());
    }

    public function test_expired_unused_voucher_can_still_be_voided(): void
    {
        $voucher = $this->voucherFor($this->activeCampaign(), ['valid_until' => now()->subDay()]);

        $this->actingAs($this->admin())->post(route('vouchers.void', $voucher), ['void_reason' => 'Bersih-bersih']);

        $this->assertSame(VoucherStatus::Voided, $voucher->fresh()->status);
    }

    public static function nonManagers(): array
    {
        return [[Role::OutletStaff], [Role::Viewer]];
    }

    #[DataProvider('nonManagers')]
    public function test_only_managers_can_void(Role $role): void
    {
        $voucher = $this->voucherFor($this->activeCampaign());

        $this->actingAs(User::factory()->role($role)->create())
            ->post(route('vouchers.void', $voucher), ['void_reason' => 'Tidak berhak'])
            ->assertForbidden();
    }

    public function test_login_and_logout_are_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout');

        $this->assertSame(['auth.login', 'auth.logout'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame($user->id, ActivityLog::first()->actor_id);
    }

    public function test_password_changes_never_reach_the_log(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'role' => Role::Viewer->value,
            'is_active' => '1',
            'password' => 'Rahasia-Baru-123',
            'password_confirmation' => 'Rahasia-Baru-123',
        ])->assertRedirect('/users');

        $log = ActivityLog::where('action', 'user.updated')->sole();
        $this->assertSame('Nama Baru', $log->payload_json['changes']['name']);
        $this->assertArrayNotHasKey('password', $log->payload_json['changes']);
        $this->assertStringNotContainsString('Rahasia-Baru-123', json_encode($log->payload_json));
    }

    public function test_master_data_changes_are_logged_with_actor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/brands', ['name' => 'Warung Nini', 'code_prefix' => 'WN-VM']);

        $log = ActivityLog::where('action', 'brand.created')->sole();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(Brand::class, $log->subject_type);
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_code_mask_keeps_codes_unusable(): void
    {
        $this->assertSame('WN-VM…K7Q', Audit::maskCode('WN-VM08100005K7Q'));
        $this->assertSame('••••••', Audit::maskCode('SHORT1'));
    }

    public function test_activity_page_is_super_admin_only(): void
    {
        Audit::record('test.event');

        $this->actingAs($this->admin())->get(route('activity.index'))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())
            ->get(route('activity.index'))->assertOk()->assertSee('test.event');
    }
}
