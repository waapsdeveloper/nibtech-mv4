<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Variation_model;

class MarketplaceStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder populates marketplace_stock table with existing variation stock data
     * for marketplace_id = 1 (BackMarket primary marketplace)
     *
     * @return void
     */
    public function run()
    {
        $marketplaceId = 1; // BackMarket primary marketplace
        
        // Get all variations that have listed_stock (not null)
        $variations = Variation_model::whereNotNull('listed_stock')
            ->get();
        
        $inserted = 0;
        $skipped = 0;
        
        foreach ($variations as $variation) {
            // Check if record already exists
            $exists = DB::table('marketplace_stock')
                ->where('variation_id', $variation->id)
                ->where('marketplace_id', $marketplaceId)
                ->exists();
            
            if ($exists) {
                // Update existing record
                DB::table('marketplace_stock')
                    ->where('variation_id', $variation->id)
                    ->where('marketplace_id', $marketplaceId)
                    ->update([
                        'listed_stock' => $variation->listed_stock ?? 0,
                        'updated_at' => now(),
                    ]);
                $skipped++;
            } else {
                // Insert new record
                DB::table('marketplace_stock')->insert([
                    'variation_id' => $variation->id,
                    'marketplace_id' => $marketplaceId,
                    'listed_stock' => $variation->listed_stock ?? 0,
                    'admin_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted++;
            }
        }
        
        $this->command->info("Marketplace Stock Seeder completed!");
        $this->command->info("Inserted: {$inserted} new records");
        $this->command->info("Updated: {$skipped} existing records");
        $this->command->info("Total variations processed: " . ($inserted + $skipped));
    }
}
