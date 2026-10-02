<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineRun extends Model
{
    protected $fillable = [
        'run_id',
        'type',
        'started_at',
        'finished_at',
        'status',
        'total_items',
        'failed_items',
        'triggered_by',
        'error_summary',
        'settings_snapshot',
    ];

    protected $casts = [
        'started_at'        => 'datetime',
        'finished_at'       => 'datetime',
        'settings_snapshot' => 'array',
        'total_items'       => 'integer',
        'failed_items'      => 'integer',
    ];

    public function scopeRunning($query)
    {
        return $query->where('status', 'RUNNING');
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (!$this->finished_at || !$this->started_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->finished_at);
    }
}
