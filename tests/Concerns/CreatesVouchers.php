<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Str;

/**
 * Builds the usual voucher fixture: an active campaign, an outlet in its
 * brand, an outlet staff user assigned to that outlet, and issued vouchers.
 */
trait CreatesVouchers
{
    protected function activeCampaign(array $attributes = []): Campaign
    {
        return Campaign::factory()->create($attributes);
    }

    protected function outletFor(Campaign $campaign, array $attributes = []): Outlet
    {
        return Outlet::factory()->create(['brand_id' => $campaign->brand_id, ...$attributes]);
    }

    protected function cashierAt(Outlet $outlet): User
    {
        $user = User::factory()->role(Role::OutletStaff)->create();
        $user->outlets()->attach($outlet);

        return $user;
    }

    protected function voucherFor(Campaign $campaign, array $attributes = []): Voucher
    {
        return $campaign->vouchers()->create([
            'code' => 'BB-VM-'.Str::upper(Str::random(8)),
            'status' => 'issued',
            'voucher_template_id' => $campaign->voucher_template_id,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'benefit_title' => '1 PORSI',
            'benefit_description' => 'NASI BEBEK',
            'outlet_scope_text' => 'BERLAKU DI SEMUA CABANG',
            'issued_at' => now(),
            ...$attributes,
        ]);
    }
}
