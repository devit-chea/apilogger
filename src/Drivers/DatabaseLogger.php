<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Pagination\LengthAwarePaginator;
use GDI\ApiLogger\Models\ApiLogger;
use GDI\ApiLogger\Models\ApiLoggerResponse;

class DatabaseLogger extends AbstractLogger
{
    /** {@inheritdoc} */
    public function save(array $data): void
    {
        $apiLogger = ApiLogger::create([
            'log_id'      => $data['id'],
            'method'      => $data['method'],
            'url'         => $data['url'],
            'ip'          => $data['ip'],
            'status_code' => $data['status_code'],
            'duration'    => $data['duration'],
            'controller'  => $data['controller'],
            'action'      => $data['action'],
            'models'      => $data['models'],
            'user_id'     => $data['user_id'],
            'logged_at'   => $data['logged_at'],
        ]);

        ApiLoggerResponse::create([
            'api_logger_id'   => $apiLogger->id,
            'response'        => $data['response'] ?? null,
            'response_headers'=> $data['response_headers'] ?? null,
            'payload'         => $data['payload'] ?? null,
            'payload_raw'     => $data['payload_raw'] ?? null,
            'headers'         => $data['headers'] ?? null,
        ]);
    }

    /** {@inheritdoc} */
    public function all(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = ApiLogger::query()->latest('logged_at');

        if (!empty($filters['method'])) {
            $query->method($filters['method']);
        }
        if (!empty($filters['status_code'])) {
            $query->status((int) $filters['status_code']);
        }
        if (!empty($filters['url'])) {
            $query->url($filters['url']);
        }

        return $query->paginate($perPage);
    }

    /** {@inheritdoc} */
    public function find(mixed $id): ?array
    {
        $log = ApiLogger::where('log_id', $id)->with('response')->first();
        
        if (!$log) {
            return null;
        }

        $data = $log->toArray();
        
        if ($log->response) {
            $data['response'] = $log->response->response;
            $data['response_headers'] = $log->response->response_headers;
            $data['payload'] = $log->response->payload;
            $data['payload_raw'] = $log->response->payload_raw;
            $data['headers'] = $log->response->headers;
        }
        
        return $data;
    }

    /** {@inheritdoc} */
    public function clear(): void
    {
        // Cascade will delete responses automatically due to foreign key
        ApiLogger::truncate();
    }
}
