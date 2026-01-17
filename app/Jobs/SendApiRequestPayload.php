<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendApiRequestPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The remote endpoint URL.
     */
    protected string $endpoint;

    /**
     * Payload that should be delivered.
     *
     * @var array<mixed>
     */
    protected array $payload;

    /**
     * Optional bearer token for the remote API.
     */
    protected ?string $token;

    /**
     * Timeout (seconds) for the HTTP request.
     */
    protected int $timeoutSeconds;

    /**
     * Create a new job instance.
     */
    public function __construct(string $endpoint, array $payload, ?string $token = null, int $timeoutSeconds = 20)
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;
        $this->token = $token;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $request = Http::timeout($this->timeoutSeconds)
                ->acceptJson();

            if ($this->token) {
                $request = $request->withToken($this->token);
            }

            $response = $request->post($this->endpoint, $this->payload);

            if ($response->failed()) {
                Log::warning('SendApiRequestPayload failed', [
                    'endpoint' => $this->endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $response->throw();
            }
        } catch (\Throwable $e) {
            Log::error('SendApiRequestPayload exception', [
                'endpoint' => $this->endpoint,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Release database connection to prevent connection pool exhaustion
            DB::disconnect();
        }
    }
}
