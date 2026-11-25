<?php

namespace App\Providers;

use App\Events\DownPaymentInvoiceIssued;
use App\Events\QuotationExpiringSoon;
use App\Events\QuotationSent;
use App\Models\Invoice;
use App\Models\OrderChat;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectFile;
use App\Models\Quotation;
use App\Models\RevisionTicket;
use App\Models\User;
use App\Policies\ChatPolicy;
use App\Policies\FilePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MilestonePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\TicketPolicy;
use App\Services\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
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
        Gate::policy(ProjectFile::class, FilePolicy::class);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('access-internal', fn (User $user) => $user->isAdmin() || $user->isStaff());
        Gate::define('access-portal', fn (User $user) => $user->isClient());

        // Email transaksional (T3-09). Closure, bukan kelas listener, agar tidak ikut terdaftar dua kali oleh auto-discovery.
        Event::listen(QuotationSent::class, fn (QuotationSent $e) => app(Notifier::class)->quotationSent($e->quotation));
        Event::listen(QuotationExpiringSoon::class, fn (QuotationExpiringSoon $e) => app(Notifier::class)->quotationExpiring($e->quotation));
        Event::listen(DownPaymentInvoiceIssued::class, fn (DownPaymentInvoiceIssued $e) => app(Notifier::class)->downPaymentInvoice($e->invoice));

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
        Model::shouldBeStrict(! app()->isProduction());

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
