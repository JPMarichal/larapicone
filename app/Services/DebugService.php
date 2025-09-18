<?php

namespace App\Services;

use App\Services\Interfaces\PineconeClientInterface;
use App\Services\Interfaces\EmbeddingServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DebugService
{
    protected PineconeClientInterface $pineconeClient;
    protected EmbeddingServiceInterface $embeddingService;
    protected array $config;

    public function __construct(
        PineconeClientInterface $pineconeClient,
        EmbeddingServiceInterface $embeddingService
    ) {
        $this->pineconeClient = $pineconeClient;
        $this->embeddingService = $embeddingService;
        $this->loadConfig();
    }

    /**
     * Get system information and health status
     */
    public function getSystemInfo(): array
    {
        return [
            'system' => $this->getSystemStatus(),
            'services' => $this->getServicesStatus(),
            'configuration' => $this->getConfiguration(),
            'performance' => $this->getPerformanceMetrics(),
            'environment' => $this->getEnvironmentInfo()
        ];
    }

    /**
     * Get basic system status
     */
    protected function getSystemStatus(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug', false),
            'timezone' => config('app.timezone'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'server_os' => php_uname(),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
        ];
    }

    /**
     * Get status of external services
     */
    protected function getServicesStatus(): array
    {
        return [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'redis' => $this->checkRedisConnection(),
            'pinecone' => $this->checkPineconeConnection(),
            'embedding_service' => $this->checkEmbeddingService(),
        ];
    }

    /**
     * Get application configuration (sanitized)
     */
    protected function getConfiguration(): array
    {
        return [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'maintenance' => [
                    'enabled' => app()->isDownForMaintenance(),
                    'secret' => config('app.maintenance.secret') ? '***' : null,
                ],
            ],
            'services' => [
                'pinecone' => [
                    'enabled' => !empty(config('pinecone.api_key')),
                    'environment' => config('pinecone.environment'),
                    'index' => config('pinecone.index'),
                    'namespace' => config('pinecone.namespace'),
                ],
                'ollama' => [
                    'enabled' => !empty(config('services.ollama.base_url')),
                    'base_url' => config('services.ollama.base_url'),
                    'model' => config('services.ollama.embed_model'),
                ],
            ],
            'cache' => [
                'default' => config('cache.default'),
                'stores' => array_keys(config('cache.stores')),
            ],
            'database' => [
                'default' => config('database.default'),
                'connections' => array_keys(config('database.connections')),
            ],
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics(): array
    {
        return [
            'memory' => [
                'usage' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'limit' => ini_get('memory_limit'),
            ],
            'execution_time' => [
                'max_execution_time' => ini_get('max_execution_time') . 's',
                'max_input_time' => ini_get('max_input_time') . 's',
            ],
            'opcache' => $this->getOpcacheStatus(),
            'query_log' => $this->getQueryLog(),
        ];
    }

    /**
     * Get environment information
     */
    protected function getEnvironmentInfo(): array
    {
        return [
            'environment_variables' => [
                'APP_ENV' => env('APP_ENV'),
                'APP_DEBUG' => env('APP_DEBUG'),
                'APP_URL' => env('APP_URL'),
                'DB_CONNECTION' => env('DB_CONNECTION'),
                'CACHE_DRIVER' => env('CACHE_DRIVER'),
                'QUEUE_CONNECTION' => env('QUEUE_CONNECTION'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'os' => PHP_OS,
                'sapi' => PHP_SAPI,
                'extensions' => get_loaded_extensions(),
            ],
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'system' => php_uname(),
            'timezone' => date_default_timezone_get(),
            'locale' => setlocale(LC_ALL, 0),
            'disk' => [
                'total' => $this->formatBytes(disk_total_space('/')), 
                'free' => $this->formatBytes(disk_free_space('/')),
                'used' => $this->formatBytes(disk_total_space('/') - disk_free_space('/')),
            ],
        ];
    }

    /**
     * Check database connection and get basic info
     */
    protected function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            
            return [
                'status' => 'connected',
                'driver' => DB::connection()->getDriverName(),
                'database' => DB::connection()->getDatabaseName(),
                'version' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'tables' => $this->getDatabaseTables(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get list of database tables with row counts
     */
    protected function getDatabaseTables(): array
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $tableInfo = [];
            
            foreach ($tables as $table) {
                $tableName = array_values((array)$table)[0];
                $count = DB::table($tableName)->count();
                $tableInfo[$tableName] = [
                    'rows' => $count,
                    'size' => $this->getTableSize($tableName),
                ];
            }
            
            return $tableInfo;
        } catch (\Exception $e) {
            Log::error('Failed to get database tables', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get table size in a human-readable format
     */
    protected function getTableSize(string $table): string
    {
        try {
            $result = DB::select(
                "SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = ? 
                AND table_name = ?", 
                [DB::connection()->getDatabaseName(), $table]
            );
            
            return $result[0]->size_mb . ' MB';
        } catch (\Exception $e) {
            Log::error("Failed to get size for table {$table}", ['error' => $e->getMessage()]);
            return 'N/A';
        }
    }

    /**
     * Check cache connection status
     */
    protected function checkCacheConnection(): array
    {
        try {
            Cache::get('test');
            return [
                'status' => 'connected',
                'driver' => config('cache.default'),
                'store' => get_class(Cache::getStore()),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check Redis connection
     */
    protected function checkRedisConnection(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return [
                'status' => 'not_configured',
                'message' => 'Redis is not set as default cache or queue driver',
            ];
        }

        try {
            Redis::ping();
            return [
                'status' => 'connected',
                'version' => Redis::info()['redis_version'] ?? 'unknown',
                'used_memory' => $this->formatBytes(Redis::info()['used_memory'] ?? 0),
                'connected_clients' => Redis::info()['connected_clients'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Pinecone connection
     */
    protected function checkPineconeConnection(): array
    {
        try {
            $start = microtime(true);
            $info = $this->pineconeClient->getDebugInfo();
            $responseTime = round((microtime(true) - $start) * 1000, 2); // ms
            
            return [
                'status' => 'connected',
                'environment' => $info['pinecone']['environment'] ?? 'unknown',
                'index' => $info['pinecone']['index'] ?? 'unknown',
                'namespace' => $info['pinecone']['namespace'] ?? 'default',
                'response_time_ms' => $responseTime,
                'vector_count' => $info['pinecone']['stats']['totalVectorCount'] ?? 0,
                'index_fullness' => $info['pinecone']['stats']['indexFullness'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check embedding service status
     */
    protected function checkEmbeddingService(): array
    {
        try {
            $start = microtime(true);
            $vector = $this->embeddingService->generateEmbedding('test');
            $responseTime = round((microtime(true) - $start) * 1000, 2); // ms
            
            return [
                'status' => 'connected',
                'model' => $this->embeddingService->getModelName(),
                'dimensions' => $this->embeddingService->getDimension(),
                'response_time_ms' => $responseTime,
                'vector_length' => is_array($vector) ? count($vector) : 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'model' => $this->embeddingService->getModelName(),
            ];
        }
    }

    /**
     * Get OPcache status if enabled
     */
    protected function getOpcacheStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }
        
        $status = opcache_get_status(false);
        
        return [
            'enabled' => true,
            'memory_usage' => [
                'used' => $this->formatBytes($status['memory_usage']['used_memory']),
                'free' => $this->formatBytes($status['memory_usage']['free_memory']),
                'wasted' => $this->formatBytes($status['memory_usage']['wasted_memory']),
                'percent_used' => round($status['memory_usage']['used_memory_percentage'], 2) . '%',
            ],
            'statistics' => [
                'hits' => $status['opcache_statistics']['hits'],
                'misses' => $status['opcache_statistics']['misses'],
                'hit_rate' => round($status['opcache_statistics']['opcache_hit_rate'], 2) . '%',
                'num_cached_scripts' => $status['opcache_statistics']['num_cached_scripts'],
            ],
        ];
    }

    /**
     * Get recent query log
     */
    protected function getQueryLog(): array
    {
        if (!config('app.debug')) {
            return ['enabled' => false, 'message' => 'Query logging is only available in debug mode'];
        }
        
        try {
            $queries = DB::getQueryLog();
            return [
                'enabled' => true,
                'query_count' => count($queries),
                'queries' => array_slice($queries, -10), // Last 10 queries
            ];
        } catch (\Exception $e) {
            return [
                'enabled' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Load configuration with sensitive data redacted
     */
    protected function loadConfig(): void
    {
        $this->config = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'services' => [
                'pinecone' => [
                    'api_key' => config('pinecone.api_key') ? '***' . substr(config('pinecone.api_key'), -4) : null,
                    'environment' => config('pinecone.environment'),
                    'index' => config('pinecone.index'),
                    'namespace' => config('pinecone.namespace'),
                ],
                'ollama' => [
                    'base_url' => config('services.ollama.base_url'),
                    'model' => config('services.ollama.embed_model'),
                ],
            ],
        ];
    }
}
