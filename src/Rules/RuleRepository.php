<?php

namespace GDI\ApiLogger\Rules;

use GDI\ApiLogger\Models\ApiLoggerRule;
use Illuminate\Support\Facades\Cache;

/**
 * Payload and response storage rules, in the order they are matched:
 *   1. enabled rules in the api_logger_rules table (when apilogger.rules.database is on), by priority
 *   2. apilogger.{type}_rules from config
 * The global apilogger.{type}_keys list applies when nothing matches (see AbstractLogger::keysFor).
 */
class RuleRepository
{
    public const CACHE_KEY = 'apilogger.rules';

    /** Rules kept in memory for a few seconds so one request does not read the cache twice. */
    protected const MEMO_SECONDS = 10;

    protected ?array $memo = null;
    protected float $memoAt = 0;

    public function databaseEnabled(): bool
    {
        return (bool) config('apilogger.rules.database', false);
    }

    /**
     * @param  'payload'|'response'  $type
     * @return list<array{pattern: string, keys: array|false|null}>
     */
    public function rulesFor(string $type): array
    {
        $rules = $this->databaseEnabled() ? ($this->databaseRules()[$type] ?? []) : [];

        foreach ((array) config("apilogger.{$type}_rules", []) as $pattern => $keys) {
            $rules[] = [
                'pattern' => (string) $pattern,
                'keys'    => ($keys === false) ? false : (($keys === null || $keys === []) ? null : (array) $keys),
            ];
        }

        return $rules;
    }

    /**
     * Enabled database rules grouped by type. Never throws: a missing table or a
     * cache failure must not break logging.
     */
    protected function databaseRules(): array
    {
        if ($this->memo !== null && (microtime(true) - $this->memoAt) < self::MEMO_SECONDS) {
            return $this->memo;
        }

        try {
            $ttl  = (int) config('apilogger.rules.cache_ttl', 300);
            $load = fn () => $this->loadFromDatabase();
            $rules = $ttl > 0 ? Cache::remember(self::CACHE_KEY, $ttl, $load) : $load();
        } catch (\Throwable $e) {
            try {
                $rules = $this->loadFromDatabase();
            } catch (\Throwable $e) {
                logger()->warning('[ApiLogger] Could not load rules: ' . $e->getMessage());
                $rules = [];
            }
        }

        $this->memo   = $rules;
        $this->memoAt = microtime(true);

        return $rules;
    }

    protected function loadFromDatabase(): array
    {
        $grouped = ['payload' => [], 'response' => []];

        foreach (ApiLoggerRule::where('enabled', true)->ordered()->get() as $rule) {
            $grouped[$rule->type][] = ['pattern' => $rule->pattern, 'keys' => $rule->keysValue()];
        }

        return $grouped;
    }

    public function flush(): void
    {
        $this->memo = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    /**
     * Copy the config rules and global key lists into the table. Rules that
     * already exist (same type and pattern) are skipped. Global lists become a
     * "*" rule with priority 1000, so route rules (priority 100) win over them.
     *
     * @return int number of rules created
     */
    public function importFromConfig(): int
    {
        $created = 0;

        foreach (ApiLoggerRule::TYPES as $type) {
            $entries = [];
            foreach ((array) config("apilogger.{$type}_rules", []) as $pattern => $keys) {
                $entries[] = [(string) $pattern, $keys, 100];
            }
            $global = (array) config("apilogger.{$type}_keys", []);
            if ($global !== []) {
                $entries[] = ['*', $global, 1000];
            }

            foreach ($entries as [$pattern, $keys, $priority]) {
                if (ApiLoggerRule::where('type', $type)->where('pattern', $pattern)->exists()) {
                    continue;
                }

                $action = match (true) {
                    $keys === false                                   => 'none',
                    $keys === null, $keys === [], $keys === ['*']     => 'all',
                    default                                           => 'keep',
                };

                ApiLoggerRule::create([
                    'type'     => $type,
                    'pattern'  => $pattern,
                    'action'   => $action,
                    'keys'     => $action === 'keep' ? array_values((array) $keys) : null,
                    'priority' => $priority,
                    'enabled'  => true,
                    'note'     => 'Imported from config',
                ]);
                $created++;
            }
        }

        return $created;
    }
}
