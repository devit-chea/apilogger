<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use GDI\ApiLogger\Rules\RuleRepository;
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

        // Read the query log first, so the logger's own rule lookup is not listed as a model.
        $models = $this->extractModels();

        $payloadJson = $this->preparePayload($request);

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
                $responseBody = $this->prepareResponseBody($request, $response->getContent());
            }
        }

        // Extract Eloquent models touched during the request (if query log is on)
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
     * Build the response body to store: keep only the configured keys, remove
     * excluded keys, then cut to max_response_length. Filtering runs on the full
     * body so it still works when the body is longer than the limit.
     */
    protected function prepareResponseBody(Request $request, string|false|null $body): ?string
    {
        if ($body === false || $body === null || $body === '') {
            return null;
        }

        $keys = $this->responseKeysFor($request);
        if ($keys === false) {
            return null; // rule says: do not store the body for this route
        }

        $body = $this->filterResponse($body, $keys);

        $max = (int) config('apilogger.max_response_length', 5000);

        return ($max > 0 && strlen($body) > $max)
            ? substr($body, 0, $max) . '...[truncated]'
            : $body;
    }

    /**
     * Build the request payload to store: keep only the configured keys, mask
     * hidden fields, then cut to max_payload_length.
     */
    protected function preparePayload(Request $request): ?string
    {
        $keys = $this->keysFor($request, 'payload');
        if ($keys === false) {
            return null; // rule says: do not store the payload for this route
        }

        $payload = $request->all();
        if ($keys !== null) {
            $payload = $this->onlyKeys($payload, $keys);
        }

        $json = json_encode($this->filterHidden($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        $max = (int) config('apilogger.max_payload_length', 5000);

        return ($max > 0 && strlen($json) > $max)
            ? substr($json, 0, $max) . '...[truncated]'
            : $json;
    }

    /**
     * Which response keys to keep for this request.
     */
    protected function responseKeysFor(Request $request): array|false|null
    {
        return $this->keysFor($request, 'response');
    }

    /**
     * Resolve the keys to keep for 'payload' or 'response'.
     *
     * Returns false to store nothing, null to keep everything, or a list of
     * dot-notation keys. The first matching rule wins (database rules, then
     * config rules; matched against the route name, then the path, with *
     * wildcards); otherwise the global {type}_keys list applies.
     */
    protected function keysFor(Request $request, string $type): array|false|null
    {
        $name = $request->route()?->getName();
        $path = $request->path();

        foreach (app(RuleRepository::class)->rulesFor($type) as $rule) {
            $pattern = $rule['pattern'];
            if (($name !== null && Str::is($pattern, $name)) || Str::is(ltrim($pattern, '/'), $path)) {
                $keys = $rule['keys'];

                return ($keys === false || $keys === null) ? $keys : (in_array('*', $keys, true) ? null : $keys);
            }
        }

        $keys = (array) config("apilogger.{$type}_keys", []);

        return $keys === [] ? null : $keys;
    }

    /**
     * Keep only the given dot-notation keys. "*" matches every key at one level,
     * e.g. "data.*.id" keeps the id of every item in data.
     */
    protected function onlyKeys(array $data, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $this->copyPath($data, $result, explode('.', (string) $key));
        }

        return $result;
    }

    protected function copyPath(array $source, array &$target, array $segments): void
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($source as $key => $value) {
                if ($segments === []) {
                    $target[$key] = $value;
                } elseif (is_array($value)) {
                    if (!isset($target[$key]) || !is_array($target[$key])) {
                        $target[$key] = [];
                    }
                    $this->copyPath($value, $target[$key], $segments);
                }
            }

            return;
        }

        if (!array_key_exists($segment, $source)) {
            return;
        }

        if ($segments === []) {
            $target[$segment] = $source[$segment];

            return;
        }

        if (!is_array($source[$segment])) {
            return;
        }

        $created = !isset($target[$segment]) || !is_array($target[$segment]);
        if ($created) {
            $target[$segment] = [];
        }
        $this->copyPath($source[$segment], $target[$segment], $segments);
        if ($created && $target[$segment] === []) {
            unset($target[$segment]); // path did not exist further down
        }
    }

    /**
     * Filter a JSON response body: keep only $keys (when given), then remove
     * excluded keys and patterns. Non-JSON bodies are returned unchanged.
     */
    protected function filterResponse(string|null $responseBody, ?array $keys = null): string|null
    {
        if (empty($responseBody)) {
            return $responseBody;
        }

        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $responseBody;
        }

        if ($keys !== null) {
            $data = $this->onlyKeys($data, $keys);
        }

        // Apply key exclusions
        $excludeKeys = config('apilogger.exclude_response_keys', []);
        $data = $this->removeKeys($data, $excludeKeys);

        // Apply pattern exclusions
        $patterns = config('apilogger.exclude_response_patterns', []);
        foreach ($patterns as $pattern) {
            $data = $this->removeByPattern($data, $pattern);
        }

        // Unescaped unicode/slashes: Khmer text takes 3 bytes instead of 6 per character.
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? $responseBody : $encoded;
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
