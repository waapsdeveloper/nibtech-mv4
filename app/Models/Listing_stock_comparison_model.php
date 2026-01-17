<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Listing_stock_comparison_model extends Model
{
    use HasFactory;
    
    protected $table = 'listing_stock_comparisons';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'variation_id',
        'variation_sku',
        'marketplace_id',
        'country_code',
        'api_stock',
        'our_stock',
        'pending_orders_count',
        'pending_orders_quantity',
        'stock_difference',
        'available_after_pending',
        'api_vs_pending_difference',
        'is_perfect',
        'has_discrepancy',
        'has_shortage',
        'has_excess',
        'notes',
        'compared_at',
    ];
    
    protected $casts = [
        'api_stock' => 'integer',
        'our_stock' => 'integer',
        'pending_orders_count' => 'integer',
        'pending_orders_quantity' => 'integer',
        'stock_difference' => 'integer',
        'available_after_pending' => 'integer',
        'api_vs_pending_difference' => 'integer',
        'is_perfect' => 'boolean',
        'has_discrepancy' => 'boolean',
        'has_shortage' => 'boolean',
        'has_excess' => 'boolean',
        'compared_at' => 'datetime',
    ];
    
    /**
     * Get the variation that owns this comparison
     */
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
    
    /**
     * Get the marketplace for this comparison
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id', 'id');
    }
    
    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        if ($this->is_perfect) {
            return 'success';
        } elseif ($this->has_shortage) {
            return 'danger';
        } elseif ($this->has_excess) {
            return 'warning';
        } else {
            return 'secondary';
        }
    }
    
    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        if ($this->is_perfect) {
            return 'Perfect Match';
        } elseif ($this->has_shortage) {
            return 'Shortage';
        } elseif ($this->has_excess) {
            return 'Excess';
        } else {
            return 'Discrepancy';
        }
    }
}
