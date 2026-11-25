<?php

namespace GDI\ApiLogger\Models;

use GDI\ApiLogger\Rules\RuleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A payload or response storage rule (see RuleRepository).
 *
 * @property string     $type     payload|response
 * @property string     $pattern  route name or path pattern
 * @property string     $action   keep|all|none
 * @property array|null $keys
 */
class ApiLoggerRule extends Model
{
    public const TYPES   = ['payload', 'response'];
    public const ACTIONS = ['keep', 'all', 'none'];

    protected $table = 'api_logger_rules';

    protected $fillable = ['type', 'pattern', 'action', 'keys', 'priority', 'enabled', 'note'];

    protected $casts = [
        'keys'     => 'array',
        'priority' => 'integer',
        'enabled'  => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = fn () => app(RuleRepository::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    /**
     * The value in config format: a key list, null (everything) or false (nothing).
     */
    public function keysValue(): array|false|null
    {
        return match ($this->action) {
            'none'  => false,
            'all'   => null,
            default => array_values($this->keys ?? []),
        };
    }
}
