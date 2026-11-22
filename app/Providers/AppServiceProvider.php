<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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
        // @rupiah($angka) => "Rp 1.250.000"
        Blade::directive('rupiah', fn (string $expr) => "<?php echo 'Rp '.number_format((float) ($expr), 0, ',', '.'); ?>");
        Paginator::useBootstrapFive();
    }
}
