<?php

namespace Database\Seeders;

use App\Models\Marketplace_model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SyntoraMV4MarketplaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Generate a unique hash secret key
        $hashSecret = Str::random(64);
        
        // Create or update the Syntora MV4 marketplace
        $marketplace = Marketplace_model::updateOrCreate(
            ['name' => 'Syntora MV4'],
            [
                'description' => 'Syntora Marketplace Version 4',
                'status' => 1,
                'api_key' => Str::random(32),
                'api_secret' => $hashSecret,
                'api_url' => null,
                'sync_enabled' => true,
                'sync_interval_hours' => 6,
            ]
        );

        $this->command->info('Syntora MV4 Marketplace seeded successfully!');
        $this->command->info('Marketplace ID: ' . $marketplace->id);
        $this->command->info('API Key: ' . $marketplace->api_key);
        $this->command->info('Hash Secret: ' . $hashSecret);
        $this->command->warn('Please add the following to your .env file in syntora-marketplace-v4:');
        $this->command->warn('SYNC_API_KEY=' . $marketplace->api_key);
        $this->command->warn('SYNC_API_SECRET=' . $hashSecret);
        $this->command->warn('SYNC_API_URL=' . config('app.url') . '/api/sync');
    }
}
