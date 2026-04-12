<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Pagination\LengthAwarePaginator;
use GDI\ApiLogger\Models\ApiLog;

class DatabaseLogger extends AbstractLogger
{
    /** {@inheritdoc} */
    public function save(array $data): void
    {
        ApiLog::create([
            'log_id'      => $data['id'],
            'method'      => $data['method'],
            'url'         => $data['url'],
            'ip'          => $data['ip'],
            'status_code' => $data['status_code'],
            'duration'    => $data['duration'],
            'payload'     => $data['payload'],
            'response'    => $data['response'],
            'controller'  => $data['controller'],
            'action'      => $data['action'],
            'models'      => $data['models'],
            'user_id'     => $data['user_id'],
            'logged_at'   => $data['logged_at'],
        ]);
    }

    /** {@inheritdoc} */
    public function all(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = ApiLog::query()->latest('logged_at');

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
        $log = ApiLog::where('log_id', $id)->first();
        return $log ? $log->toArray() : null;
    }

    /** {@inheritdoc} */
    public function clear(): void
    {
        ApiLog::truncate();
    }
}
