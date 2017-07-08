<?php

namespace App\Providers;

use Herzcthu\ExchangeRates\CrawlBank;
use Illuminate\Support\ServiceProvider;

class ExRatesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('crawlbank', function () {
            return $this->app->make(CrawlBank::class);
        });
    }
}
