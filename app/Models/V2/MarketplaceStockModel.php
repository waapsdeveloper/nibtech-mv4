<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceStockModel extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $table = 'marketplace_stock';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'variation_id',
        'marketplace_id',
        'listed_stock',
        'locked_stock',
        'available_stock',
        'buffer_percentage',
        'last_synced_at',
        'last_api_quantity',
        'admin_id',
        'formula',
        'reserve_old_value',
        'reserve_new_value',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'formula' => 'array',
        'reserve_old_value' => 'integer',
        'reserve_new_value' => 'integer',
        'buffer_percentage' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];
    
    /**
     * Get the variation that owns the marketplace stock
     */
    public function variation()
    {
        return $this->belongsTo(\App\Models\Variation_model::class, 'variation_id', 'id');
    }
    
    /**
     * Get the marketplace for this stock
     */
    public function marketplace()
    {
        return $this->belongsTo(\App\Models\Marketplace_model::class, 'marketplace_id', 'id');
    }
    
    /**
     * Get the admin who last updated this stock
     */
    public function admin()
    {
        return $this->belongsTo(\App\Models\Admin_model::class, 'admin_id', 'id');
    }
    
    /**
     * Get all locks for this marketplace stock
     */
    public function locks()
    {
        return $this->hasMany(MarketplaceStockLock::class, 'marketplace_stock_id')
            ->where('lock_status', 'locked');
    }
    
    /**
     * Get all locks (including released/consumed)
     */
    public function allLocks()
    {
        return $this->hasMany(MarketplaceStockLock::class, 'marketplace_stock_id');
    }
    
    /**
     * Get history for this marketplace stock
     */
    public function history()
    {
        return $this->hasMany(MarketplaceStockHistory::class, 'marketplace_stock_id')
            ->orderBy('created_at', 'desc');
    }
    
    /**
     * Calculate available stock with buffer
     */
    public function getAvailableStockWithBuffer()
    {
        $available = $this->available_stock ?? ($this->listed_stock - $this->locked_stock);
        $buffer = $this->buffer_percentage ?? 10.00;
        return max(0, floor($available * (1 - $buffer / 100)));
    }
    
    /**
     * Update available stock (recalculate)
     */
    public function updateAvailableStock()
    {
        $this->available_stock = max(0, $this->listed_stock - $this->locked_stock);
        $this->save();
    }
    
    /**
     * Boot the model and register observers
     */
    protected static function booted()
    {
        // Automatically recalculate available_stock whenever listed_stock or locked_stock changes
        static::saving(function ($marketplaceStock) {
            // Only recalculate if listed_stock or locked_stock is being changed
            if ($marketplaceStock->isDirty(['listed_stock', 'locked_stock'])) {
                $listedStock = $marketplaceStock->listed_stock ?? 0;
                $lockedStock = $marketplaceStock->locked_stock ?? 0;
                $marketplaceStock->available_stock = max(0, $listedStock - $lockedStock);
            }
        });
    }
}

