<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ListingCacheService
{
    /**
     * Cache key prefix for variation data
     */
    private const CACHE_PREFIX = 'v2_listing_variation_';
    
    /**
     * Cache key prefix for page data
     */
    private const PAGE_CACHE_PREFIX = 'v2_listing_page_';
    
    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Store variation data in cache
     * 
     * @param array $variationData Array of variation data with calculated stats
     * @param string $pageKey Unique key for this page (based on filters)
     * @return void
     */
    public function cacheVariationData(array $variationData, string $pageKey): void
    {
        try {
            // Store each variation individually for quick access
            foreach ($variationData as $variation) {
                $variationId = $variation['id'] ?? null;
                if ($variationId) {
                    $key = self::CACHE_PREFIX . $variationId;
                    Cache::put($key, $variation, self::CACHE_TTL);
                }
            }
            
            // Also store the page mapping (which variation IDs belong to this page)
            $variationIds = array_column($variationData, 'id');
            Cache::put(
                self::PAGE_CACHE_PREFIX . $pageKey,
                $variationIds,
                self::CACHE_TTL
            );
            
            Log::debug('Cached variation data', [
                'count' => count($variationData),
                'page_key' => $pageKey
            ]);
        } catch (\Exception $e) {
            Log::error('Error caching variation data: ' . $e->getMessage());
        }
    }

    /**
     * Get cached variation data by ID
     * 
     * @param int $variationId
     * @return array|null
     */
    public function getCachedVariation(int $variationId): ?array
    {
        try {
            $key = self::CACHE_PREFIX . $variationId;
            return Cache::get($key);
        } catch (\Exception $e) {
            Log::error('Error getting cached variation: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get multiple cached variations
     * 
     * @param array $variationIds
     * @return array Array of variation data (only cached ones)
     */
    public function getCachedVariations(array $variationIds): array
    {
        $cached = [];
        foreach ($variationIds as $id) {
            $data = $this->getCachedVariation($id);
            if ($data) {
                $cached[] = $data;
            }
        }
        return $cached;
    }

    /**
     * Generate page cache key from request filters
     * 
     * @param array $filters
     * @return string
     */
    public function generatePageKey(array $filters): string
    {
        // Create a unique key based on filters
        ksort($filters); // Sort for consistency
        return md5(json_encode($filters));
    }

    /**
     * Clear cache for specific variation
     * 
     * @param int $variationId
     * @return void
     */
    public function clearVariationCache(int $variationId): void
    {
        try {
            $key = self::CACHE_PREFIX . $variationId;
            Cache::forget($key);
        } catch (\Exception $e) {
            Log::error('Error clearing variation cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear all listing caches (use with caution)
     * 
     * @return void
     */
    public function clearAllCaches(): void
    {
        try {
            // Note: This is a simple implementation
            // For production with Redis, you might want to use tags or patterns
            Cache::flush();
        } catch (\Exception $e) {
            Log::error('Error clearing all caches: ' . $e->getMessage());
        }
    }

    /**
     * Check if variation is cached
     * 
     * @param int $variationId
     * @return bool
     */
    public function isCached(int $variationId): bool
    {
        return $this->getCachedVariation($variationId) !== null;
    }
}

