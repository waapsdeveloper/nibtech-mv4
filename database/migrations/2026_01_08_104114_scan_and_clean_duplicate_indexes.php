<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Scans the database for duplicate indexes and removes them to improve performance.
     * Duplicate indexes waste storage, memory, and slow down write operations.
     *
     * @return void
     */
    public function up()
    {
        echo "\n========================================\n";
        echo "SCANNING FOR DUPLICATE INDEXES\n";
        echo "========================================\n\n";

        $allDuplicates = $this->findAllDuplicateIndexes();
        $redundantIndexes = $this->findRedundantIndexes();

        if (empty($allDuplicates) && empty($redundantIndexes)) {
            echo "✓ No duplicate or redundant indexes found. Database is optimized!\n\n";
            return;
        }

        // Display findings
        $this->displayFindings($allDuplicates, $redundantIndexes);

        // Clean up duplicates
        $this->cleanDuplicateIndexes($allDuplicates);
        
        // Clean up redundant indexes
        $this->cleanRedundantIndexes($redundantIndexes);

        echo "\n========================================\n";
        echo "CLEANUP COMPLETE\n";
        echo "========================================\n\n";
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This migration only removes indexes, so rollback is not applicable.
     * If you need to restore indexes, run the original index creation migration.
     *
     * @return void
     */
    public function down()
    {
        echo "\n⚠ This migration only removes duplicate indexes.\n";
        echo "To restore indexes, run the index creation migration.\n\n";
    }

    /**
     * Find all duplicate indexes (same columns, different names)
     * 
     * @return array Array of duplicate index groups
     */
    private function findAllDuplicateIndexes(): array
    {
        $duplicates = [];
        
        // Get all tables in the database
        $tables = DB::select("SELECT TABLE_NAME 
                             FROM information_schema.TABLES 
                             WHERE TABLE_SCHEMA = DATABASE() 
                             AND TABLE_TYPE = 'BASE TABLE'");

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            
            // Get all indexes for this table (except PRIMARY)
            $indexes = DB::select(
                "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
                 FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                 AND table_name = ?
                 AND INDEX_NAME != 'PRIMARY'
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX",
                [$tableName]
            );

            // Group indexes by their column sets
            $indexGroups = [];
            foreach ($indexes as $index) {
                $indexName = $index->INDEX_NAME;
                if (!isset($indexGroups[$indexName])) {
                    $indexGroups[$indexName] = [
                        'name' => $indexName,
                        'columns' => [],
                        'is_unique' => $index->NON_UNIQUE == 0
                    ];
                }
                $indexGroups[$indexName]['columns'][] = $index->COLUMN_NAME;
            }

            // Find duplicates (same columns, different names)
            $columnSets = [];
            foreach ($indexGroups as $indexName => $indexData) {
                $columnKey = implode(',', $indexData['columns']);
                if (!isset($columnSets[$columnKey])) {
                    $columnSets[$columnKey] = [];
                }
                $columnSets[$columnKey][] = [
                    'name' => $indexName,
                    'is_unique' => $indexData['is_unique'],
                    'columns' => $indexData['columns']
                ];
            }

            // Identify duplicates
            foreach ($columnSets as $columnKey => $indexList) {
                if (count($indexList) > 1) {
                    // Sort: prefer unique indexes, then shorter names
                    usort($indexList, function($a, $b) {
                        if ($a['is_unique'] != $b['is_unique']) {
                            return $b['is_unique'] ? 1 : -1; // Unique first
                        }
                        return strlen($a['name']) <=> strlen($b['name']); // Shorter name first
                    });

                    $keep = array_shift($indexList); // Keep the first one
                    $duplicates[] = [
                        'table' => $tableName,
                        'keep' => $keep,
                        'remove' => $indexList,
                        'columns' => $keep['columns']
                    ];
                }
            }
        }

        return $duplicates;
    }

    /**
     * Find redundant indexes (composite index makes single-column index redundant)
     * 
     * Example: Index on (A, B) makes index on (A) redundant
     * 
     * @return array Array of redundant indexes
     */
    private function findRedundantIndexes(): array
    {
        $redundant = [];
        
        // Get all tables
        $tables = DB::select("SELECT TABLE_NAME 
                             FROM information_schema.TABLES 
                             WHERE TABLE_SCHEMA = DATABASE() 
                             AND TABLE_TYPE = 'BASE TABLE'");

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            
            // Get all indexes
            $indexes = DB::select(
                "SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE
                 FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                 AND table_name = ?
                 AND INDEX_NAME != 'PRIMARY'
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX",
                [$tableName]
            );

            // Build index structure
            $indexStructures = [];
            foreach ($indexes as $index) {
                $indexName = $index->INDEX_NAME;
                if (!isset($indexStructures[$indexName])) {
                    $indexStructures[$indexName] = [
                        'columns' => [],
                        'is_unique' => $index->NON_UNIQUE == 0
                    ];
                }
                $indexStructures[$indexName]['columns'][] = $index->COLUMN_NAME;
            }

            // Check for redundancy
            foreach ($indexStructures as $indexName => $indexData) {
                $columns = $indexData['columns'];
                
                // If this is a composite index, check if any single-column index is redundant
                if (count($columns) > 1) {
                    foreach ($indexStructures as $otherIndexName => $otherIndexData) {
                        // Skip same index
                        if ($otherIndexName === $indexName) {
                            continue;
                        }
                        
                        // Check if other index is a prefix of this composite index
                        $otherColumns = $otherIndexData['columns'];
                        if (count($otherColumns) === 1 && $otherColumns[0] === $columns[0]) {
                            // Single-column index matches first column of composite
                            // Check if it's not unique (unique indexes should be kept)
                            if (!$otherIndexData['is_unique']) {
                                $redundant[] = [
                                    'table' => $tableName,
                                    'redundant_index' => $otherIndexName,
                                    'redundant_columns' => $otherColumns,
                                    'covered_by' => $indexName,
                                    'covered_columns' => $columns,
                                    'reason' => "Single-column index '{$otherIndexName}' is redundant because composite index '{$indexName}' covers it"
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $redundant;
    }

    /**
     * Display findings before cleanup
     * 
     * @param array $duplicates
     * @param array $redundant
     * @return void
     */
    private function displayFindings(array $duplicates, array $redundant): void
    {
        if (!empty($duplicates)) {
            echo "📋 FOUND DUPLICATE INDEXES:\n";
            echo "----------------------------\n";
            foreach ($duplicates as $group) {
                echo "\nTable: {$group['table']}\n";
                echo "  Columns: (" . implode(', ', $group['columns']) . ")\n";
                echo "  ✓ KEEP: {$group['keep']['name']}" . ($group['keep']['is_unique'] ? ' (UNIQUE)' : '') . "\n";
                foreach ($group['remove'] as $remove) {
                    echo "  ✗ REMOVE: {$remove['name']}" . ($remove['is_unique'] ? ' (UNIQUE)' : '') . "\n";
                }
            }
            echo "\n";
        }

        if (!empty($redundant)) {
            echo "📋 FOUND REDUNDANT INDEXES:\n";
            echo "----------------------------\n";
            foreach ($redundant as $item) {
                echo "\nTable: {$item['table']}\n";
                echo "  ✗ REMOVE: {$item['redundant_index']} (" . implode(', ', $item['redundant_columns']) . ")\n";
                echo "  → Covered by: {$item['covered_by']} (" . implode(', ', $item['covered_columns']) . ")\n";
                echo "  Reason: {$item['reason']}\n";
            }
            echo "\n";
        }

        $totalToRemove = count($duplicates) + count($redundant);
        echo "Total indexes to remove: {$totalToRemove}\n\n";
    }

    /**
     * Clean up duplicate indexes
     * 
     * @param array $duplicates
     * @return void
     */
    private function cleanDuplicateIndexes(array $duplicates): void
    {
        if (empty($duplicates)) {
            return;
        }

        echo "🧹 CLEANING DUPLICATE INDEXES:\n";
        echo "----------------------------\n";

        $removed = 0;
        $failed = 0;

        foreach ($duplicates as $group) {
            foreach ($group['remove'] as $remove) {
                try {
                    DB::statement("DROP INDEX `{$remove['name']}` ON `{$group['table']}`");
                    echo "✓ Removed duplicate index: {$remove['name']} from {$group['table']}\n";
                    $removed++;
                } catch (\Exception $e) {
                    echo "⚠ Failed to remove index {$remove['name']} from {$group['table']}: {$e->getMessage()}\n";
                    $failed++;
                }
            }
        }

        echo "\nRemoved: {$removed} duplicate index(es)\n";
        if ($failed > 0) {
            echo "Failed: {$failed} index(es)\n";
        }
        echo "\n";
    }

    /**
     * Clean up redundant indexes
     * 
     * @param array $redundant
     * @return void
     */
    private function cleanRedundantIndexes(array $redundant): void
    {
        if (empty($redundant)) {
            return;
        }

        echo "🧹 CLEANING REDUNDANT INDEXES:\n";
        echo "----------------------------\n";

        $removed = 0;
        $failed = 0;

        foreach ($redundant as $item) {
            try {
                DB::statement("DROP INDEX `{$item['redundant_index']}` ON `{$item['table']}`");
                echo "✓ Removed redundant index: {$item['redundant_index']} from {$item['table']}\n";
                $removed++;
            } catch (\Exception $e) {
                echo "⚠ Failed to remove index {$item['redundant_index']} from {$item['table']}: {$e->getMessage()}\n";
                $failed++;
            }
        }

        echo "\nRemoved: {$removed} redundant index(es)\n";
        if ($failed > 0) {
            echo "Failed: {$failed} index(es)\n";
        }
        echo "\n";
    }
};
