<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Observers\VersionObserver;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Register permission gates.
        foreach (Permissions::all() as $permission) {
            Gate::define($permission, function (User $user, $pharmacy = null) use ($permission) {
                return $user->hasPermission($permission, $pharmacy instanceof Pharmacy ? $pharmacy : null);
            });
        }

        // Pharmacy access gate.
        Gate::define('access-pharmacy', function (User $user, Pharmacy $pharmacy) {
            return $user->canAccessPharmacy($pharmacy);
        });

        // Synchronisation offline : version à chaque écriture.
        foreach ([Product::class, Customer::class, InsuranceCompany::class, InsuranceRule::class, StockLevel::class] as $model) {
            $model::observe(VersionObserver::class);
        }
    }
}
