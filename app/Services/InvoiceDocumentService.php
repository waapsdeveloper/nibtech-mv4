<?php

namespace App\Services;

use App\Models\Order_item_model;
use App\Models\Order_model;
use TCPDF;

class InvoiceDocumentService
{
    /**
     * Generate the standard invoice PDF for an order and return the raw binary string.
     */
    public function buildInvoicePdf(Order_model $order): string
    {
        $order->loadMissing(
            'customer.country_id',
            'order_items.stock',
            'order_items.variation.product',
            'order_items.variation.storage_id',
            'order_items.variation.color_id',
            'order_items.variation.grade_id',
            'order_items.replacement',
            'exchange_items',
            'currency_id',
            'admin'
        );

        $orderItems = $this->resolveOrderItems($order);

        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->AddPage();

        $html = view('export.invoice', [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $orderItems,
        ])->render();

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    protected function resolveOrderItems(Order_model $order)
    {
        $query = Order_item_model::where('order_id', $order->id);

        if ($query->count() > 1) {
            return $query->whereHas('stock', function ($q) {
                $q->where('status', 2)->orWhereNull('status');
            })->get();
        }

        return $query->get();
    }
}
