<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HardwareTelemetrySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_uuid',
        'session_type',
        'source',
        'page_name',
        'page_path',
        'page_url',
        'status',
        'timeout_seconds',
        'user_agent',
        'metadata',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(HardwareTelemetryEvent::class);
    }
}
