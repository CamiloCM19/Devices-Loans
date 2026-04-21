<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HardwareTelemetryEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'hardware_telemetry_session_id',
        'event_uuid',
        'session_uuid',
        'session_type',
        'channel',
        'event_type',
        'level',
        'source',
        'uid',
        'correlation_id',
        'message',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(HardwareTelemetrySession::class, 'hardware_telemetry_session_id');
    }
}
