<?php

namespace GDI\ApiLogger\Contracts;

interface ApiLoggerInterface
{
    /**
     * Save a log entry.
     *
     * @param array $data
     * @return void
     */
    public function save(array $data): void;

    /**
     * Retrieve paginated log entries.
     *
     * @param int $perPage
     * @param array $filters  e.g. ['method' => 'POST', 'status' => 500]
     * @return mixed  Should return a paginator or array with 'data' and 'total'
     */
    public function all(int $perPage = 20, array $filters = []): mixed;

    /**
     * Retrieve a single log entry by ID/key.
     *
     * @param mixed $id
     * @return array|null
     */
    public function find(mixed $id): ?array;

    /**
     * Delete all log entries.
     *
     * @return void
     */
    public function clear(): void;
}
