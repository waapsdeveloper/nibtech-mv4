<?php

namespace App\Services;

use App\Http\Controllers\RefurbedAPIController;
use App\Models\Order_model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefurbedCommercialInvoiceService
{
    public function __construct(private InvoiceDocumentService $invoiceDocumentService)
    {
    }

    /**
     * Ensure that Refurbed has a commercial invoice for the given order. If the invoice
     * is missing, we generate the PDF and upload it before shipping so that label
     * creation can reference the resulting commercial invoice number.
     */
    public function ensureCommercialInvoice(Order_model $order, RefurbedAPIController $refurbedApi): ?array
    {
        if (! $order->reference_id) {
            return null;
        }

        try {
            return $refurbedApi->getOrderCommercialInvoice($order->reference_id);
        } catch (Throwable $e) {
            if (! $this->shouldAttemptUpload($e)) {
                Log::info('Refurbed: Commercial invoice fetch failed and upload skipped', $this->context($order, [
                    'error' => $e->getMessage(),
                ]));

                return null;
            }

            return $this->uploadCommercialInvoice($order, $refurbedApi);
        }
    }

    /**
     * Upload a freshly generated commercial invoice PDF for the supplied order.
     */
    public function uploadCommercialInvoice(Order_model $order, RefurbedAPIController $refurbedApi): ?array
    {
        if (! $order->reference_id) {
            return null;
        }

        try {
            $binary = $this->invoiceDocumentService->buildInvoicePdf($order);
        } catch (Throwable $e) {
            Log::error('Refurbed: Failed to render invoice PDF before upload', $this->context($order, [
                'error' => $e->getMessage(),
            ]));

            return null;
        }

        $documentSize = strlen($binary);
        $maxTotalBytes = 2 * 1024 * 1024; // API hard limit per documentation.

        if ($documentSize > $maxTotalBytes) {
            Log::error('Refurbed: Commercial invoice exceeds 2MB upload limit', $this->context($order, [
                'size_bytes' => $documentSize,
            ]));

            return null;
        }

        $invoiceNumber = $this->buildCommercialInvoiceNumber($order);

        try {
            $response = $refurbedApi->uploadOrderCommercialInvoice(
                $order->reference_id,
                $invoiceNumber,
                $binary
            );
        } catch (Throwable $e) {
            if ($this->isNotEligible($e)) {
                Log::info('Refurbed: Commercial invoice upload not required', $this->context($order, [
                    'error' => $e->getMessage(),
                ]));

                return null;
            }

            Log::error('Refurbed: Uploading commercial invoice failed', $this->context($order, [
                'error' => $e->getMessage(),
            ]));

            return null;
        }

        Log::info('Refurbed: Commercial invoice uploaded', $this->context($order, [
            'commercial_invoice_number' => data_get($response, 'commercial_invoice_number') ?? $invoiceNumber,
            'url' => data_get($response, 'url'),
            'size' => data_get($response, 'size'),
        ]));

        return $response;
    }

    protected function shouldAttemptUpload(Throwable $exception): bool
    {
        return $this->isNotFound($exception);
    }

    protected function isNotFound(Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $exception->response) {
            $status = $exception->response->status();
            $code = data_get($exception->response->json(), 'code');

            return $status === 404 || $code === 5;
        }

        return str_contains(strtolower($exception->getMessage()), 'not found');
    }

    protected function isNotEligible(Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $exception->response) {
            $status = $exception->response->status();
            $code = data_get($exception->response->json(), 'code');

            // FAILED_PRECONDITION => 9, INVALID_ARGUMENT => 3 (size/format issues)
            return in_array($status, [400, 412], true) || in_array($code, [3, 9], true);
        }

        return false;
    }

    protected function buildCommercialInvoiceNumber(Order_model $order): string
    {
        $reference = trim((string) ($order->reference_id ?? $order->id));

        if ($reference === '') {
            $reference = (string) $order->id;
        }

        return 'INV-' . $reference;
    }

    protected function context(Order_model $order, array $extras = []): array
    {
        return array_merge([
            'order_id' => $order->id,
            'order_reference' => $order->reference_id,
        ], $extras);
    }
}
