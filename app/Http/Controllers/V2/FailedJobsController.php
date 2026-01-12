<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class FailedJobsController extends Controller
{
    /**
     * Display failed jobs
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Failed Jobs";
        session()->put('page_title', $data['title_page']);
        
        // Get filter parameters
        $queue = $request->get('queue');
        $perPage = $request->get('per_page', 20);
        
        // Build query
        $query = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc');
        
        if ($queue) {
            $query->where('queue', $queue);
        }
        
        // Get failed jobs
        $failedJobs = $query->paginate($perPage);
        
        // Parse payload for each job to extract useful info
        $failedJobs->getCollection()->transform(function ($job) {
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
                
                // Extract exception message
                try {
                    $exception = json_decode($job->exception, true);
                    $job->exception_message = $exception['message'] ?? ($job->exception ? 'Error parsing exception' : 'Unknown error');
                    $job->exception_class = $exception['exception'] ?? 'Unknown';
                } catch (\Exception $e) {
                    $job->exception_message = $job->exception ? substr($job->exception, 0, 200) : 'Unknown error';
                    $job->exception_class = 'Exception';
                }
            } catch (\Exception $e) {
                $job->job_class = 'Parse Error';
                $job->exception_message = 'Failed to parse job data: ' . $e->getMessage();
                $job->exception_class = 'ParseException';
            }
            
            return $job;
        });
        
        // Get statistics
        $stats = [
            'total' => DB::table('failed_jobs')->count(),
            'last_24h' => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count(),
            'last_7d' => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDays(7))
                ->count(),
        ];
        
        // Get unique queues
        $queues = DB::table('failed_jobs')
            ->select('queue')
            ->distinct()
            ->whereNotNull('queue')
            ->pluck('queue');
        
        return view('v2.logs.failed-jobs.index', compact('failedJobs', 'stats', 'queues', 'data'));
    }
    
    /**
     * Show details of a specific failed job
     */
    public function show($id)
    {
        $job = DB::table('failed_jobs')->find($id);
        
        if (!$job) {
            abort(404, 'Failed job not found');
        }
        
        $data['title_page'] = "Failed Job Details";
        session()->put('page_title', $data['title_page']);
        
        // Parse payload
        $payload = [];
        try {
            $payload = json_decode($job->payload, true) ?? [];
        } catch (\Exception $e) {
            $payload = ['error' => 'Failed to parse payload: ' . $e->getMessage()];
        }
        
        // Parse exception
        $exception = [];
        try {
            if ($job->exception) {
                $exception = json_decode($job->exception, true) ?? [];
            }
        } catch (\Exception $e) {
            $exception = ['error' => 'Failed to parse exception: ' . $e->getMessage()];
        }
        
        return view('v2.logs.failed-jobs.show', compact('job', 'payload', 'exception', 'data'));
    }
    
    /**
     * Retry a failed job
     */
    public function retry($id)
    {
        try {
            Artisan::call('queue:retry', ['id' => $id]);
            
            return redirect()->route('v2.logs.failed-jobs')
                ->with('success', 'Job queued for retry');
        } catch (\Exception $e) {
            return redirect()->route('v2.logs.failed-jobs')
                ->with('error', 'Failed to retry job: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a failed job
     */
    public function destroy($id)
    {
        try {
            DB::table('failed_jobs')->where('id', $id)->delete();
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Failed job deleted successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.failed-jobs')
                ->with('success', 'Failed job deleted successfully');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete job: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('v2.logs.failed-jobs')
                ->with('error', 'Failed to delete job: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete all failed jobs
     */
    public function clear()
    {
        try {
            DB::table('failed_jobs')->truncate();
            
            return redirect()->route('v2.logs.failed-jobs')
                ->with('success', 'All failed jobs cleared');
        } catch (\Exception $e) {
            return redirect()->route('v2.logs.failed-jobs')
                ->with('error', 'Failed to clear jobs: ' . $e->getMessage());
        }
    }
}
