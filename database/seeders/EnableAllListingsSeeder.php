<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Listing_model;

class EnableAllListingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder enables all existing listings by setting is_enabled = 1
     *
     * @return void
     */
    public function run()
    {
        // Update all existing listings to be enabled
        $updated = DB::table('listings')
            ->whereNull('is_enabled')
            ->orWhere('is_enabled', '!=', 1)
            ->update(['is_enabled' => 1]);
        
        // Also ensure all listings have is_enabled = 1 (in case column was just added)
        $total = DB::table('listings')->update(['is_enabled' => 1]);
        
        $this->command->info("Enable All Listings Seeder completed!");
        $this->command->info("Updated {$total} listings to enabled status (is_enabled = 1)");
    }
}
