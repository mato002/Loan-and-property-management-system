<?php

namespace App\Providers;

use App\Http\Middleware\EnsureLoanAccessPolicy;
use App\Http\Middleware\EnsureLoanPermission;
use App\Http\Middleware\EnsureLoanRole;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsurePropertyPermission;
use App\Http\Middleware\EnsurePropertyPortalRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\DepositDefinition;
use App\Models\ExpenseDefinition;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmLease;
use App\Models\PmPaymentAllocation;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\UnassignedPayment;
use App\Observers\LoanBookApplicationClientLeadObserver;
use App\Observers\LoanBookLoanClientLeadObserver;
use App\Observers\PropertyDashboardCacheObserver;
use App\View\Compilers\AppBladeCompiler;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Illuminate\View\DynamicComponent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fallback container aliases for middleware strings. This prevents
        // "Target class [loan.permission] does not exist" when production
        // caches are stale and route middleware strings resolve via container.
        $this->app->bind('module.access', EnsureModuleAccess::class);
        $this->app->bind('loan.role', EnsureLoanRole::class);
        $this->app->bind('loan.permission', EnsureLoanPermission::class);
        $this->app->bind('loan.access_policy', EnsureLoanAccessPolicy::class);
        $this->app->bind('property.portal', EnsurePropertyPortalRole::class);
        $this->app->bind('property.permission', EnsurePropertyPermission::class);
        $this->app->bind('superadmin', EnsureSuperAdmin::class);

        $this->app->extend('blade.compiler', function ($_compiler, $app) {
            $blade = new AppBladeCompiler(
                $app['files'],
                $app['config']['view.compiled'],
                $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                $app['config']->get('view.cache', true),
                $app['config']->get('view.compiled_extension', 'php'),
                $app['config']->get('view.check_cache_timestamps', true),
            );

            $blade->component('dynamic-component', DynamicComponent::class);

            return $blade;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadGroupedMigrationPaths();
        $this->guardAgainstDestructiveLocalCommands();

        LoanBookApplication::observe(LoanBookApplicationClientLeadObserver::class);
        LoanBookLoan::observe(LoanBookLoanClientLeadObserver::class);

        $dashboardCacheObserver = PropertyDashboardCacheObserver::class;
        foreach ([
            PmInvoice::class,
            PmPayment::class,
            PmPaymentAllocation::class,
            PmInvoicePenaltyApplication::class,
            PmLease::class,
            PmTenant::class,
            Property::class,
            PropertyUnit::class,
            PmMaintenanceRequest::class,
            PmMaintenanceJob::class,
            UnassignedPayment::class,
            ExpenseDefinition::class,
            DepositDefinition::class,
        ] as $model) {
            $model::observe($dashboardCacheObserver);
        }

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        // Only property sidebars read this flag; avoid Schema/DB work on every view render.
        $allowDestructiveDbCommands = filter_var((string) env('ALLOW_DESTRUCTIVE_DB_COMMANDS', false), FILTER_VALIDATE_BOOLEAN);
        View::composer([
            'layouts.property.*',
            'layouts.property_sidebar_*',
        ], function ($view) use ($allowDestructiveDbCommands) {
            $view->with('allowDestructiveDbCommands', $allowDestructiveDbCommands);
        });
    }

    private function loadGroupedMigrationPaths(): void
    {
        foreach (['core', 'loan', 'property', 'shared'] as $group) {
            $path = database_path('migrations/'.$group);
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }

    private function guardAgainstDestructiveLocalCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! $this->app->environment('local')) {
            return;
        }

        $allow = filter_var((string) env('ALLOW_DESTRUCTIVE_DB_COMMANDS', false), FILTER_VALIDATE_BOOLEAN);
        if ($allow) {
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = (string) ($argv[1] ?? '');
        if ($command === '') {
            return;
        }

        $blocked = [
            'migrate:fresh',
            'migrate:refresh',
            'db:wipe',
            'db:reset',
            'schema:drop',
        ];

        if (! in_array($command, $blocked, true)) {
            return;
        }

        throw new RuntimeException(
            'Blocked destructive database command "'.$command.'" in local environment. '
            .'Set ALLOW_DESTRUCTIVE_DB_COMMANDS=true only when you intentionally want to wipe/reset data.'
        );
    }
}
