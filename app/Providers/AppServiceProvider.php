<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        $rootUrl = $request->getSchemeAndHttpHost();

        if ($rootUrl === '') {
            return;
        }

        // Prefer the host actually used by the browser so shared LAN access
        // keeps generating URLs with the remote IP instead of localhost.
        URL::forceRootUrl($rootUrl);
        URL::forceScheme($request->getScheme());
    }
}
