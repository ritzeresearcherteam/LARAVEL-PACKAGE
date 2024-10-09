<?php
namespace Stanbic\LaravelMonitoring;

use DateTime;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Illuminate\Support\Facades\Log;

class LaravelMonitoringService
{
    private CollectorRegistry $collectorRegistry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->collectorRegistry = $registry->getDefault();
    }

    public function metrics(): string
    {

        $cpuUsage = 0;
        $cpuFrequency = 0;
        $cpuCount = 0;
        $platformGuage = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'platform_type',
            'Machine Platform',
            []
        );

        $uptimeGuage = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'system_uptime',
            'System Up Time',
            []
        );
        $cpuCountGuage = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'system_cpu_count',
            'System CPU Count',
            []
        );
        $cpuUsageGuage = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'system_cpu_usage',
            'System CPU Usage',
            []
        );
        //$totalMemory = $this->getTotalMemory();
        $memoryGauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'server_total_memory_bytes',
            'Total memory of the server in bytes',
            []
        );
        

        if (PHP_OS_FAMILY == 'Windows') {
            $output = shell_exec('net stats workstation');
            if ($output) {
                preg_match('/since (.*)/', $output, $matches);
                if (isset($matches[1])) {
                    $startTime = new DateTime($matches[1]);
                    $currentTime = new DateTime();
                    $uptimeGuage->set($currentTime->getTimestamp() - $startTime->getTimestamp());
                }
            }

            $cpuUsageOutput = shell_exec('wmic cpu get loadpercentage');
            if ($cpuUsageOutput) {
                $cpuUsageLines = explode("\n", $cpuUsageOutput);
                $cpuUsage = (int) $cpuUsageLines[1];
            }

            $cpuFrequencyOutput = shell_exec('wmic cpu get currentclockspeed');
            if ($cpuFrequencyOutput) {
                $cpuFrequencyLines = explode("\n", $cpuFrequencyOutput);
                $cpuFrequency = (int) $cpuFrequencyLines[1]; // in MHz
            }

            $cpuCountOutput = shell_exec('wmic cpu get numberofcores');
            if ($cpuCountOutput) {
                $cpuCountLines = explode("\n", $cpuCountOutput);
                $cpuCount = (int) $cpuCountLines[1];
            }

            $platformGuage->set(1);
        } elseif (PHP_OS_FAMILY == "Linux") {

            $output = shell_exec('cat /proc/uptime');
            if ($output) {
                $uptime = explode(" ", $output);
                $uptimeGuage->set((int) $uptime[0]);
            }

            $cpuUsageOutput = shell_exec("mpstat | awk '$12 ~ /[0-9.]+/ { print 100 - $12 }'");
            if ($cpuUsageOutput) {
                $cpuUsage = (float) trim($cpuUsageOutput);
            }

            $cpuFrequencyOutput = shell_exec('lscpu | grep "MHz"');
            if ($cpuFrequencyOutput) {
                preg_match('/[\d.]+/', $cpuFrequencyOutput, $matches);
                if (isset($matches[0])) {
                    $cpuFrequency = (float) $matches[0]; // in MHz
                }
            }

            $cpuCount = (int) shell_exec('nproc');

            $platformGuage->set(0);
        } elseif (PHP_OS_FAMILY == "Darwin") {
            $output = shell_exec('sysctl -n kern.boottime');
            if ($output) {
                preg_match('/sec = (\d+)/', $output, $matches);
                $uptime = explode(" ", $output);
                if (isset($matches)) {
                    $bootTime = (int) $matches[1];
                    $curr = time();
                    $uptimeGuage->set($curr - $bootTime);
                }
            }

            $cpuUsageOutput = shell_exec("top -l 1 | grep 'CPU usage' | awk '{print $3}'");
            if ($cpuUsageOutput) {
                $cpuUsage = (float) trim(str_replace('%', '', $cpuUsageOutput)); // in percentage
                $cpuUsageGuage->set((float) $cpuUsage);
            }

            $cpuFrequencyOutput = shell_exec("sysctl -n hw.cpufrequency");

            Log::info($cpuUsage);
            if ($cpuFrequencyOutput) {
                $cpuFrequency = (int) ($cpuFrequencyOutput / 1000000); // Convert Hz to MHz
            }

            $cpuCountOutput = shell_exec('sysctl -n hw.ncpu');
            if ($cpuCountOutput) {
                $cpuCount = (int) trim($cpuCountOutput);
                $cpuCountGuage->set($cpuCount);
            }


            $platformGuage->set(2);
        }

        $renderer = new RenderTextFormat();
        $result = $renderer->render($this->collectorRegistry->getMetricFamilySamples());
        header('Content-type: ' . RenderTextFormat::MIME_TYPE);

        return $result;
    }

    public function observeHttpRequest(string $method, string $path, int $status): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'http_requests_total',
            'Total number of HTTP requests',
            ['method', 'path', 'status']
        );

        $counter->incBy(1, [$method, $path, (string) $status]);
    }

    public function observeHttpRequestDuration(string $method, string $path, float $duration): void
    {
        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            'laravel',
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'path'],
            [0.1, 0.5, 1, 2.5, 5, 10]// Buckets
        );

        $histogram->observe($duration, [$method, $path]);
    }

    public function observeRequestSize(string $method, string $path, int $size): void
    {
        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            'laravel',
            'http_request_size_bytes',
            'HTTP request size in bytes',
            ['method', 'path']
        );

        $histogram->observe($size, [$method, $path]);
    }

    public function observeResponseSize(string $method, string $path, int $size): void
    {
        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            'laravel',
            'http_response_size_bytes',
            'HTTP response size in bytes',
            ['method', 'path']
        );

        $histogram->observe($size, [$method, $path]);
    }

    public function observeErrorRate(string $method, string $path, int $status): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'http_errors_total',
            'count',
            'Total number of HTTP errors',
            ['method', 'path', 'status']
        );

        if ($status >= 400) {
            $counter->incBy(1, [$method, $path, (string) $status]);
        }
    }

    public function observeConcurrency(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'http_requests_concurrent',
            'Number of concurrent HTTP requests',
            []
        );

        $gauge->inc();
    }

    public function decrementConcurrency(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'http_requests_concurrent',
            'Number of concurrent HTTP requests',
            []
        );

        $gauge->dec();
    }

    public function observeDbQueryTime(float $duration): void
    {
        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            'laravel',
            'db_query_duration_seconds',
            'Database query duration in seconds',
            [],
            [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1]// Buckets
        );

        $histogram->observe($duration);
    }

    public function incrementTotalDbAction($model_action)
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'db_total_actions',
            'Total Number of Ops',
            ['total']
        );

        if (config('database.default') == 'sqlite') {
            $db_nature = 1;
        } elseif (config('database.default') == 'mssql') {
            $db_nature = 2;
        } elseif (config('database.default') == 'mysql') {
            $db_nature = 3;
        } elseif (config('database.default') == 'postgres') {
            $db_nature = 4;
        } else {
            $db_nature = 50;
        }

        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'db_nature',
            'Database Nature',
            []
        );

        $gauge->set($db_nature);
        $counter->incBy(1, [$model_action]);
    }

    public function incrementInsertDbAction($model_action)
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'db_insert_actions',
            'Total Number of Insert Ops',
            ['action']
        );

        $counter->incBy(1, [$model_action]);
    }

    public function incrementUpdateDbAction($model_action)
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'db_update_actions',
            'Total Number of Update Ops',
            ['action']
        );

        $counter->incBy(1, [$model_action]);
    }

    public function incrementDeleteDbAction($model_action)
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'db_delete_actions',
            'Total Number of Delete Ops',
            ['action']
        );

        $counter->incBy(1, [$model_action]);
    }

    public function observeDatabaseQueryCount(int $count): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'db_queries_total',
            'Total number of database queries',
            []
        );

        $counter->incBy($count);
    }

    public function observeQueueJob(string $queueName, bool $success, float $duration): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'queue_jobs_total',
            'Total number of queue jobs processed',
            ['queue', 'status']
        );

        $status = $success ? 'success' : 'failed';
        $counter->incBy(1, [$queueName, $status]);

        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            'laravel',
            'queue_job_duration_seconds',
            'Duration of queue jobs in seconds',
            ['queue'],
            [0.1, 0.5, 1, 2.5, 5, 10]
        );

        $histogram->observe($duration, [$queueName]);
    }

    public function observeCacheHit(string $key): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'cache_hits_total',
            'Total number of cache hits',
            ['key']
        );

        $counter->incBy(1, [$key]);
    }

    public function observeCacheMiss(string $key): void
    {
        $counter = $this->collectorRegistry->getOrRegisterCounter(
            'laravel',
            'cache_misses_total',
            'Total number of cache misses',
            ['key']
        );

        $counter->incBy(1, [$key]);
    }

    public function observeMemoryUsage(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'system',
            'memory_usage_bytes',
            'Current memory usage in bytes',
            []
        );

        $gauge->set(memory_get_usage(true));
    }

    public function observeCpuUsage(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'system',
            'cpu_usage_percentage',
            'Current CPU usage percentage',
            []
        );

        $cpuLoad = sys_getloadavg();
        $gauge->set($cpuLoad[0]); // Current CPU load average
    }

    public function observeActiveSessions(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'active_sessions_total',
            'Number of active user sessions',
            []
        );

        // Get the current session count (if using a session driver like Redis or database)
        // $sessionCount = \DB::table('sessions')->count();

        // Update the gauge with the active session count
        //$gauge->set(session()->count());
        // $gauge->set($sessionCount);
    }

    public function observeWorkerMemoryUsage(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'worker_memory_usage_bytes',
            'Worker memory usage in bytes',
            []
        );

        $gauge->set(memory_get_usage());
    }

    public function observeWorkerCpuUsage(): void
    {
        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            'laravel',
            'worker_cpu_usage_percentage',
            'Worker CPU usage percentage',
            []
        );

        $gauge->set(sys_getloadavg()[0]); // Gets the CPU load average
    }
}
