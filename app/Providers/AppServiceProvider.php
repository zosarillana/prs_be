<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
     public function boot()
    {
        // Force Laravel to use the APP_URL for all URL generation
        URL::forceRootUrl(config('app.url'));
        
        // Optional: Force scheme if needed
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }
    }
}
