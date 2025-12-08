<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BMPROOrdersController extends Controller
{
    public function __construct(private BMPROAPIController $bmpro)
    {
    }

    public function pending(Request $request): JsonResponse
    {
        $perPage = $this->clamp((int) $request->input('per_page', 50), 1, 200);

        $fulfillmentStatus = $request->input('fulfillment_status');

        if ($fulfillmentStatus === null || $fulfillmentStatus === '') {
            $fulfillmentStatus = 'fulfilled';
        }

        $filters = array_filter([
            'fulfillment_status' => $fulfillmentStatus,
            'financial_status' => $request->input('financial_status'),
            'page-size' => $perPage,
            'page' => $request->input('page'),
        ], fn ($value) => $value !== null && $value !== '');

        $environment = $request->input('environment', 'prod');
        $autoPaginate = $request->boolean('auto_paginate', true);

        $options = array_filter([
            'marketplace_id' => $request->input('marketplace_id'),
            'currency' => $request->input('currency'),
        ], fn ($value) => $value !== null && $value !== '');

        $payload = $this->bmpro->getOrders($filters, $environment, $autoPaginate, $options);

        return response()->json([
            'request' => [
                'filters' => $filters,
                'environment' => $environment,
                'auto_paginate' => $autoPaginate,
                'options' => $options,
            ],
            'response' => $payload,
        ]);
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }
}
