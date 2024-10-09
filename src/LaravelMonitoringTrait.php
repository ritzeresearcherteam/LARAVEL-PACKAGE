<?php
 
namespace Stanbic\LaravelMonitoring;
 
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Illuminate\Support\Facades\Log;
 
trait LaravelMonitoringTrait
{
  
    protected  $service;

    protected static function bootPrometheusMetricsTrait()
    {

        static::saved(function ($model) {        
                $service = app(LaravelMonitoringService::class);
                $service->incrementInsertDbAction('save');
                $service->incrementTotalDbAction('total');

        });
 
        static::updated(function ($model) {            
            $service = app(LaravelMonitoringService::class);
            $service->incrementUpdateDbAction('update');
            $service->incrementTotalDbAction('total');
        });
 
        static::deleted(function ($model) {      
            $service = app(LaravelMonitoringService::class);
            $service->incrementDeleteDbAction('delete');
            $service->incrementTotalDbAction('total');
        });
    }
 
    protected static function incrementMetric($metricName, $action)
    {
        Log::error($action);
        $registry = self::getPrometheusRegistry();
        $counter = $registry->getOrRegisterCounter('app', $metricName, 'Number of model actions', ['action']);
        Log::info("adding");
        $counter->inc(['action' => $action]);
    }
 
    protected static function getPrometheusRegistry()
    {
        static $registry;
 
        if (!$registry) {
            $registry = new CollectorRegistry(new \Prometheus\Storage\InMemory());
        }
 
        return $registry;
    }
 
    public static function getMetrics()
    {
        $registry = self::getPrometheusRegistry();
        $renderer = new RenderTextFormat();
        $metrics = $registry->getMetricFamilySamples();
        return $renderer->render($metrics);
    }
}