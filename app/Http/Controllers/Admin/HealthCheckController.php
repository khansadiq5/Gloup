<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Comprehensive health check endpoint.
     */
    public function check(Request $request): JsonResponse
    {
        $checks = [];
        $startTime = microtime(true);

        // Database
        $checks['database'] = $this->checkDatabase();

        // Cache
        $checks['cache'] = $this->checkCache();

        // Queue
        $checks['queue'] = $this->checkQueue();

        // Storage
        $checks['storage'] = $this->checkStorage();

        // Redis (if configured)
        $checks['redis'] = $this->checkRedis();

        // Application
        $checks['application'] = $this->checkApplication();

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $overall = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                $overall = 'unhealthy';
                break;
            }
            if ($check['status'] === 'degraded' && $overall === 'healthy') {
                $overall = 'degraded';
            }
        }

        return response()->json([
            'status' => $overall,
            'timestamp' => now()->toIso8601String(),
            'duration_ms' => $duration,
            'checks' => $checks,
        ], $overall === 'healthy' ? 200 : ($overall === 'degraded' ? 200 : 503));
    }

    /**
     * Lightweight liveness probe.
     */
    public function live(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe.
     */
    public function ready(Request $request): JsonResponse
    {
        $dbHealthy = $this->checkDatabase()['status'] === 'healthy';
        $cacheHealthy = $this->checkCache()['status'] === 'healthy';

        if ($dbHealthy && $cacheHealthy) {
            return response()->json([
                'status' => 'ready',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'status' => 'not_ready',
            'timestamp' => now()->toIso8601String(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ], 503);
    }

    /**
     * Detailed system metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $metrics = [
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'uptime_seconds' => $this->getUptime(),
            ],
            'database' => $this->getDatabaseMetrics(),
            'queue' => $this->getQueueMetrics(),
            'cache' => $this->getCacheMetrics(),
        ];

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'metrics' => $metrics,
        ]);
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'latency_ms' => $duration,
                'message' => 'Connected',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_'.time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache working',
                ];
            }

            return [
                'status' => 'degraded',
                'message' => 'Cache read/write mismatch',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $size = Queue::size();
            $failed = Queue::failed()->count();

            $status = 'healthy';
            if ($failed > 100) {
                $status = 'degraded';
            }
            if ($failed > 1000) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'pending_jobs' => $size,
                'failed_jobs' => $failed,
                'message' => $status === 'healthy' ? 'Queue healthy' : 'Queue has failed jobs',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $path = storage_path();
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            $usedPercent = $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;

            $status = 'healthy';
            if ($usedPercent > 90) {
                $status = 'degraded';
            }
            if ($usedPercent > 95) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                'used_percent' => $usedPercent,
                'message' => "{$usedPercent}% used",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkRedis(): array
    {
        if (! config('database.redis.client')) {
            return [
                'status' => 'not_configured',
                'message' => 'Redis not configured',
            ];
        }

        try {
            $redis = app('redis')->connection();
            $start = microtime(true);
            $redis->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'message' => 'PONG',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkApplication(): array
    {
        return [
            'status' => 'healthy',
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'message' => 'Application running',
        ];
    }

    protected function getUptime(): int
    {
        // Laravel doesn't track uptime natively, return process uptime approximation
        return (int) (microtime(true) - LARAVEL_START);
    }

    protected function getDatabaseMetrics(): array
    {
        try {
            $connections = DB::getConnections();
            $pdo = DB::connection()->getPdo();

            return [
                'connections' => count($connections),
                'driver' => $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
                'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function getQueueMetrics(): array
    {
        return [
            'pending' => Queue::size(),
            'failed' => Queue::failed()->count(),
        ];
    }

    protected function getCacheMetrics(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
        ];
    }
}
