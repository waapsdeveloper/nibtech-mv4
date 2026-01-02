<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSyncLog extends Model
{
    use HasFactory;
    
    protected $table = 'stock_sync_logs';
    
    protected $fillable = [
        'marketplace_id',
        'status',
        'total_records',
        'synced_count',
        'skipped_count',
        'error_count',
        'error_details',
        'summary',
        'started_at',
        'completed_at',
        'duration_seconds',
        'admin_id'
    ];
    
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_details' => 'array'
    ];
    
    /**
     * Get the marketplace
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id', 'id');
    }
    
    /**
     * Get the admin who triggered the sync
     */
    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }
}
