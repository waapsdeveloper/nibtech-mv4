<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BMPROListingsController extends Controller
{
    public function __construct(private BMPROAPIController $bmpro)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->clamp((int) $request->input('per_page', 50), 1, 200);

        $publicationState = $request->input('publication_state');

        if ($publicationState === null || $publicationState === '') {
            $publicationState = 'active';
        }

        $filters = array_filter([
            'publication_state' => $publicationState,
            'page-size' => $perPage,
            'page' => $request->input('page'),
        ], fn ($value) => $value !== null && $value !== '');

        $environment = $request->input('environment', 'prod');
        $autoPaginate = $request->boolean('auto_paginate', true);

        $options = array_filter([
            'marketplace_id' => $request->input('marketplace_id'),
            'currency' => $request->input('currency'),
        ], fn ($value) => $value !== null && $value !== '');

        $payload = $this->bmpro->getListings($filters, $environment, $autoPaginate, $options);

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
