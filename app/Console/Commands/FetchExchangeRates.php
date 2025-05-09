<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchExchangeRates extends Command
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
        try {
            $this->exchangeRateService->getRates();
            $this->info('Exchange rates updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update exchange rates: ' . $e->getMessage());
        }
    }
}
