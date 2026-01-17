<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock_deduction_log_model extends Model
{
    use HasFactory;
    
    protected $table = 'stock_deduction_logs';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'variation_id',
        'marketplace_id',
        'order_id',
        'order_reference_id',
        'variation_sku',
        'before_variation_stock',
        'before_marketplace_stock',
        'after_variation_stock',
        'after_marketplace_stock',
        'deduction_reason',
        'order_status',
        'is_new_order',
        'old_order_status',
        'notes',
        'deduction_at',
    ];
    
    protected $casts = [
        'is_new_order' => 'boolean',
        'deduction_at' => 'datetime',
        'before_variation_stock' => 'integer',
        'before_marketplace_stock' => 'integer',
        'after_variation_stock' => 'integer',
        'after_marketplace_stock' => 'integer',
        'order_status' => 'integer',
        'old_order_status' => 'integer',
    ];
    
    /**
     * Get the variation that owns this deduction log
     */
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
    
    /**
     * Get the order associated with this deduction
     */
    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }
    
    /**
     * Get the marketplace for this deduction
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id', 'id');
    }
    
    /**
     * Get status name for display
     */
    public function getOrderStatusNameAttribute()
    {
        $statuses = [
            1 => 'To Be Treated',
            2 => 'Awaiting Shipment',
            3 => 'Shipped',
            4 => 'Cancelled',
            5 => 'Refunded Before Delivery',
            6 => 'Reimbursed After Delivery',
        ];
        
        return $statuses[$this->order_status] ?? 'Unknown';
    }
    
    /**
     * Get deduction reason label
     */
    public function getDeductionReasonLabelAttribute()
    {
        $reasons = [
            'new_order_status_1' => 'New Order (Status 1)',
            'status_change_1_to_2' => 'Status Change (1 → 2)',
        ];
        
        return $reasons[$this->deduction_reason] ?? $this->deduction_reason;
    }
}
