<?php

namespace App\Http\Livewire\V2;

use Livewire\Component;
use App\Models\Marketplace_model;
use App\Jobs\SyncMarketplaceStockJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class Marketplace extends Component
{
    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect()->route('login');
        }
    }

    public function render()
    {
        $data['title_page'] = "Marketplaces";
        session()->put('page_title', $data['title_page']);
        $data['marketplaces'] = Marketplace_model::orderBy('name', 'ASC')->get();

        return view('livewire.v2.marketplace')->with($data);
    }

    public function add_marketplace()
    {
        $data['title_page'] = "Add Marketplace";
        session()->put('page_title', $data['title_page']);
        return view('livewire.v2.add-marketplace')->with($data);
    }

    public function insert_marketplace()
    {
        request()->validate([
            'name' => 'required|string|max:255',
        ]);

        Marketplace_model::create([
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status', 1),
            'api_key' => request('api_key'),
            'api_secret' => request('api_secret'),
            'api_url' => request('api_url'),
        ]);

        session()->put('success', "Marketplace has been added successfully");
        return redirect('v2/marketplace');
    }

    public function edit_marketplace($id)
    {
        $data['title_page'] = "Edit Marketplace";
        session()->put('page_title', $data['title_page']);
        $data['marketplace'] = Marketplace_model::where('id', $id)->first();

        if (!$data['marketplace']) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        return view('livewire.v2.edit-marketplace')->with($data);
    }

    public function update_marketplace($id)
    {
        request()->validate([
            'name' => 'required|string|max:255',
        ]);

        $marketplace = Marketplace_model::find($id);

        if (!$marketplace) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        $updateData = [
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status', 1),
            'api_url' => request('api_url'),
        ];

        // Only update API key if provided (not empty)
        if (request()->has('api_key') && request('api_key') !== '') {
            $updateData['api_key'] = request('api_key');
        }

        // Only update API secret if provided (not empty)
        if (request()->has('api_secret') && request('api_secret') !== '') {
            $updateData['api_secret'] = request('api_secret');
        }

        $marketplace->update($updateData);

        session()->put('success', "Marketplace has been updated successfully");
        return redirect('v2/marketplace');
    }

    public function delete_marketplace($id)
    {
        $marketplace = Marketplace_model::find($id);

        if (!$marketplace) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        $marketplace->delete();
        session()->put('success', "Marketplace has been deleted successfully");
        return redirect('v2/marketplace');
    }

    /**
     * Sync stock for a specific marketplace
     */
    public function sync_marketplace($marketplaceId)
    {
        try {
            $marketplace = Marketplace_model::find($marketplaceId);
            
            if (!$marketplace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marketplace not found'
                ], 404);
            }

            Log::info("Starting marketplace stock sync (queued)", [
                'marketplace_id' => $marketplaceId,
                'marketplace_name' => $marketplace->name,
                'user_id' => session('user_id'),
                'url' => request()->fullUrl(),
                'ip' => request()->ip()
            ]);

            // Dispatch job to queue (runs in background)
            $job = new SyncMarketplaceStockJob($marketplaceId, session('user_id'));
            $jobId = dispatch($job);
            
            // Get job ID if available
            $jobIdString = is_string($jobId) ? $jobId : (method_exists($jobId, 'getJobId') ? $jobId->getJobId() : null);

            Log::info("Marketplace stock sync job dispatched", [
                'marketplace_id' => $marketplaceId,
                'marketplace_name' => $marketplace->name,
                'job_id' => $jobIdString
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stock sync started in background for {$marketplace->name}",
                'job_id' => $jobIdString,
                'queued' => true,
                'note' => 'Sync is running in background. Check logs or refresh page to see status.'
            ]);

        } catch (\Exception $e) {
            Log::error("Error syncing marketplace stock", [
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error syncing marketplace: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync stock for all marketplaces
     */
    public function sync_all_marketplaces()
    {
        try {
            $marketplaces = Marketplace_model::where('status', 1)->get();
            
            Log::info("Starting sync for all marketplaces (queued)", [
                'count' => $marketplaces->count(),
                'user_id' => session('user_id')
            ]);

            $jobIds = [];
            foreach ($marketplaces as $marketplace) {
                // Dispatch job for each marketplace
                $job = new SyncMarketplaceStockJob($marketplace->id, session('user_id'));
                $jobId = dispatch($job);
                
                $jobIdString = is_string($jobId) ? $jobId : (method_exists($jobId, 'getJobId') ? $jobId->getJobId() : null);
                
                $jobIds[] = [
                    'marketplace_id' => $marketplace->id,
                    'marketplace_name' => $marketplace->name,
                    'job_id' => $jobIdString
                ];

                Log::info("Dispatched sync job for marketplace", [
                    'marketplace_id' => $marketplace->id,
                    'marketplace_name' => $marketplace->name,
                    'job_id' => $jobIdString
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync started in background for all marketplaces',
                'job_ids' => $jobIds,
                'queued' => true,
                'note' => 'All syncs are running in background. Check logs or refresh page to see status.'
            ]);

        } catch (\Exception $e) {
            Log::error("Error dispatching sync jobs for all marketplaces", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error starting syncs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status for a marketplace
     */
    public function get_sync_status($marketplaceId)
    {
        try {
            $marketplace = Marketplace_model::find($marketplaceId);
            
            if (!$marketplace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marketplace not found'
                ], 404);
            }

            // Get sync statistics
            $stats = DB::table('marketplace_stock')
                ->where('marketplace_id', $marketplaceId)
                ->selectRaw('
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN last_synced_at IS NULL THEN 1 END) as never_synced,
                    COUNT(CASE WHEN last_synced_at IS NOT NULL AND TIMESTAMPDIFF(HOUR, last_synced_at, NOW()) >= 6 THEN 1 END) as needs_sync,
                    AVG(TIMESTAMPDIFF(HOUR, last_synced_at, NOW())) as avg_hours_since_sync,
                    MAX(last_synced_at) as last_sync_time
                ')
                ->first();

            return response()->json([
                'success' => true,
                'marketplace' => [
                    'id' => $marketplace->id,
                    'name' => $marketplace->name,
                    'sync_interval_hours' => $marketplace->sync_interval_hours ?? 6,
                ],
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting sync status: ' . $e->getMessage()
            ], 500);
        }
    }
}

