<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceStockLock extends Model
{
    use HasFactory;
    
    protected $table = 'marketplace_stock_locks';
    
    protected $fillable = [
        'marketplace_stock_id',
        'variation_id',
        'marketplace_id',
        'order_id',
        'order_item_id',
        'quantity_locked',
        'lock_status',
        'locked_at',
        'released_at',
        'consumed_at',
    ];
    
    protected $casts = [
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
    
    public function marketplaceStock()
    {
        return $this->belongsTo(MarketplaceStockModel::class, 'marketplace_stock_id');
    }
    
    public function order()
    {
        return $this->belongsTo(\App\Models\Order_model::class, 'order_id');
    }
    
    public function orderItem()
    {
        return $this->belongsTo(\App\Models\Order_item_model::class, 'order_item_id');
    }
}

