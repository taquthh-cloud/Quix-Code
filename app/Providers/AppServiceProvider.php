<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\OrderChat;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Quotation;
use App\Models\RevisionTicket;
use App\Models\User;
use App\Policies\ChatPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MilestonePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\TicketPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(RevisionTicket::class, TicketPolicy::class);
        Gate::policy(OrderChat::class, ChatPolicy::class);
        Gate::policy(ProjectMilestone::class, MilestonePolicy::class);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('access-internal', fn (User $user) => $user->isAdmin() || $user->isStaff());
        Gate::define('access-portal', fn (User $user) => $user->isClient());

        // Catat login terakhir (termasuk login via passkey dan 2FA).
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
