<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartsPurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parts_purchase_orders';

    protected $fillable = [
        'reference_id',
        'reference',
        'status',
        'currency',
        'customer_id',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public const STATUS_PENDING = 2;
    public const STATUS_APPROVED = 3;

    public function partBatches()
    {
        return $this->hasMany(PartBatch::class, 'parts_purchase_order_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(Admin_model::class, 'processed_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer_model::class, 'customer_id');
    }

    public function currencyRelation()
    {
        return $this->belongsTo(Currency_model::class, 'currency', 'id');
    }
}
