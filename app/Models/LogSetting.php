<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel_name',
        'webhook_url',
        'log_level',
        'log_type',
        'keywords',
        'is_enabled',
        'description',
        'admin_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_enabled' => 'boolean',
        'admin_id' => 'integer',
    ];

    /**
     * Get active log settings for a specific log type
     */
    public static function getActiveForType(string $logType, string $logLevel = 'info'): ?self
    {
        $levelPriority = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $minLevel = $levelPriority[$logLevel] ?? 1;
        
        return self::where('log_type', $logType)
            ->where('is_enabled', true)
            ->get()
            ->filter(function ($setting) use ($minLevel, $levelPriority) {
                $settingLevel = $levelPriority[$setting->log_level] ?? 1;
                return $settingLevel <= $minLevel;
            })
            ->first();
    }

    /**
     * Get active log settings matching keywords in message
     */
    public static function getActiveForKeywords(string $message, string $logLevel = 'info'): ?self
    {
        $levelPriority = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $minLevel = $levelPriority[$logLevel] ?? 1;
        
        return self::where('is_enabled', true)
            ->whereNotNull('keywords')
            ->get()
            ->filter(function ($setting) use ($message, $minLevel, $levelPriority) {
                // Check log level
                $settingLevel = $levelPriority[$setting->log_level] ?? 1;
                if ($settingLevel > $minLevel) {
                    return false;
                }
                
                // Check keywords
                if ($setting->keywords && is_array($setting->keywords)) {
                    foreach ($setting->keywords as $keyword) {
                        if (stripos($message, $keyword) !== false) {
                            return true;
                        }
                    }
                }
                
                return false;
            })
            ->first();
    }
}
