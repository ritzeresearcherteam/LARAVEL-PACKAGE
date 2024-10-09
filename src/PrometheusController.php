<?php
namespace Stanbic\LaravelMonitoring;

use App\Http\Controllers;

class PrometheusController extends Controller
{
  
    private LaravelMonitoringService $service;

    public function __construct (LaravelMonitoringService $service )
    {
        $this->service = $service;
    }

    public function metrics (): string
    {
        return $this->service->metrics();
    }
}


abstract class Controller
{
    //
}
