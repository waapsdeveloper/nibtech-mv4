<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\ExchangeRate;

class ExchangeRateService
{
    protected $client;
    protected $apiKey;
    protected $baseUri = 'https://v6.exchangerate-api.com/v6/';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('EXCHANGERATE_API_KEY');
    }

    public function getRates($baseCurrency = 'EUR')
    {
        $response = $this->client->get($this->baseUri . $this->apiKey . '/latest/' . $baseCurrency);

        $data = json_decode($response->getBody(), true);

        if ($data['result'] == 'success') {
            $this->storeRates($baseCurrency, $data['conversion_rates']);
            return $data['conversion_rates'];
        } else {
            throw new \Exception('Failed to fetch exchange rates: ' . $data['error-type']);
        }
    }

    protected function storeRates($baseCurrency, $rates)
    {
        foreach ($rates as $targetCurrency => $rate) {
            ExchangeRate::updateOrCreate(
                ['base_currency' => $baseCurrency, 'target_currency' => $targetCurrency],
                ['rate' => $rate]
            );
        }
    }
}
