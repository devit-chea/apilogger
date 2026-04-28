<?php

namespace GDI\ApiLogger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;
use GDI\ApiLogger\Jobs\WriteApiLog;

class ApiLoggerMiddleware
{
    public function __construct(protected ApiLoggerInterface $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Enable DB query log so we can extract touched models
        try {
            DB::enableQueryLog();
        } catch (\Throwable) {}

        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        try {
            $data = $this->logger->buildLogData($request, $response, $startTime);
            
            // Skip logging if route is excluded
            if (!empty($data)) {
                $this->dispatchLog($data);
            }
        } catch (\Throwable $e) {
            // Never let logging break the application
            logger()->error('[ApiLogger] Failed to log request: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Dispatch the log job to queue or save directly.
     */
    protected function dispatchLog(array $data): void
    {
        $queueEnabled = config('apilogger.queue.enabled', false);
        
        if ($queueEnabled) {
            WriteApiLog::dispatch($data);
        } else {
            $this->logger->save($data);
        }
    }
}
