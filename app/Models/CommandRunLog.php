<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per command slug. Each run overwrites the same slug with last run info.
 */
class CommandRunLog extends Model
{
    protected $table = 'command_run_logs';

    protected $fillable = [
        'slug',
        'last_started_at',
        'last_completed_at',
        'total_processed',
        'processed_ok',
        'processed_failed',
        'status',
        'last_note',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_completed_at' => 'datetime',
    ];

    /**
     * Record command start. Creates or updates the row for this slug.
     */
    public static function recordStart(string $slug): self
    {
        return self::updateOrCreate(
            ['slug' => $slug],
            [
                'last_started_at' => now(),
                'last_completed_at' => null,
                'status' => 'running',
            ]
        );
    }

    /**
     * Record command end. Updates the row for this slug with last run results.
     */
    public static function recordEnd(
        string $slug,
        int $totalProcessed = 0,
        int $processedOk = 0,
        int $processedFailed = 0,
        ?string $note = null,
        string $status = 'completed'
    ): self {
        $log = self::firstOrNew(['slug' => $slug]);
        $log->last_completed_at = now();
        $log->total_processed = $totalProcessed;
        $log->processed_ok = $processedOk;
        $log->processed_failed = $processedFailed;
        $log->status = in_array($status, ['completed', 'failed', 'cancelled']) ? $status : 'completed';
        if ($note !== null) {
            $log->last_note = $note;
        }
        $log->save();
        return $log;
    }

    /**
     * Get duration in seconds (if both start and end are set).
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->last_started_at && $this->last_completed_at) {
            return (int) $this->last_started_at->diffInSeconds($this->last_completed_at);
        }
        return null;
    }
}
