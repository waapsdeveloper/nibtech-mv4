# Query Comparison: Original vs V2 Listing

## Original Listing Controller Query (`ListingController.php`)

### Base Query:
```php
$query = Variation_model::with([
    'listings',
    'listings.country_id',
    'listings.currency',
    'listings.marketplace',
    'product',
    'available_stocks',
    'pending_orders',
    'storage_id',
    'color_id',
    'grade_id',
]);
```

### Filters Applied (in order):
1. `reference_id` - where clause
2. `variation_id` - where clause
3. `category` - whereHas on product
4. `brand` - whereHas on product
5. `marketplace` - whereHas on listings
6. `product` - where clause on product_id
7. `product_name` (via productSearch) - whereIn on product_id
8. `product_name` (via storageSearch) - whereIn on storage
9. `sku` - where clause
10. `color` - where clause
11. `storage` - where clause
12. `grade` - whereIn
13. `topup` - whereHas on listed_stock_verifications
14. `listed_stock` - where clause (> 0 or <= 0)
15. `available_stock` - whereHas with withCount and havingRaw
16. `state` - whereIn([2, 3]) if null, or where if specified
17. `sale_40` - withCount and having
18. `handler_status` - whereHas on listings (with special logic for status 2)
19. `process_id` + `special='show_only'` - whereHas on process_stocks
20. **`whereNotNull('sku')`** - Applied at the end

### Sorting (Applied AFTER all filters):
- **Sort 4**: Join products → orderBy products.model ASC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC → select('variation.*')
- **Sort 3**: Join products → orderBy products.model DESC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC → select('variation.*')
- **Sort 2**: orderBy listed_stock ASC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC
- **Sort 1 (default)**: orderBy listed_stock DESC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC

### Return:
```php
return $this->buildVariationQuery($request)
    ->paginate($perPage)
    ->appends($request->except('page'));
```

---

## V2 Listing Query Service (`ListingQueryService.php`)

### Base Query:
```php
$query = Variation_model::with([
    'product:id,model,brand,category',
    'storage_id:id,name',
    'color_id:id,name,code',
    'grade_id:id,name',
]);
```

**DIFFERENCE**: V2 uses minimal eager loading (only specific columns), Original loads full relationships

### Filters Applied (in order):
1. `reference_id` - where clause
2. `variation_id` - where clause
3. `category` - whereHas on product
4. `brand` - whereHas on product
5. `marketplace` - whereHas on listings
6. `product` - where clause on product_id
7. `product_name` (via productSearch) - whereIn on product_id
8. `product_name` (via storageSearch) - whereIn on storage
9. `sku` - where clause
10. `color` - where clause
11. `storage` - where clause
12. `grade` - whereIn
13. `topup` - whereHas on listed_stock_verifications
14. `listed_stock` - where clause (> 0 or <= 0)
15. `available_stock` - whereHas with withCount and havingRaw
16. **`whereNotNull('sku')`** - Applied BEFORE state filter
17. `state` - whereIn([2, 3]) if not filled, or where if specified
18. `sale_40` - withCount and having
19. `handler_status` - whereHas on listings (with special logic for status 2)
20. `process_id` + `special='show_only'` - whereHas on process_stocks

**DIFFERENCE**: In V2, `whereNotNull('sku')` is applied BEFORE the state filter, while in Original it's applied AFTER all filters but BEFORE sorting

### Sorting (Applied AFTER all filters):
- **Sort 4**: Join products → orderBy products.model ASC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC → select('variation.*')
- **Sort 3**: Join products → orderBy products.model DESC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC → select('variation.*')
- **Sort 2**: orderBy listed_stock ASC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC
- **Sort 1 (default)**: orderBy listed_stock DESC → orderBy variation.storage ASC → orderBy variation.color ASC → orderBy variation.grade ASC

**SAME**: Sorting logic is identical

### Return:
```php
return $query; // Just returns the query builder
```

Then in Controller:
```php
$variationIds = $query->skip(($page - 1) * $perPage)
    ->take($perPage)
    ->pluck('id')
    ->toArray();
```

---

## Key Differences:

1. **Eager Loading**: 
   - Original: Full relationships loaded
   - V2: Minimal eager loading (only specific columns)

2. **Filter Order**:
   - Original: `whereNotNull('sku')` at the very end (after process_id filter, before sorting)
   - V2: `whereNotNull('sku')` before state filter

3. **Return Method**:
   - Original: Uses `paginate()` which returns a paginator object
   - V2: Returns query builder, then manually paginates with `skip/take/pluck`

4. **State Filter Logic**:
   - Original: Uses if/elseif structure
   - V2: Uses `when()` with callback for default case

---

## Potential Issues:

1. **Filter Order**: The `whereNotNull('sku')` placement might affect query results
2. **Eager Loading**: Missing relationships might cause issues if code expects them
3. **Pagination**: Manual pagination vs Laravel's paginate() might handle joins differently

