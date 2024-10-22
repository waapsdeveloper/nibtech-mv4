<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;

class OrdersExport
{
    public function generatePdf()
    {
        // Fetch data from the database
        $data = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('variation', 'order_items.variation_id', '=', 'variation.id')
            ->join('products', 'variation.product_id', '=', 'products.id')
            ->join('color', 'variation.color', '=', 'color.id')
            ->join('storage', 'variation.storage', '=', 'storage.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->select(
                'orders.reference_id',
                'variation.sku',
                'order_items.quantity',
                'products.model',
                'color.name as color',
                'storage.name as storage',
                'grade.name as grade_name'
            )
            ->where('orders.deleted_at', null)
            ->where('orders.order_type_id', 3)
            ->when(request('start_date') != '', function ($q) {
                return $q->where('orders.created_at', '>=', request('start_date', 0));
            })
            ->when(request('end_date') != '', function ($q) {
                return $q->where('orders.created_at', '<=', request('end_date', 0) . " 23:59:59");
            })
            ->when(request('status') != '', function ($q) {
                return $q->where('orders.status', request('status'));
            })
            ->when(request('order_id') != '', function ($q) {
                if (str_contains(request('order_id'), '<')) {
                    $order_ref = str_replace('<', '', request('order_id'));
                    return $q->where('orders.reference_id', '<', $order_ref);
                } elseif (str_contains(request('order_id'), '>')) {
                    $order_ref = str_replace('>', '', request('order_id'));
                    return $q->where('orders.reference_id', '>', $order_ref);
                } elseif (str_contains(request('order_id'), '<=')) {
                    $order_ref = str_replace('<=', '', request('order_id'));
                    return $q->where('orders.reference_id', '<=', $order_ref);
                } elseif (str_contains(request('order_id'), '>=')) {
                    $order_ref = str_replace('>=', '', request('order_id'));
                    return $q->where('orders.reference_id', '>=', $order_ref);
                } elseif (str_contains(request('order_id'), '-')) {
                    $order_ref = explode('-', request('order_id'));
                    return $q->whereBetween('orders.reference_id', $order_ref);
                } elseif (str_contains(request('order_id'), ',')) {
                    $order_ref = explode(',', request('order_id'));
                    return $q->whereIn('orders.reference_id', $order_ref);
                } elseif (str_contains(request('order_id'), ' ')) {
                    $order_ref = explode(' ', request('order_id'));
                    return $q->whereIn('orders.reference_id', $order_ref);
                } else {
                    return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
                }
            })
            ->when(request('last_order') != '', function ($q) {
                return $q->where('orders.reference_id', '>', request('last_order'));
            })
            ->when(request('sku') != '', function ($q) {
                return $q->whereHas('order_items.variation', function ($q) {
                    $q->where('sku', 'LIKE', '%' . request('sku') . '%');
                });
            })
            ->when(request('imei') != '', function ($q) {
                return $q->whereHas('order_items.stock', function ($q) {
                    $q->where('imei', 'LIKE', '%' . request('imei') . '%');
                });
            })
            ->when(request('with_stock') == 2, function ($q) {
                return $q->where('order_items.stock_id', 0);
            })
            ->orderBy('orders.reference_id', 'DESC')
            ->distinct()->get();

        // Create a CustomTCPDF instance
        $pdf = new CustomTCPDF();
        $pdf->SetMargins(10, 16, 10); // Adjust top margin to make space for header

        // Add a new page
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(TRUE, 10);

        // Set font for data
        $pdf->SetFont('times', '', 12);

        // Iterate through data and add to PDF
        $i = 0;
        foreach ($data as $order) {
            $i++;
            $pdf->Ln();
            // Set line style for all borders
            $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);
            // Add Product Name (ellipsize to fit within 110)
            $variationName = $this->ellipsize($order->model . " - " . $order->storage . " - " . $order->color, 60);
            $sku = $this->ellipsize($order->sku, 13);
            $pdf->Cell(8, 5, $i, 1);
            $pdf->Cell(20, 5, $order->reference_id, 1);
            $pdf->Cell(25, 5, $sku, 1);
            $pdf->Cell(7, 5, $this->bold($order->quantity), 1);
            $pdf->Cell(110, 5, $variationName, 1);
            $pdf->Cell(0, 5, $order->grade_name, 1);
        }

        // Output PDF to the browser
        $pdf->Output('orders.pdf', 'I');
    }

    // Custom function for ellipsizing text
    private function ellipsize($text, $max_length)
    {
        if (mb_strlen($text, 'UTF-8') > $max_length) {
            $text = mb_substr($text, 0, $max_length - 3, 'UTF-8') . '...';
        }
        return $text;
    }

    // Custom function for bolding text
    private function bold($text)
    {
        if ($text > 1) {
            $text = "(" . $text . ")";
        }
        return $text;
    }
}
