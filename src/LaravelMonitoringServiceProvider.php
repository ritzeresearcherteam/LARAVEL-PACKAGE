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
        $this->app->singleton( CollectorRegistry::class, function () {

            Redis::setDefaultOptions(
                Arr::only( config( 'database.redis.default' ), [ 'host', 'password', 'username' ] )
            );

            return CollectorRegistry::getDefault();

        } );
   
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