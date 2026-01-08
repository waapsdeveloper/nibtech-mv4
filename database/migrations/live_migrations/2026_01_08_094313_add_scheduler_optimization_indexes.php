<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds critical indexes for scheduler optimization to improve CPU performance.
     * All indexes are checked for existence before creation to prevent migration failures.
     *
     * @return void
     */
    public function up()
    {
        $this->addOrdersTableIndexes();
        $this->addOrderItemsTableIndexes();
        $this->addVariationsTableIndexes();
        $this->addStocksTableIndexes();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropOrdersTableIndexes();
        $this->dropOrderItemsTableIndexes();
        $this->dropVariationsTableIndexes();
        $this->dropStocksTableIndexes();
    }

    /**
     * Check if an index exists on a table
     * 
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $result = DB::select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = DATABASE() 
             AND table_name = ? 
             AND index_name = ?",
            [$tableName, $indexName]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * Check if an index with the same columns already exists (duplicate detection)
     * 
     * @param string $tableName
     * @param array $columns Array of column names in order
     * @return array|null Returns array with existing index name(s) or null if no duplicate
     */
    private function findDuplicateIndexByColumns(string $tableName, array $columns): ?array
    {
        $columnsStr = implode(',', $columns);
        
        // Get all indexes for this table
        $allIndexes = DB::select(
            "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = ?
             AND INDEX_NAME != 'PRIMARY'
             ORDER BY INDEX_NAME, SEQ_IN_INDEX",
            [$tableName]
        );
        
        // Group by index name and build column lists
        $indexColumns = [];
        foreach ($allIndexes as $row) {
            if (!isset($indexColumns[$row->INDEX_NAME])) {
                $indexColumns[$row->INDEX_NAME] = [];
            }
            $indexColumns[$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }
        
        // Find indexes with matching columns
        $duplicates = [];
        foreach ($indexColumns as $indexName => $indexCols) {
            if (implode(',', $indexCols) === $columnsStr) {
                $duplicates[] = $indexName;
            }
        }
        
        return count($duplicates) > 0 ? $duplicates : null;
    }

    /**
     * Check if there are duplicate records for unique index columns
     * 
     * @param string $tableName
     * @param array $columns
     * @return bool
     */
    private function hasDuplicates(string $tableName, array $columns): bool
    {
        $columnsStr = implode(', ', $columns);
        $groupByStr = implode(', ', $columns);
        
        $result = DB::select(
            "SELECT COUNT(*) as duplicate_count 
             FROM (
                 SELECT {$columnsStr}, COUNT(*) as cnt 
                 FROM {$tableName} 
                 GROUP BY {$groupByStr} 
                 HAVING cnt > 1
             ) as duplicates"
        );
        
        return $result[0]->duplicate_count > 0;
    }

    /**
     * Get duplicate count for better error messages
     * 
     * @param string $tableName
     * @param array $columns
     * @return int
     */
    private function getDuplicateCount(string $tableName, array $columns): int
    {
        $columnsStr = implode(', ', $columns);
        $groupByStr = implode(', ', $columns);
        
        $result = DB::select(
            "SELECT COUNT(*) as duplicate_count 
             FROM (
                 SELECT {$columnsStr}, COUNT(*) as cnt 
                 FROM {$tableName} 
                 GROUP BY {$groupByStr} 
                 HAVING cnt > 1
             ) as duplicates"
        );
        
        return (int) $result[0]->duplicate_count;
    }

    /**
     * Safely create an index if it doesn't exist
     * 
     * @param string $tableName
     * @param string $indexName
     * @param string $sql
     * @param bool $isUnique
     * @param array|null $uniqueColumns
     * @param array|null $indexColumns Columns used in this index (for duplicate detection)
     * @return void
     */
    private function createIndexIfNotExists(
        string $tableName, 
        string $indexName, 
        string $sql, 
        bool $isUnique = false,
        ?array $uniqueColumns = null,
        ?array $indexColumns = null
    ): void {
        // Check if index with same name exists
        if ($this->indexExists($tableName, $indexName)) {
            echo "⊘ Index {$indexName} already exists on {$tableName}, skipping...\n";
            return;
        }
        
        // Check for duplicate indexes with same columns but different name
        if ($indexColumns !== null) {
            $duplicateIndexes = $this->findDuplicateIndexByColumns($tableName, $indexColumns);
            if ($duplicateIndexes !== null && count($duplicateIndexes) > 0) {
                $duplicateNames = implode(', ', $duplicateIndexes);
                echo "⚠ Skipping index {$indexName} on {$tableName}: Duplicate index(es) already exist with same columns: {$duplicateNames}\n";
                echo "   → Columns: (" . implode(', ', $indexColumns) . ")\n";
                echo "   → Consider removing duplicate index(es) for better performance\n";
                return;
            }
        }
        
        if (true) { // Removed the check since we already did it above
            // Check for duplicates if this is a unique index
            if ($isUnique && $uniqueColumns !== null) {
                if ($this->hasDuplicates($tableName, $uniqueColumns)) {
                    $duplicateCount = $this->getDuplicateCount($tableName, $uniqueColumns);
                    $columnsStr = implode(', ', $uniqueColumns);
                    
                    echo "⚠ Skipping UNIQUE index {$indexName} on {$tableName}: Found {$duplicateCount} duplicate record(s) for columns ({$columnsStr})\n";
                    echo "   → Creating non-unique index instead for query performance...\n";
                    
                    // Create non-unique index instead
                    $nonUniqueSql = str_replace('UNIQUE INDEX', 'INDEX', $sql);
                    try {
                        DB::statement($nonUniqueSql);
                        echo "✓ Created non-unique index: {$indexName} on {$tableName} (duplicates exist)\n";
                    } catch (\Exception $e) {
                        echo "⚠ Failed to create non-unique index {$indexName} on {$tableName}: {$e->getMessage()}\n";
                    }
                    return;
                }
            }
            
            try {
                DB::statement($sql);
                echo "✓ Created index: {$indexName} on {$tableName}\n";
            } catch (\Exception $e) {
                // If unique index fails due to duplicates, try non-unique
                if ($isUnique && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "⚠ Unique index failed due to duplicates, creating non-unique index instead...\n";
                    $nonUniqueSql = str_replace('UNIQUE INDEX', 'INDEX', $sql);
                    try {
                        DB::statement($nonUniqueSql);
                        echo "✓ Created non-unique index: {$indexName} on {$tableName} (duplicates exist)\n";
                    } catch (\Exception $e2) {
                        echo "⚠ Failed to create index {$indexName} on {$tableName}: {$e2->getMessage()}\n";
                    }
                } else {
                    echo "⚠ Failed to create index {$indexName} on {$tableName}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Add indexes to orders table
     */
    private function addOrdersTableIndexes(): void
    {
        $tableName = 'orders';

        // Priority 1: Composite unique index for order lookup (most critical)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_orders_reference_marketplace',
            "CREATE UNIQUE INDEX idx_orders_reference_marketplace ON {$tableName}(reference_id, marketplace_id)",
            true,
            ['reference_id', 'marketplace_id'],
            ['reference_id', 'marketplace_id']
        );

        // Priority 1: Index for reference_id lookups (used in N+1 queries)
        // Note: This might be redundant if idx_orders_reference_marketplace exists (leftmost prefix)
        // But we keep it for queries that only filter by reference_id
        $this->createIndexIfNotExists(
            $tableName,
            'idx_orders_reference_id',
            "CREATE INDEX idx_orders_reference_id ON {$tableName}(reference_id)",
            false,
            null,
            ['reference_id']
        );

        // Priority 1: Composite index for incomplete orders query
        $this->createIndexIfNotExists(
            $tableName,
            'idx_orders_incomplete',
            "CREATE INDEX idx_orders_incomplete ON {$tableName}(order_type_id, status, created_at)",
            false,
            null,
            ['order_type_id', 'status', 'created_at']
        );

        // Priority 1: Index for marketplace_id (used in whereHas queries)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_orders_marketplace_id',
            "CREATE INDEX idx_orders_marketplace_id ON {$tableName}(marketplace_id)",
            false,
            null,
            ['marketplace_id']
        );

        // Priority 2: Additional composite index for status/type/created queries
        $this->createIndexIfNotExists(
            $tableName,
            'idx_orders_status_type_created',
            "CREATE INDEX idx_orders_status_type_created ON {$tableName}(status, order_type_id, created_at)",
            false,
            null,
            ['status', 'order_type_id', 'created_at']
        );
    }

    /**
     * Add indexes to order_items table
     */
    private function addOrderItemsTableIndexes(): void
    {
        $tableName = 'order_items';

        // Priority 1: Composite unique index for order item lookup
        $this->createIndexIfNotExists(
            $tableName,
            'idx_order_items_reference_order',
            "CREATE UNIQUE INDEX idx_order_items_reference_order ON {$tableName}(reference_id, order_id)",
            true,
            ['reference_id', 'order_id'],
            ['reference_id', 'order_id']
        );

        // Priority 1: Index for reference_id (used in care updates)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_order_items_reference_id',
            "CREATE INDEX idx_order_items_reference_id ON {$tableName}(reference_id)",
            false,
            null,
            ['reference_id']
        );

        // Priority 1: Index for order_id (used in relationships)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_order_items_order_id',
            "CREATE INDEX idx_order_items_order_id ON {$tableName}(order_id)",
            false,
            null,
            ['order_id']
        );

        // Priority 1: Partial index for care_id (only non-null values)
        // Note: MySQL doesn't support partial indexes like PostgreSQL, so we create a regular index
        $this->createIndexIfNotExists(
            $tableName,
            'idx_order_items_care_id',
            "CREATE INDEX idx_order_items_care_id ON {$tableName}(care_id)",
            false,
            null,
            ['care_id']
        );

        // Priority 2: Composite index for variation/order queries
        $this->createIndexIfNotExists(
            $tableName,
            'idx_order_items_variation_order',
            "CREATE INDEX idx_order_items_variation_order ON {$tableName}(variation_id, order_id)",
            false,
            null,
            ['variation_id', 'order_id']
        );
    }

    /**
     * Add indexes to variations table
     */
    private function addVariationsTableIndexes(): void
    {
        $tableName = 'variation';

        // Priority 1: Index for reference_id lookups (used in updateOrderItemsInDB)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_variations_reference_id',
            "CREATE INDEX idx_variations_reference_id ON {$tableName}(reference_id)",
            false,
            null,
            ['reference_id']
        );

        // Priority 1: Index for SKU lookups (used in updateOrderItemsInDB)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_variations_sku',
            "CREATE INDEX idx_variations_sku ON {$tableName}(sku)",
            false,
            null,
            ['sku']
        );
    }

    /**
     * Add indexes to stocks table
     */
    private function addStocksTableIndexes(): void
    {
        $tableName = 'stock';

        // Priority 1: Index for IMEI lookups (used in updateOrderItemsInDB)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_stocks_imei',
            "CREATE INDEX idx_stocks_imei ON {$tableName}(imei)",
            false,
            null,
            ['imei']
        );

        // Priority 1: Index for serial_number lookups (used in updateOrderItemsInDB)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_stocks_serial_number',
            "CREATE INDEX idx_stocks_serial_number ON {$tableName}(serial_number)",
            false,
            null,
            ['serial_number']
        );

        // Priority 1: Index for variation_id (used in relationships)
        $this->createIndexIfNotExists(
            $tableName,
            'idx_stocks_variation_id',
            "CREATE INDEX idx_stocks_variation_id ON {$tableName}(variation_id)",
            false,
            null,
            ['variation_id']
        );
    }

    /**
     * Drop indexes from orders table
     */
    private function dropOrdersTableIndexes(): void
    {
        $tableName = 'orders';
        $indexes = [
            'idx_orders_reference_marketplace',
            'idx_orders_reference_id',
            'idx_orders_incomplete',
            'idx_orders_marketplace_id',
            'idx_orders_status_type_created',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                try {
                    DB::statement("DROP INDEX {$indexName} ON {$tableName}");
                    echo "✓ Dropped index: {$indexName} from {$tableName}\n";
                } catch (\Exception $e) {
                    echo "⚠ Failed to drop index {$indexName} from {$tableName}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Drop indexes from order_items table
     */
    private function dropOrderItemsTableIndexes(): void
    {
        $tableName = 'order_items';
        $indexes = [
            'idx_order_items_reference_order',
            'idx_order_items_reference_id',
            'idx_order_items_order_id',
            'idx_order_items_care_id',
            'idx_order_items_variation_order',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                try {
                    DB::statement("DROP INDEX {$indexName} ON {$tableName}");
                    echo "✓ Dropped index: {$indexName} from {$tableName}\n";
                } catch (\Exception $e) {
                    echo "⚠ Failed to drop index {$indexName} from {$tableName}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Drop indexes from variations table
     */
    private function dropVariationsTableIndexes(): void
    {
        $tableName = 'variation';
        $indexes = [
            'idx_variations_reference_id',
            'idx_variations_sku',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                try {
                    DB::statement("DROP INDEX {$indexName} ON {$tableName}");
                    echo "✓ Dropped index: {$indexName} from {$tableName}\n";
                } catch (\Exception $e) {
                    echo "⚠ Failed to drop index {$indexName} from {$tableName}: {$e->getMessage()}\n";
                }
            }
        }
    }

    /**
     * Drop indexes from stocks table
     */
    private function dropStocksTableIndexes(): void
    {
        $tableName = 'stock';
        $indexes = [
            'idx_stocks_imei',
            'idx_stocks_serial_number',
            'idx_stocks_variation_id',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                try {
                    DB::statement("DROP INDEX {$indexName} ON {$tableName}");
                    echo "✓ Dropped index: {$indexName} from {$tableName}\n";
                } catch (\Exception $e) {
                    echo "⚠ Failed to drop index {$indexName} from {$tableName}: {$e->getMessage()}\n";
                }
            }
        }
    }
};

