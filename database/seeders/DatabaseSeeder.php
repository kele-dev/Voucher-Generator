<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The first super admin comes from SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD.
     * Without a password a random one is generated and printed once, so no
     * well-known credential ever reaches a real deployment.
     */
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@example.com');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! User::where('email', $email)->exists()) {
            $generated = blank($password);
            $password = $generated ? Str::password(16, symbols: false) : $password;

            User::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => $password,
                'role' => Role::SuperAdmin,
                'is_active' => true,
            ]);

            $this->command?->info("Super admin: {$email}");
            if ($generated) {
                $this->command?->warn("Generated password (shown once): {$password}");
            }
        }

        if (app()->environment('local')) {
            $this->call([DemoDataSeeder::class, TemplateSeeder::class, TemplateContentSlotsSeeder::class]);
        }
    }
}
