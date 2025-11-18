<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefurbedListingsController extends Controller
{
    protected RefurbedAPIController $refurbed;

    public function __construct(RefurbedAPIController $refurbed)
    {
        $this->refurbed = $refurbed;
    }

    public function active(Request $request): JsonResponse
    {
        $perPage = $this->clampPageSize((int) $request->input('per_page', 50));
        $states = $this->normalizeList($request->input('state'), ['ACTIVE']);

        $filter = [
            'state' => [
                'any_of' => $states,
            ],
        ];

        $pagination = $this->buildPagination($perPage, $request->input('page_token'));
        $sort = $this->buildSort(
            $request->input('sort_by'),
            $request->input('sort_direction', 'ASC')
        );

        $payload = $this->refurbed->listOffers($filter, $pagination, $sort);

        return response()->json([
            'filters' => [
                'states' => $states,
                'page_size' => $pagination['page_size'] ?? $perPage,
            ],
            'data' => $payload,
        ]);
    }

    private function normalizeList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            $value = [];
        }

        $value = array_values(array_filter($value));

        return empty($value) ? $default : $value;
    }

    private function clampPageSize(int $perPage): int
    {
        return max(1, min($perPage, 200));
    }

    private function buildPagination(int $perPage, ?string $pageToken): array
    {
        return array_filter([
            'page_size' => $perPage,
            'page_token' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function buildSort(?string $field, string $direction): array
    {
        if (! $field) {
            return [];
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return [
            'order_by' => $field,
            'direction' => $direction,
        ];
    }
}
