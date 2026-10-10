<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\GenerationBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesVouchers;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesVouchers, RefreshDatabase;

    private function viewer(): User
    {
        return User::factory()->role(Role::Viewer)->create();
    }

    public function test_dashboard_summarises_generation(): void
    {
        $campaign = $this->activeCampaign(['name' => 'Promo Dashboard']);
        $this->voucherFor($campaign);
        $this->voucherFor($campaign);
        $this->voucherFor($campaign, ['status' => 'voided']);
        $this->voucherFor($campaign, ['valid_until' => now()->subDay()]);

        $response = $this->actingAs($this->viewer())->get('/dashboard')->assertOk()->assertSee('Promo Dashboard');
        $metrics = $response->viewData('metrics');

        $this->assertSame(1, $metrics['campaigns']);
        $this->assertSame(4, $metrics['issued']);
        $this->assertSame(2, $metrics['active']);
        $this->assertSame(1, $metrics['expired']);
        $this->assertSame(1, $metrics['voided']);
        $this->assertArrayNotHasKey('redeemed', $metrics);
    }

    public function test_date_filter_excludes_other_periods(): void
    {
        $this->voucherFor($this->activeCampaign());

        $metrics = $this->actingAs($this->viewer())
            ->get('/dashboard?from='.now()->subYear()->toDateString().'&to='.now()->subMonths(6)->toDateString())
            ->assertOk()->viewData('metrics');

        $this->assertSame(0, $metrics['issued']);
        $this->assertSame(0, $metrics['batches']);
    }

    public function test_campaign_filter_limits_figures(): void
    {
        $a = $this->activeCampaign(['name' => 'Campaign A']);
        $b = $this->activeCampaign(['name' => 'Campaign B']);
        $this->voucherFor($a);
        $this->voucherFor($b);
        $this->voucherFor($b);

        $metrics = $this->actingAs($this->viewer())->get('/dashboard?campaign_id='.$b->id)->assertOk()->viewData('metrics');

        $this->assertSame(1, $metrics['campaigns']);
        $this->assertSame(2, $metrics['issued']);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->actingAs($this->viewer())
            ->get('/dashboard?from=2026-02-01&to=2026-01-01')
            ->assertSessionHasErrors('to');
    }

    public function test_outlet_staff_do_not_get_voucher_figures(): void
    {
        $campaign = $this->activeCampaign();
        $this->voucherFor($campaign);
        $staff = $this->cashierAt($this->outletFor($campaign));

        $metrics = $this->actingAs($staff)->get('/dashboard')->assertOk()->viewData('metrics');

        $this->assertNull($metrics['issued']);
        $this->assertNull($metrics['batches']);
    }
}
