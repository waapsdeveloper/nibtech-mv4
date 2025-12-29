<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketplaceStockHistory extends Model
{
    use HasFactory;
    
    protected $table = 'marketplace_stock_history';
    
    protected $fillable = [
        'marketplace_stock_id',
        'variation_id',
        'marketplace_id',
        'listed_stock_before',
        'listed_stock_after',
        'locked_stock_before',
        'locked_stock_after',
        'available_stock_before',
        'available_stock_after',
        'quantity_change',
        'change_type',
        'order_id',
        'order_item_id',
        'reference_id',
        'admin_id',
        'notes',
    ];
    
    public function marketplaceStock()
    {
        return $this->belongsTo(MarketplaceStockModel::class, 'marketplace_stock_id');
    }
    
    public function order()
    {
        return $this->belongsTo(\App\Models\Order_model::class, 'order_id');
    }
}

