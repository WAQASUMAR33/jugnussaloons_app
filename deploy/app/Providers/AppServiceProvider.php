<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
            if ($user->hasPermission($ability)) {
                return true;
            }
        });

        View::composer('*', function ($view) {
            static $cachedSetting = null;
            if ($cachedSetting === null) {
                try {
                    $cachedSetting = Setting::getSettings();
                } catch (\Throwable $e) {
                    $cachedSetting = null;
                }
            }
            if ($cachedSetting) {
                $view->with('appSetting', $cachedSetting);
            }
        });
    }
}
