<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\FieldAria;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // <input @aria('email')> -> aria-describedby/aria-invalid from <x-field>.
        Blade::directive('aria', function (string $expression): string {
            return "<?php echo \\App\\Support\\FieldAria::attrs({$expression}); ?>";
        });

        Gate::define('viewActivity', fn (User $user): bool => $user->hasRole(Role::SuperAdmin));
        Gate::define('viewQueueStatus', fn (User $user): bool => $user->hasRole(Role::SuperAdmin, Role::VoucherAdmin));

        Event::listen(Login::class, fn (Login $event) => Audit::record('auth.login', $event->user, [], $event->user));
        Event::listen(Logout::class, fn (Logout $event) => Audit::record('auth.logout', $event->user, [], $event->user));
    }
}
