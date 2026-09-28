<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\CashSession;
use App\Models\PatientImage;
use App\Models\TreatmentProcedurePrice;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PatientPolicy;
use App\Policies\TreatmentPolicy;
use App\Policies\UserPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\PatientImagePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Patient::class => PatientPolicy::class,
        Appointment::class => AppointmentPolicy::class,
        Treatment::class => TreatmentPolicy::class,
        Invoice::class => InvoicePolicy::class,
        InventoryItem::class => InventoryItemPolicy::class,
        User::class => UserPolicy::class,
        CashSession::class => CashSessionPolicy::class,
        PatientImage::class => PatientImagePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('viewReports', function (User $user) {
            return $user->hasAnyRole(['admin', 'receptionist']);
        });

        Gate::define('manageTreatmentPrices', function (User $user) {
            return $user->hasRole('admin');
        });
    }
}
