<?php

namespace App\Console\Commands;

use App\Console\Commands\BaseCommand;
use App\Models\CommandRunLog;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Log;

class FetchExchangeRates extends BaseCommand
{
    protected $signature = 'fetch:exchange-rates';
    protected $description = 'Fetch and store exchange rates from the API';
    protected $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
        $this->exchangeRateService = $exchangeRateService;
    }

    public function handle()
    {
        CommandRunLog::recordStart('fetch-exchange-rates');
        try {
            $this->exchangeRateService->getRates();
            $this->info('Exchange rates updated successfully.');
            CommandRunLog::recordEnd('fetch-exchange-rates', 0, 1, 0, 'Exchange rates updated successfully.', 'completed');
        } catch (\Exception $e) {
            Log::error('Failed to update exchange rates: ' . $e->getMessage());
            CommandRunLog::recordEnd('fetch-exchange-rates', 0, 0, 1, 'Failed: ' . $e->getMessage(), 'failed');
        }
    }
}
