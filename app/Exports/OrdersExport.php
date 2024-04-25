<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use TCPDF;
// use Picqer\Barcode\BarcodeGeneratorPNG;

class OrdersExport
{
    public function generatePdf()
    {
        // Fetch data from the database
        $data = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('variation', 'order_items.variation_id', '=', 'variation.id') // Use LEFT JOIN instead of JOIN
            ->join('products', 'variation.product_id', '=', 'products.id')
            ->leftJoin('color', 'variation.color', '=', 'color.id') // Use leftJoin instead of join
            ->leftJoin('storage', 'variation.storage', '=', 'storage.id') // Use leftJoin instead of join
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->select(
                'orders.reference_id',
                'variation.sku',
                'order_items.quantity',
                'products.model',
                'color.name as color', // Access the storage column directly
                'storage.name as storage_name', // Access the storage column directly
                'grade.name as grade_name',
                // DB::raw('SUM(order_items.quantity) as total_quantity')
            )
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
                return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
            })
            ->when(request('last_order') != '', function ($q) {
                return $q->where('orders.reference_id', '>', request('last_order'));
            })
            ->when(request('sku') != '', function ($q) {
                return $q->whereHas('order_items.variation', function ($q) {
                    $q->where('sku', 'LIKE', '%' . request('sku') . '%');
                });
                // where('orders.order_items.variation.sku', 'LIKE', '%' . request('sku') . '%');
            })
            ->when(request('imei') != '', function ($q) {
                return $q->whereHas('order_items.stock', function ($q) {
                    $q->where('imei', 'LIKE', '%' . request('imei') . '%');
                });
            })
            // ->groupBy('variation.sku', 'variation.name', 'grade.name')
            ->orderBy('orders.reference_id', 'DESC')
            ->get();

        // Create a TCPDF instance
        $pdf = new TCPDF();
        $pdf->SetMargins(10, 10, 10);

        // Add a new page
        $pdf->AddPage();
        // Add heading cell at the top center
        $pdf->Cell(0, 10, 'Order list', 0, 1, 'C');

        // Set font
        $pdf->SetFont('times', 'B', 12);

        // Add headings
        $pdf->Cell(8, 0, 'No');
        $pdf->Cell(20, 0, 'Order');
        $pdf->Cell(25, 0, 'SKU');
        $pdf->Cell(10, 0, 'Qty');
        $pdf->Cell(105, 0, 'Product Name');
        $pdf->Cell(0, 0, 'Grade');

        // Set font for data
        $pdf->SetFont('times', '', 12);

        // Create a BarcodeGenerator instance
        // $barcodeGenerator = new BarcodeGeneratorPNG();
        $i = 0;
        // Iterate through data and add to PDF
        foreach ($data as $order) {
            $i++;
            $pdf->Ln();
            // Set line style for all borders
            $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);
            // $pdf->Cell(110, 10, $order->name, 1);
            // Add Product Name (ellipsize to fit within 110)
            $pdf->Cell(8, 0, $i, 1);
            $variationName = $this->ellipsize($order->model." - ".$order->storage." - ".$order->color, 60);
            $sku = $this->ellipsize($order->sku, 13);
            $pdf->Cell(20, 0, $order->reference_id, 1);
            $pdf->Cell(25, 0, $sku, 1);
            $pdf->Cell(5, 0, $this->bold($order->quantity), 1);
            $pdf->Cell(110, 0, $variationName, 1);
            $pdf->Cell(0, 0, $order->grade_name, 1);

        }

        // Output PDF to the browser
        $pdf->Output('orders.pdf', 'I');
    }

    // Custom function for ellipsizing text
    private function ellipsize($text, $max_length) {
        if (mb_strlen($text, 'UTF-8') > $max_length) {
            $text = mb_substr($text, 0, $max_length - 3, 'UTF-8') . '...';
        }
        return $text;
    }
    // Custom function for ellipsizing text
    private function bold($text) {
        if ($text > 1) {
            $text = "(".$text.")";
        }
        return $text;
    }


}
