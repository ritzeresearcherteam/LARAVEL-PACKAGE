<?php
namespace Stanbic\LaravelMonitoring;

use Closure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class LaravelMonitoringMiddleware
{
    private LaravelMonitoringService $prometheusService;

    public function __construct(LaravelMonitoringService $prometheusService)
    {
        $this->prometheusService = $prometheusService;
    }

    /**

     * Handle an incoming request.

     *

     * @param  \Illuminate\Http\Request  $request

     * @param  \Closure  $next

     * @return mixed

     */

    public function handle(Request $request, Closure $next)
    {

        // Observe metrics

        try {
        // Start time to measure request duration

        $startTime = microtime(true);

        $this->prometheusService->observeActiveSessions();

        $this->prometheusService->observeConcurrency();

        $this->prometheusService->observeMemoryUsage();

        // Pass the request to the next middleware/controller

        $response = $next($request);

        $this->prometheusService->decrementConcurrency();



        // Calculate request duration

        $duration = microtime(true) - $startTime;



        // Get HTTP method, path, and status code, size

        $method = $request->method();

        $path = $request->path();

        $status = $response->getStatusCode();

        $requestSize = strlen($request->getContent());

        $responseSize = strlen($response->getContent());


            $this->prometheusService->observeHttpRequest($method, $path, $status);

            $this->prometheusService->observeHttpRequestDuration($method, $path, $duration);





            $this->prometheusService->observeRequestSize($method, $path, $requestSize);

            $this->prometheusService->observeResponseSize($method, $path, $responseSize);



            // listen to db query count

            \DB::listen(function ($query) {

                // Increment your query count metric

                $this->prometheusService->observeDatabaseQueryCount(1);

            });

           

        } catch (\Exception $e) {

            Log::error('Error observing Prometheus metrics: ' . $e->getMessage());

        }



        return $response;

    }

}