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
