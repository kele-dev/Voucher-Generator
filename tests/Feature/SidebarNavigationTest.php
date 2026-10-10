<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_groups_links_and_marks_the_current_page(): void
    {
        $response = $this->actingAs(User::factory()->role(Role::SuperAdmin)->create())->get(route('campaigns.index'));

        $response->assertOk()
            ->assertSee('aria-label="Navigasi utama"', false)
            ->assertSeeInOrder(['Voucher', 'Editor', 'Campaign', 'Laporan', 'Dashboard', 'Aktivitas', 'Master', 'Brand', 'User'])
            ->assertDontSee(route('outlets.index'))
            ->assertDontSee(route('templates.index'));

        preg_match_all('/<a href="([^"]+)" title="[^"]+"\s+aria-current="page"/', $response->getContent(), $current);
        $this->assertSame([route('campaigns.index')], $current[1], 'exactly one current link');
    }

    public function test_editor_page_is_accessible_and_lists_brand_templates(): void
    {
        $admin = User::factory()->role(Role::SuperAdmin)->create();
        $brand = \App\Models\Brand::factory()->create(['name' => 'Bebek Boedjang']);
        $template = \App\Models\VoucherTemplate::factory()->create(['brand_id' => $brand->id, 'name' => 'Voucher Fisik']);

        $response = $this->actingAs($admin)->get(route('editor.index'));
        $response->assertOk()
            ->assertSee('Editor template voucher')
            ->assertSee($brand->name)
            ->assertSee($template->name)
            ->assertSee(route('templates.editor', $template));
    }

    public function test_closed_mobile_drawer_is_kept_out_of_keyboard_focus(): void
    {
        $html = $this->actingAs(User::factory()->role(Role::VoucherAdmin)->create())->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('x-effect="$el.inert = ! drawer && ! desktop"', $html);
        // Open drawer traps focus by making the page behind it inert.
        $this->assertStringContainsString('x-effect="$el.inert = drawer && ! desktop"', $html);
        $this->assertStringContainsString('x-ref="menuButton" @click="openDrawer()"', $html);
        $this->assertStringContainsString('@keydown.escape.window="closeDrawer()"', $html);
    }

    public function test_links_follow_role_permissions(): void
    {
        $staff = $this->actingAs(User::factory()->role(Role::OutletStaff)->create())->get(route('dashboard'));
        $staff->assertOk()->assertDontSee(route('users.index'))->assertDontSee(route('activity.index'));

        $viewer = $this->actingAs(User::factory()->role(Role::Viewer)->create())->get(route('dashboard'));
        $viewer->assertOk()->assertDontSee(route('users.index'));
    }

    public function test_redeem_and_report_pages_are_gone(): void
    {
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());

        foreach (['/redeem', '/reports', '/reports/redemptions.csv'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->post('/redeem/verify')->assertNotFound();
        $this->post('/redeem/confirm')->assertNotFound();
    }

    public function test_guest_pages_have_no_sidebar(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('Navigasi utama');
    }
}
