<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;

abstract class AbstractLogger implements ApiLoggerInterface
{
    /**
     * Build a structured log data array from the request and response.
     *
     * @param Request        $request
     * @param mixed          $response
     * @param float          $startTime   microtime(true) from before the request
     * @return array
     */
    public function buildLogData(Request $request, mixed $response, float $startTime): array
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

        $payload = $this->filterHidden($request->all());

        $controller = null;
        $method     = null;
        $action = $request->route()?->getActionName();
        if ($action && str_contains($action, '@')) {
            [$controller, $method] = explode('@', class_basename($action));
        } elseif ($action) {
            $controller = $action;
        }

        $responseBody = null;
        $statusCode   = null;
        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $statusCode = $response->getStatusCode();
            if (config('apilogger.log_response', true)) {
                $body = $response->getContent();
                $max  = (int) config('apilogger.max_response_length', 5000);
                $responseBody = ($max > 0 && strlen($body) > $max)
                    ? substr($body, 0, $max) . '...[truncated]'
                    : $body;
            }
        }

        // Extract Eloquent models touched during the request (if query log is on)
        $models = $this->extractModels();

        return [
            'id'          => $this->generateId(),
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'ip'          => $request->ip(),
            'status_code' => $statusCode,
            'duration'    => $duration,
            'payload'     => json_encode($payload),
            'response'    => $responseBody,
            'controller'  => $controller,
            'action'      => $method,
            'models'      => implode(', ', $models),
            'user_id'     => auth()->id(),
            'logged_at'   => now()->toDateTimeString(),
        ];
    }

    /**
     * Replace hidden fields with "***".
     */
    protected function filterHidden(array $data): array
    {
        $hidden = config('apilogger.hidden_fields', []);
        foreach ($hidden as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '***';
            }
        }
        return $data;
    }

    /**
     * Attempt to extract model class names from the DB query log.
     * Requires DB::enableQueryLog() to be called earlier (done in middleware).
     */
    protected function extractModels(): array
    {
        $models = [];
        try {
            $queries = \DB::getQueryLog();
            foreach ($queries as $query) {
                // Extract table names from queries
                if (preg_match('/\bfrom\s+["`]?(\w+)["`]?/i', $query['query'], $matches)) {
                    $table = $matches[1];
                    // Attempt to map table -> model name (simple pascal-case guess)
                    $model = str($table)->singular()->studly()->toString();
                    $models[$model] = $model;
                }
            }
        } catch (\Throwable $e) {
            // Query log unavailable — silently skip
        }
        return array_values($models);
    }

    /**
     * Generate a unique log entry ID.
     */
    protected function generateId(): string
    {
        return uniqid('log_', true);
    }
}
