<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class JobsController extends Controller
{
    /**
     * Display all jobs (queued, processing, etc.)
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Queue Jobs";
        session()->put('page_title', $data['title_page']);
        
        try {
            // Check if table exists
            if (!Schema::hasTable('jobs')) {
                $emptyPaginator = new LengthAwarePaginator([], 0, 20, 1, [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]);
                return view('v2.logs.jobs.index', [
                    'jobs' => $emptyPaginator,
                    'stats' => ['total' => 0, 'queued' => 0, 'processing' => 0],
                    'queues' => collect([]),
                    'data' => $data,
                    'error' => 'jobs table does not exist. Please run: php artisan queue:table && php artisan migrate'
                ]);
            }
            
            // Get filter parameters
            $queue = $request->get('queue');
            $status = $request->get('status'); // 'queued', 'processing', 'all'
            $perPage = $request->get('per_page', 20);
            
            // Build query
            $query = DB::table('jobs')
                ->orderBy('created_at', 'desc');
            
            if ($queue) {
                $query->where('queue', $queue);
            }
            
            // Filter by status
            if ($status === 'queued') {
                $query->whereNull('reserved_at');
            } elseif ($status === 'processing') {
                $query->whereNotNull('reserved_at');
            }
            // 'all' or no filter shows everything
            
            // Get jobs
            $jobs = $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('JobsController::index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $emptyPaginator = new LengthAwarePaginator([], 0, 20, 1, [
                'path' => request()->url(),
                'pageName' => 'page',
            ]);
            return view('v2.logs.jobs.index', [
                'jobs' => $emptyPaginator,
                'stats' => ['total' => 0, 'queued' => 0, 'processing' => 0],
                'queues' => collect([]),
                'data' => $data,
                'error' => 'Error loading jobs: ' . $e->getMessage()
            ]);
        }
        
        // Parse payload for each job to extract useful info
        $jobs->getCollection()->transform(function ($job) {
            try {
                $payload = json_decode($job->payload, true);
                $job->job_class = $payload['displayName'] ?? 'Unknown';
                $job->command = null;
                $job->options = null;
                
                // Extract command info if it's ExecuteArtisanCommandJob
                if (isset($payload['data']['commandName']) && $payload['data']['commandName'] === 'App\\Jobs\\ExecuteArtisanCommandJob') {
                    try {
                        if (isset($payload['data']['command'])) {
                            $jobData = unserialize($payload['data']['command']);
                            if (is_object($jobData) && isset($jobData->command)) {
                                $job->command = $jobData->command;
                                $job->options = $jobData->options ?? [];
                            }
                        }
                    } catch (\Exception $e) {
                        // Failed to unserialize, skip
                    }
                }
                
                // Determine status
                if ($job->reserved_at) {
                    $job->status = 'processing';
                    $job->status_label = 'Processing';
                    $job->status_class = 'warning';
                } else {
                    $job->status = 'queued';
                    $job->status_label = 'Queued';
                    $job->status_class = 'info';
                }
                
            } catch (\Exception $e) {
                $job->job_class = 'Parse Error';
                $job->status = 'error';
                $job->status_label = 'Error';
                $job->status_class = 'danger';
            }
            
            return $job;
        });
        
        // Get statistics
        try {
            $stats = [
                'total' => DB::table('jobs')->count(),
                'queued' => DB::table('jobs')->whereNull('reserved_at')->count(),
                'processing' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            ];
            
            // Get unique queues
            $queues = DB::table('jobs')
                ->select('queue')
                ->distinct()
                ->whereNotNull('queue')
                ->pluck('queue');
        } catch (\Exception $e) {
            Log::error('JobsController::index error in stats', [
                'error' => $e->getMessage()
            ]);
            $stats = ['total' => 0, 'queued' => 0, 'processing' => 0];
            $queues = collect([]);
        }
        
        return view('v2.logs.jobs.index', compact('jobs', 'stats', 'queues', 'data'));
    }
    
    /**
     * Show details of a specific job
     */
    public function show($id)
    {
        $job = DB::table('jobs')->where('id', $id)->first();
        
        if (!$job) {
            abort(404, 'Job not found');
        }
        
        $data['title_page'] = "Job Details";
        session()->put('page_title', $data['title_page']);
        
        // Parse payload
        $payload = [];
        try {
            $payload = json_decode($job->payload, true) ?? [];
        } catch (\Exception $e) {
            $payload = ['error' => 'Failed to parse payload: ' . $e->getMessage()];
        }
        
        // Determine status
        $status = $job->reserved_at ? 'processing' : 'queued';
        
        return view('v2.logs.jobs.show', compact('job', 'payload', 'status', 'data'));
    }
    
    /**
     * Manually process a single job (run it immediately)
     */
    public function process($id)
    {
        try {
            $job = DB::table('jobs')->where('id', $id)->first();
            
            if (!$job) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Job not found'
                    ], 404);
                }
                return redirect()->route('v2.logs.jobs')
                    ->with('error', 'Job not found');
            }
            
            // Check if job is already being processed
            if ($job->reserved_at) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Job is already being processed'
                    ], 400);
                }
                return redirect()->route('v2.logs.jobs')
                    ->with('error', 'Job is already being processed');
            }
            
            // Parse payload to get job class
            $payload = json_decode($job->payload, true);
            if (!$payload) {
                throw new \Exception('Invalid job payload');
            }
            
            // Mark job as reserved and increment attempts
            $now = now()->timestamp;
            DB::table('jobs')
                ->where('id', $id)
                ->update([
                    'reserved_at' => $now,
                    'attempts' => ($job->attempts ?? 0) + 1
                ]);
            
            // Try to process the job by running queue:work once
            // This will pick up the reserved job and execute it
            try {
                Artisan::call('queue:work', [
                    '--once' => true,
                    '--queue' => $job->queue ?? 'default',
                    '--timeout' => 120,
                    '--tries' => 1
                ]);
            } catch (\Exception $e) {
                // If queue:work fails, reset the reserved_at
                DB::table('jobs')
                    ->where('id', $id)
                    ->update(['reserved_at' => null]);
                throw $e;
            }
            
            // Check if job was processed (should be deleted from jobs table if successful)
            $jobStillExists = DB::table('jobs')->where('id', $id)->exists();
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $jobStillExists ? 'Job execution attempted. Check logs for details.' : 'Job processed successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('success', $jobStillExists ? 'Job execution attempted. Check logs for details.' : 'Job processed successfully');
        } catch (\Exception $e) {
            Log::error('JobsController::process error', [
                'job_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to process job: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('error', 'Failed to process job: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a job
     */
    public function destroy($id)
    {
        try {
            $deleted = DB::table('jobs')->where('id', $id)->delete();
            
            if ($deleted === 0) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Job not found'
                    ], 404);
                }
                return redirect()->route('v2.logs.jobs')
                    ->with('error', 'Job not found');
            }
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Job deleted successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('success', 'Job deleted successfully');
        } catch (\Exception $e) {
            Log::error('JobsController::destroy error', [
                'job_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete job: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('error', 'Failed to delete job: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear all jobs - truncates the entire jobs table
     */
    public function clear()
    {
        try {
            // Truncate the entire jobs table
            DB::table('jobs')->truncate();
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'All jobs cleared successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('success', 'All jobs cleared');
        } catch (\Exception $e) {
            Log::error('JobsController::clear error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to clear jobs: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('v2.logs.jobs')
                ->with('error', 'Failed to clear jobs: ' . $e->getMessage());
        }
    }
}
