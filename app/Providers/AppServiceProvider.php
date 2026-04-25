<?php

namespace App\Providers;

use App\Models\AccountingChartAccount;
use App\View\Compilers\AppBladeCompiler;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\DynamicComponent;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
            $view->with('overdrawnCount', $overdrawnCount);
        });
    }
}
