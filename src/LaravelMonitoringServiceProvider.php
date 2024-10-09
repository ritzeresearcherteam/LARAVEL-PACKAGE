<?php
namespace Stanbic\LaravelMonitoring;
 
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\redis;
 
class LaravelMonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
 
    {
   
    $this->app->singleton(CollectorRegistry::class, function () {
   
    // Initialize the CollectorRegistry with InMemory storage
   
    $adapter = new Redis(); // Create an instance of your InMemory storage adapter
   
    return new CollectorRegistry($adapter); // Pass the adapter to CollectorRegistry
   
    });
   
    }
 
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot ()
    {
        //
    }
}