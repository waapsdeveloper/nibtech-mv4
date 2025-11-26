<?php

namespace App\Console\Commands;

use App\Http\Controllers\RefurbedListingsController;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RefurbedUpdateStock extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'refurbed:update-stock';

    /**
     * The console command description.
     */
    protected $description = 'Sync Refurbed listing stock levels with the latest system quantities.';

    public function __construct(private RefurbedListingsController $refurbedListingsController)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Refurbed stock update...');

        try {
            $response = $this->refurbedListingsController->updateStockFromSystem();
            $payload = $this->normalizePayload($response);

            if (($response instanceof JsonResponse) && $response->getStatusCode() >= 400) {
                $message = $payload['message'] ?? 'Refurbed stock update failed';
                $this->error($message);

                return self::FAILURE;
            }

            $message = $payload['message'] ?? 'Refurbed stock update completed';
            $this->info($message);

            if (! empty($payload)) {
                $this->line(json_encode($payload));
            }

            Log::info('Refurbed: stock update command completed', $payload);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Refurbed: stock update command threw an exception', [
                'error' => $e->getMessage(),
            ]);

            $this->error('Refurbed stock update failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function normalizePayload(mixed $response): array
    {
        if ($response instanceof JsonResponse) {
            return (array) $response->getData(true);
        }

        return [];
    }
}
