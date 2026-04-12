<?php

namespace GDI\ApiLogger\Drivers;

use Illuminate\Pagination\LengthAwarePaginator;

class FileLogger extends AbstractLogger
{
    protected string $path;
    protected int $maxEntries;

    public function __construct()
    {
        $this->path       = storage_path(config('apilogger.log_file', 'logs/apilogger.log'));
        $this->maxEntries = (int) config('apilogger.max_entries', 1000);
    }

    /** {@inheritdoc} */
    public function save(array $data): void
    {
        $entries   = $this->readAll();
        $entries[] = $data;

        // Prune oldest entries if limit exceeded
        if ($this->maxEntries > 0 && count($entries) > $this->maxEntries) {
            $entries = array_slice($entries, -$this->maxEntries);
        }

        $this->write($entries);
    }

    /** {@inheritdoc} */
    public function all(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $entries = array_reverse($this->readAll()); // newest first

        // Apply filters
        if (!empty($filters['method'])) {
            $entries = array_filter($entries, fn($e) => strtoupper($e['method'] ?? '') === strtoupper($filters['method']));
        }
        if (!empty($filters['status_code'])) {
            $entries = array_filter($entries, fn($e) => (string)($e['status_code'] ?? '') === (string)$filters['status_code']);
        }
        if (!empty($filters['url'])) {
            $entries = array_filter($entries, fn($e) => str_contains($e['url'] ?? '', $filters['url']));
        }
        $entries = array_values($entries);

        $total   = count($entries);
        $page    = LengthAwarePaginator::resolveCurrentPage();
        $items   = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    /** {@inheritdoc} */
    public function find(mixed $id): ?array
    {
        foreach ($this->readAll() as $entry) {
            if (($entry['id'] ?? null) === $id) {
                return $entry;
            }
        }
        return null;
    }

    /** {@inheritdoc} */
    public function clear(): void
    {
        $this->write([]);
    }

    // -------------------------------------------------------------------------

    protected function readAll(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }
        $contents = file_get_contents($this->path);
        if (empty($contents)) {
            return [];
        }
        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    protected function write(array $entries): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->path, json_encode($entries, JSON_PRETTY_PRINT));
    }
}
