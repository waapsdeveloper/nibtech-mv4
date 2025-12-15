<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingMarketplaceState extends Model
{
    use HasFactory;

    protected $table = 'listing_marketplace_state';
    
    protected $fillable = [
        'variation_id',
        'marketplace_id',
        'listing_id',
        'country_id',
        'min_handler',
        'price_handler',
        'buybox',
        'buybox_price',
        'min_price',
        'price',
        'last_updated_by',
        'last_updated_at',
    ];

    protected $casts = [
        'min_handler' => 'decimal:2',
        'price_handler' => 'decimal:2',
        'buybox' => 'boolean',
        'buybox_price' => 'decimal:2',
        'min_price' => 'decimal:2',
        'price' => 'decimal:2',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the variation that owns this state
     */
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id');
    }

    /**
     * Get the marketplace that owns this state
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id');
    }

    /**
     * Get the listing (if this is listing-level state)
     */
    public function listing()
    {
        return $this->belongsTo(Listing_model::class, 'listing_id');
    }

    /**
     * Get the admin who last updated this state
     */
    public function lastUpdatedBy()
    {
        return $this->belongsTo(Admin_model::class, 'last_updated_by');
    }

    /**
     * Get all history records for this state
     */
    public function history()
    {
        return $this->hasMany(ListingMarketplaceHistory::class, 'state_id');
    }

    /**
     * Get or create state for a variation + marketplace + listing combination
     */
    public static function getOrCreateState($variationId, $marketplaceId, $listingId = null, $countryId = null)
    {
        return self::firstOrCreate(
            [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'listing_id' => $listingId,
                'country_id' => $countryId,
            ]
        );
    }

    /**
     * Update state and track changes
     * @param array $data New values to update
     * @param string $changeType Type of change (listing, bulk, etc.)
     * @param string|null $reason Reason for change
     * @param array|null $explicitOldValues Optional explicit old values to use (for first-time changes)
     */
    public function updateState(array $data, $changeType = 'listing', $reason = null, $explicitOldValues = null)
    {
        $changedFields = [];
        $oldValues = [];

        $trackableFields = ['min_handler', 'price_handler', 'buybox', 'buybox_price', 'min_price', 'price'];

        foreach ($trackableFields as $field) {
            if (isset($data[$field])) {
                // Use explicit old value if provided (for first-time changes), otherwise use current state value
                $oldValue = isset($explicitOldValues[$field]) ? $explicitOldValues[$field] : $this->$field;
                $newValue = $data[$field];

                // Check if value actually changed
                if ($oldValue != $newValue) {
                    $oldValues[$field] = $oldValue;
                    $changedFields[] = $field;
                    $this->$field = $newValue;
                }
            }
        }

        // Only update if something changed
        if (empty($changedFields)) {
            return false;
        }

        $this->last_updated_by = session('user_id');
        $this->last_updated_at = now();
        $this->save();

        // Log each changed field to history
        foreach ($changedFields as $field) {
            ListingMarketplaceHistory::create([
                'state_id' => $this->id,
                'variation_id' => $this->variation_id,
                'marketplace_id' => $this->marketplace_id,
                'listing_id' => $this->listing_id,
                'country_id' => $this->country_id,
                'field_name' => $field,
                'old_value' => $oldValues[$field] ?? null,
                'new_value' => $data[$field],
                'change_type' => $changeType,
                'change_reason' => $reason,
                'admin_id' => session('user_id'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        return true;
    }
}
