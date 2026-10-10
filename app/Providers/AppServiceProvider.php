<?php

namespace App\Providers;

use App\Services\Admin\ProductManager;
use App\Services\CatalogueSource;
use App\Services\Commerce\Cart;
use App\Services\Commerce\InventoryAvailability;
use App\Services\ResponsiveMedia;
use App\Services\SelectedCatalogueSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CatalogueSource::class, SelectedCatalogueSource::class);
        $this->app->scoped(ResponsiveMedia::class);
        $this->app->scoped(Cart::class);
        $this->app->scoped(ProductManager::class);
        $this->app->scoped(InventoryAvailability::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-document-download', fn (Request $request) => Limit::perMinute(20)->by('admin-document-download|'.$request->ip()));
        RateLimiter::for('commercial-document-download', fn (Request $request) => Limit::perMinute(20)->by('commercial-document-download|'.$request->ip()));
        RateLimiter::for('admin-document-issue', fn (Request $request) => Limit::perMinute(5)->by('admin-document-issue|'.$request->ip()));
        RateLimiter::for('admin-oauth', fn (Request $request) => Limit::perMinute(10)->by('admin-oauth|'.$request->ip()));
        RateLimiter::for('admin-write', fn (Request $request) => Limit::perMinute(20)->by('admin-write|'.$request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => [Limit::perMinute(5)->by('admin-minute|'.$request->ip()), Limit::perHour(30)->by('admin-hour|'.$request->ip())]);
        RateLimiter::for('return-review', fn (Request $request) => Limit::perMinute(10)->by('return-review|'.$request->ip()));
        RateLimiter::for('return-submit', fn (Request $request) => Limit::perMinute(5)->by('return-submit|'.$request->ip()));
        RateLimiter::for('transfer-proof', fn (Request $request) => Limit::perMinute(5)->by('proof|'.$request->ip().'|'.$request->route('id')));
        RateLimiter::for('address-lookup', fn (Request $request) => Limit::perMinute(20)->by('addresses|'.$request->ip()));
    }
}
