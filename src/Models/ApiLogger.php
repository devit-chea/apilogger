<?php

namespace GDI\ApiLogger\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLogger extends Model
{
    public $timestamps = false;

    protected $table = 'api_loggers';

    protected $fillable = [
        'log_id',
        'method',
        'url',
        'ip',
        'status_code',
        'duration',
        'payload',
        'response',
        'controller',
        'action',
        'models',
        'user_id',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function scopeMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    public function scopeStatus($query, int $status)
    {
        return $query->where('status_code', $status);
    }

    public function scopeUrl($query, string $search)
    {
        return $query->where('url', 'like', "%{$search}%");
    }
}
