<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Http\Request;
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
        // Check if route should be logged
        if (!$this->shouldLogRoute($request)) {
            return [];
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

        $payload = $this->filterHidden($request->all());
        $payloadJson = json_encode($payload);
        $maxPayload = (int) config('apilogger.max_payload_length', 5000);
        if ($maxPayload > 0 && strlen($payloadJson) > $maxPayload) {
            $payloadJson = substr($payloadJson, 0, $maxPayload) . '...[truncated]';
        }

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
                $truncated = ($max > 0 && strlen($body) > $max)
                    ? substr($body, 0, $max) . '...[truncated]'
                    : $body;
                
                // Apply response filtering
                $responseBody = $this->filterResponse($truncated);
            }
        }

        // Extract Eloquent models touched during the request (if query log is on)
        $models = $this->extractModels();

        return [
            'id'          => $this->generateId(),
            'method'      => $request->method(),
            'url'         => $this->truncateUrl($request->fullUrl()),
            'ip'          => $request->ip(),
            'status_code' => $statusCode,
            'duration'    => $duration,
            'payload'     => $payloadJson,
            'response'    => $responseBody,
            'response_headers' => config('apilogger.log_response_headers', false) 
                ? $this->extractResponseHeaders($response) 
                : null,
            'payload_raw' => null,
            'headers'     => config('apilogger.log_request_headers', false)
                ? json_encode($request->headers->all())
                : null,
            'controller'  => $controller,
            'action'      => $method,
            'models'      => implode(', ', $models),
            'user_id'     => auth()->id(),
            'logged_at'   => now()->toDateTimeString(),
        ];
    }

    /**
     * Replace hidden fields with "***".
     * Supports nested dot notation: 'user.password'
     */
    protected function filterHidden(array $data): array
    {
        $hidden = config('apilogger.hidden_fields', []);
        $extraHidden = config('apilogger.exclude_payload_keys', []);
        $allHidden = array_merge($hidden, $extraHidden);
        
        foreach ($allHidden as $field) {
            if (str_contains($field, '.')) {
                // Nested key like 'user.password'
                $this->maskNestedKey($data, $field);
            } elseif (array_key_exists($field, $data)) {
                $data[$field] = '***';
            }
        }
        return $data;
    }

    /**
     * Mask nested key with "***" using dot notation.
     */
    protected function maskNestedKey(array &$data, string $key): void
    {
        $parts = explode('.', $key);
        $current = &$data;
        
        foreach ($parts as $i => $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return;
            }
            
            if ($i === count($parts) - 1) {
                $current[$part] = '***';
            } else {
                $current = &$current[$part];
            }
        }
    }

    /**
     * Filter response body by removing excluded keys.
     */
    protected function filterResponse(string|null $responseBody): string|null
    {
        if (empty($responseBody)) {
            return $responseBody;
        }

        try {
            $data = json_decode($responseBody, true);
        } catch (\Throwable $e) {
            // Invalid JSON, return as-is
            return $responseBody;
        }
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $responseBody;
        }

        // Apply key exclusions
        $excludeKeys = config('apilogger.exclude_response_keys', []);
        $data = $this->removeKeys($data, $excludeKeys);

        // Apply pattern exclusions
        $patterns = config('apilogger.exclude_response_patterns', []);
        foreach ($patterns as $pattern) {
            $data = $this->removeByPattern($data, $pattern);
        }

        try {
            return json_encode($data);
        } catch (\Throwable $e) {
            return $responseBody;
        }
    }

    /**
     * Recursively remove keys from array.
     */
    protected function removeKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (str_contains($key, '.')) {
                // Nested key like 'data.user.password'
                $this->removeNestedKey($data, $key);
            } else {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * Remove nested key using dot notation.
     */
    protected function removeNestedKey(array &$data, string $key): void
    {
        $parts = explode('.', $key);
        $current = &$data;
        
        foreach ($parts as $i => $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return;
            }
            
            if ($i === count($parts) - 1) {
                unset($current[$part]);
            } else {
                $current = &$current[$part];
            }
        }
    }

    /**
     * Remove keys by pattern (supports wildcards).
     */
    protected function removeByPattern(array $data, string $pattern): array
    {
        // Convert pattern to regex: data.*.password -> data\.\w+\.password
        $regex = '/^' . str_replace(['.', '*'], ['\.', '\w+'], $pattern) . '$/';
        
        return $this->removeKeysByRegex($data, $regex);
    }

    /**
     * Recursively remove keys matching regex pattern.
     */
    protected function removeKeysByRegex(array $data, string $regex): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeKeysByRegex($value, $regex);
            }
            
            if (preg_match($regex, $key)) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * Check if route should be logged.
     */
    protected function shouldLogRoute(Request $request): bool
    {
        $url = $request->fullUrl();
        $path = $request->path();
        
        // Check exclude routes
        $excludeRoutes = config('apilogger.exclude_routes', []);
        foreach ($excludeRoutes as $route) {
            if (str_contains($path, $route) || preg_match('/' . $route . '/', $path)) {
                return false;
            }
        }
        
        // Check include routes (if specified, only log matching routes)
        $includeRoutes = config('apilogger.include_routes', []);
        if (!empty($includeRoutes)) {
            foreach ($includeRoutes as $route) {
                if (str_contains($path, $route) || preg_match('/' . $route . '/', $path)) {
                    return true;
                }
            }
            return false;
        }
        
        return true;
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

    /**
     * Truncate URL to prevent overly long entries.
     */
    protected function truncateUrl(string $url): string
    {
        $maxLength = (int) config('apilogger.max_url_length', 500);
        if ($maxLength > 0 && strlen($url) > $maxLength) {
            return substr($url, 0, $maxLength) . '...[truncated]';
        }
        return $url;
    }

    /**
     * Extract headers from response.
     */
    protected function extractResponseHeaders(mixed $response): ?string
    {
        if (!$response instanceof \Symfony\Component\HttpFoundation\Response) {
            return null;
        }
        
        $headers = $response->headers->all();
        // Filter out content-type and common large headers
        unset($headers['content-type'], $headers['content-length']);
        
        return json_encode($headers);
    }
}
