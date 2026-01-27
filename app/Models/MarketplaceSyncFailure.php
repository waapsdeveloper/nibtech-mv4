<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncFailure extends Model
{
    use HasFactory;
    
    protected $table = 'marketplace_sync_failures';
    
    protected $fillable = [
        'variation_id',
        'sku',
        'marketplace_id',
        'error_reason',
        'error_message',
        'is_posted_on_marketplace',
        'failure_count',
        'first_failed_at',
        'last_attempted_at',
    ];
    
    protected $casts = [
        'is_posted_on_marketplace' => 'boolean',
        'failure_count' => 'integer',
        'first_failed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
    ];
    
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id');
    }
    
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id');
    }
}
