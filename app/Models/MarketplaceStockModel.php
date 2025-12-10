<?php

namespace App\Models;

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
    ];
    
    /**
     * Get the variation that owns the marketplace stock
     */
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
    
    /**
     * Get the marketplace for this stock
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id', 'id');
    }
    
    /**
     * Get the admin who last updated this stock
     */
    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }
}
