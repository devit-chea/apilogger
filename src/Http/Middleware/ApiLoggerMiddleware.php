<?php

namespace GDI\ApiLogger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;

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
            $this->logger->save($data);
        } catch (\Throwable $e) {
            // Never let logging break the application
            logger()->error('[ApiLogger] Failed to log request: ' . $e->getMessage());
        }

        return $response;
    }
}
