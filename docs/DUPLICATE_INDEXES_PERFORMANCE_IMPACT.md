# Duplicate Indexes Performance Impact

## Yes, Duplicate Indexes DO Affect Database Performance

Duplicate indexes can significantly impact database performance in several ways:

### 1. **Storage Overhead**
- Each index takes up disk space
- Duplicate indexes waste storage unnecessarily
- For large tables, this can be substantial

### 2. **Write Performance (INSERT/UPDATE/DELETE)**
- **Every write operation must update ALL indexes**
- If you have 2 identical indexes, both must be updated on every write
- This can **double or triple write time** for affected operations
- Example: If a table has 5 indexes and you add 2 duplicates, writes now update 7 indexes instead of 5

### 3. **Memory Usage**
- Indexes are loaded into memory (InnoDB buffer pool)
- Duplicate indexes waste valuable memory space
- Less memory available for actual data = more disk I/O = slower queries

### 4. **Query Optimizer Confusion**
- MySQL query optimizer may choose a less optimal index
- Multiple similar indexes can lead to suboptimal query plans
- Can result in slower SELECT queries

### 5. **Maintenance Overhead**
- Index maintenance operations (REPAIR, OPTIMIZE) take longer
- Backup/restore operations are slower
- More indexes = more time for maintenance

## Real-World Impact

### Example Scenario:
**Table:** `orders` (1 million rows)
- **Without duplicates:** 5 indexes, INSERT takes 50ms
- **With 2 duplicate indexes:** 7 indexes, INSERT takes 70ms
- **Impact:** 40% slower writes

### For High-Frequency Operations:
- `refresh:new` runs every 2 minutes
- If it inserts/updates 100 orders per run
- With duplicate indexes: **2 extra index updates per order = 200 extra index operations**
- Over 24 hours: **14,400 extra index operations per day**

## How to Detect Duplicate Indexes

### Method 1: SQL Query to Find Duplicate Indexes

```sql
-- Find indexes with the same columns in the same order
SELECT 
    t.TABLE_SCHEMA,
    t.TABLE_NAME,
    GROUP_CONCAT(DISTINCT s.INDEX_NAME ORDER BY s.INDEX_NAME) as index_names,
    GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX) as columns,
    COUNT(DISTINCT s.INDEX_NAME) as index_count
FROM 
    information_schema.STATISTICS s
    INNER JOIN information_schema.TABLES t 
        ON s.TABLE_SCHEMA = t.TABLE_SCHEMA 
        AND s.TABLE_NAME = t.TABLE_NAME
WHERE 
    t.TABLE_SCHEMA = DATABASE()
    AND s.INDEX_NAME != 'PRIMARY'
GROUP BY 
    t.TABLE_SCHEMA, 
    t.TABLE_NAME,
    GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX)
HAVING 
    COUNT(DISTINCT s.INDEX_NAME) > 1
ORDER BY 
    t.TABLE_NAME, index_count DESC;
```

### Method 2: Check for Indexes with Same Columns

```sql
-- Find indexes on the same table with identical column sets
SELECT 
    TABLE_NAME,
    GROUP_CONCAT(INDEX_NAME ORDER BY INDEX_NAME) as duplicate_indexes,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
FROM 
    information_schema.STATISTICS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND INDEX_NAME != 'PRIMARY'
GROUP BY 
    TABLE_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
HAVING 
    COUNT(DISTINCT INDEX_NAME) > 1;
```

### Method 3: Check Specific Tables

```sql
-- Check orders table for duplicate indexes
SHOW INDEXES FROM orders;

-- Look for indexes with same columns
-- Example: If you see both 'idx_orders_reference_id' and 'idx_orders_ref_id' 
-- with same columns, one is duplicate
```

## Our Migration Protection

Our migration (`2026_01_08_094313_add_scheduler_optimization_indexes.php`) **already protects against duplicates** by:

1. **Checking if index exists before creating:**
   ```php
   if (!$this->indexExists($tableName, $indexName)) {
       // Only create if doesn't exist
   }
   ```

2. **Using `indexExists()` method:**
   - Queries `information_schema.statistics`
   - Checks by exact index name
   - Prevents creating same index twice

## Potential Issues to Watch For

### 1. **Similar Indexes (Not Exact Duplicates)**
- `idx_orders_reference_id` (single column)
- `idx_orders_reference_marketplace` (composite: reference_id, marketplace_id)
- **These are NOT duplicates** - the composite index can be used for queries on both columns

### 2. **Indexes with Different Names but Same Columns**
- If an index already exists with a different name but same columns
- Our migration won't detect this
- Need manual check

### 3. **Redundant Composite Indexes**
- Index on `(A, B, C)` makes index on `(A, B)` redundant
- MySQL can use the leftmost prefix of composite indexes
- Example: `idx_orders_reference_marketplace` (reference_id, marketplace_id) 
  - Can be used for queries on just `reference_id`
  - So a separate `idx_orders_reference_id` might be redundant

## Recommendations

### 1. **Before Running Migration:**
Check for existing indexes:
```sql
SHOW INDEXES FROM orders;
SHOW INDEXES FROM order_items;
SHOW INDEXES FROM variation;
SHOW INDEXES FROM stock;
```

### 2. **After Running Migration:**
Run the duplicate detection query above to verify no duplicates were created.

### 3. **Remove Redundant Indexes:**
If you find:
- Index on `(A, B, C)` AND index on `(A, B)` → Remove `(A, B)`
- Index on `(A)` AND index on `(A, B)` → Keep `(A, B)`, remove `(A)` if not needed for single-column queries

### 4. **Monitor Performance:**
- Check `SHOW PROCESSLIST` for slow queries
- Monitor `SHOW ENGINE INNODB STATUS` for index usage
- Use `EXPLAIN` to see which indexes are actually used

## Safe Index Removal

If you find duplicate indexes, remove them carefully:

```sql
-- 1. Check if index is being used
SELECT * FROM sys.schema_unused_indexes 
WHERE object_schema = DATABASE() 
AND object_name = 'orders';

-- 2. If not used, drop it
DROP INDEX idx_old_index_name ON orders;

-- 3. Monitor performance after removal
```

## Conclusion

**Yes, duplicate indexes significantly impact performance**, especially for:
- High-frequency write operations (like our schedulers)
- Large tables
- Memory-constrained systems

**Our migration is safe** because it checks for existing indexes before creating new ones. However, you should:
1. ✅ Check for existing indexes before running migration
2. ✅ Run duplicate detection query after migration
3. ✅ Remove any redundant indexes found
4. ✅ Monitor performance after changes

