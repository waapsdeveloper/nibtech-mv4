<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceDefaultFormula extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'marketplace_default_formulas';
    
    protected $fillable = [
        'marketplace_id',
        'formula',
        'min_threshold',
        'max_threshold',
        'min_stock_required',
        'is_active',
        'admin_id',
        'notes',
    ];

    protected $casts = [
        'formula' => 'array',
        'min_threshold' => 'integer',
        'max_threshold' => 'integer',
        'min_stock_required' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the marketplace that owns this default formula
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id', 'id');
    }

    /**
     * Get the admin who created/updated this default
     */
    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }

    /**
     * Get active default formula for a marketplace
     */
    public static function getActiveForMarketplace($marketplaceId)
    {
        return self::where('marketplace_id', $marketplaceId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
