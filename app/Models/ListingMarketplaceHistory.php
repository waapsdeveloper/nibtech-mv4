<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingMarketplaceHistory extends Model
{
    use HasFactory;

    protected $table = 'listing_marketplace_history';

    protected $fillable = [
        'state_id',
        'variation_id',
        'marketplace_id',
        'listing_id',
        'country_id',
        'field_name',
        'old_value',
        'new_value',
        'change_type',
        'change_reason',
        'admin_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Use changed_at as the timestamp field
    public $timestamps = false;
    
    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->changed_at)) {
                $model->changed_at = now();
            }
        });
    }

    /**
     * Get the state that this history belongs to
     */
    public function state()
    {
        return $this->belongsTo(ListingMarketplaceState::class, 'state_id');
    }

    /**
     * Get the variation that owns this history
     */
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id');
    }

    /**
     * Get the marketplace that owns this history
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id');
    }

    /**
     * Get the listing (if this is listing-level history)
     */
    public function listing()
    {
        return $this->belongsTo(Listing_model::class, 'listing_id');
    }

    /**
     * Get the admin who made this change
     */
    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }

    /**
     * Scope to filter by variation and marketplace
     */
    public function scopeForVariationMarketplace($query, $variationId, $marketplaceId)
    {
        return $query->where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId);
    }

    /**
     * Scope to filter by field name
     */
    public function scopeForField($query, $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('changed_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by change type
     */
    public function scopeChangeType($query, $type)
    {
        return $query->where('change_type', $type);
    }

    /**
     * Get formatted field value (for display)
     */
    public function getFormattedOldValueAttribute()
    {
        return $this->formatValue($this->old_value);
    }

    /**
     * Get formatted new value (for display)
     */
    public function getFormattedNewValueAttribute()
    {
        return $this->formatValue($this->new_value);
    }

    /**
     * Format value based on field type
     */
    private function formatValue($value)
    {
        if ($value === null) {
            return 'N/A';
        }

        // Format boolean values
        if (in_array($this->field_name, ['buybox'])) {
            return $value ? 'Yes' : 'No';
        }

        // Format decimal values
        if (in_array($this->field_name, ['min_handler', 'price_handler', 'buybox_price', 'min_price', 'price'])) {
            return number_format((float)$value, 2, '.', '');
        }

        return $value;
    }

    /**
     * Get human-readable field name
     */
    public function getFieldLabelAttribute()
    {
        $labels = [
            'min_handler' => 'Min Handler',
            'price_handler' => 'Price Handler',
            'buybox' => 'BuyBox',
            'buybox_price' => 'BuyBox Price',
            'min_price' => 'Min Price',
            'price' => 'Price',
        ];

        return $labels[$this->field_name] ?? ucfirst(str_replace('_', ' ', $this->field_name));
    }
}
