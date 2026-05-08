<?php

namespace App\Providers;

use App\Http\Middleware\EnsureLoanAccessPolicy;
use App\Http\Middleware\EnsureLoanPermission;
use App\Http\Middleware\EnsureLoanRole;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsurePropertyPermission;
use App\Http\Middleware\EnsurePropertyPortalRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\AccountingChartAccount;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Observers\LoanBookApplicationClientLeadObserver;
use App\Observers\LoanBookLoanClientLeadObserver;
use App\View\Compilers\AppBladeCompiler;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
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
        $this->guardAgainstDestructiveLocalCommands();

        LoanBookApplication::observe(LoanBookApplicationClientLeadObserver::class);
        LoanBookLoan::observe(LoanBookLoanClientLeadObserver::class);

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        View::composer('*', function ($view) {
            $overdrawnCount = 0;
            if (Schema::hasTable('accounting_chart_accounts')
                && Schema::hasColumn('accounting_chart_accounts', 'allow_overdraft')
                && Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
                $overdrawnCount = AccountingChartAccount::query()
                    ->where('allow_overdraft', true)
                    ->where('current_balance', '<', 0)
                    ->count();
            }
            $allowDestructiveDbCommands = filter_var((string) env('ALLOW_DESTRUCTIVE_DB_COMMANDS', false), FILTER_VALIDATE_BOOLEAN);
            $view->with('overdrawnCount', $overdrawnCount);
            $view->with('allowDestructiveDbCommands', $allowDestructiveDbCommands);
        });
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
