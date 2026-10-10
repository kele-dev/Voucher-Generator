<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueStallBannerTest extends TestCase
{
    use RefreshDatabase;

    private const BANNER = 'Queue worker tidak berjalan';

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    private function job(int $ageSeconds, ?int $reservedAgo = null): void
    {
        $at = now()->getTimestamp() - $ageSeconds;
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => $reservedAgo === null ? 0 : 1,
            'reserved_at' => $reservedAgo === null ? null : now()->getTimestamp() - $reservedAgo,
            'available_at' => $at, 'created_at' => $at,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::VoucherAdmin]);
    }

    public function test_banner_shows_when_a_job_waits_and_nothing_runs(): void
    {
        $this->job(300);

        $this->actingAs($this->admin())->get('/dashboard')->assertOk()->assertSee(self::BANNER);
    }

    public function test_no_banner_for_a_fresh_job(): void
    {
        $this->job(10);

        $this->actingAs($this->admin())->get('/dashboard')->assertDontSee(self::BANNER);
    }

    public function test_no_banner_while_the_worker_is_busy_with_another_job(): void
    {
        $this->job(300);
        $this->job(600, reservedAgo: 500);

        $this->actingAs($this->admin())->get('/dashboard')->assertDontSee(self::BANNER);
    }

    public function test_banner_when_the_only_reservation_belongs_to_a_dead_worker(): void
    {
        $this->job(300);
        $this->job(5000, reservedAgo: config('queue.connections.database.retry_after') + 60);

        $this->actingAs($this->admin())->get('/dashboard')->assertSee(self::BANNER);
    }

    public function test_staff_and_viewers_do_not_see_the_banner(): void
    {
        $this->job(300);

        foreach ([Role::OutletStaff, Role::Viewer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/dashboard')->assertDontSee(self::BANNER);
        }
    }
}
