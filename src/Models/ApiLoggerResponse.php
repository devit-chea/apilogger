<?php

namespace GDI\ApiLogger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLoggerResponse extends Model
{
    public $timestamps = false;

    protected $table = 'api_logger_responses';

    protected $fillable = [
        'api_logger_id',
        'response',
        'response_headers',
        'payload',
        'payload_raw',
        'headers',
    ];

    public function apiLogger(): BelongsTo
    {
        return $this->belongsTo(ApiLogger::class);
    }
}