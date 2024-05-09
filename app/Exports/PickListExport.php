<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use TCPDF;
use Picqer\Barcode\BarcodeGeneratorPNG;

class PickListExport
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
                'variation.sku',
                'products.model',
                'color.name as color',
                'storage.name as storage',
                'grade.name as grade_name',
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->where('orders.deleted_at',null)
            ->where('orders.order_type_id',3)
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
            ->groupBy('variation.sku', 'products.model', 'color.name', 'storage.name', 'grade.name')
            ->orderBy('products.model', 'ASC')
            ->orderBy('storage.name', 'ASC')
            ->orderBy('color.name', 'ASC')
            ->orderBy('grade.name', 'ASC')
            ->get();

        // Create a TCPDF instance
        $pdf = new TCPDF();
        $pdf->SetMargins(10, 10, 10);

        // Add a new page
        $pdf->AddPage();
        // Add heading cell at the top center
        $pdf->Cell(0, 10, 'Pick list', 0, 1, 'C');

        // Set font
        $pdf->SetFont('times', 'B', 12);

        // Add headings
        $pdf->Cell(8, 10, 'No');
        $pdf->Cell(110, 10, 'Product Name');
        $pdf->Cell(20, 10, 'Grade');
        $pdf->Cell(8, 10, 'Qty');
        $pdf->Cell(50, 10, 'Barcode');

        // Set font for data
        $pdf->SetFont('times', '', 12);

        // Create a BarcodeGenerator instance
        $barcodeGenerator = new BarcodeGeneratorPNG();
        $i = 0;
        // Iterate through data and add to PDF
        foreach ($data as $order) {
            $i++;
            $pdf->Ln();
            // Set line style for all borders
            $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);
            // $pdf->Cell(110, 10, $order->name, 1);
            // Add Product Name (ellipsize to fit within 110)
            $pdf->Cell(8, 10, $i, 1);
            $variationName = $this->ellipsize($order->model." - ".$order->storage." - ".$order->color, 60);
            $pdf->Cell(110, 10, $variationName, 1);
            $pdf->Cell(22, 10, $order->grade_name, 1);
            $pdf->Cell(5, 10, $order->total_quantity, 1);

            // Generate and add barcode with SKU text
            $barcodeImage = $this->generateBarcodeWithSku($barcodeGenerator, $order->sku);
            $pdf->Image($barcodeImage, $pdf->GetX() + 2, $pdf->GetY() + 1, 50, 13);
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

    private function generateBarcodeWithSku($barcodeGenerator, $sku)
    {
        // Generate barcode image
        $barcodeImage = imagecreatefromstring($barcodeGenerator->getBarcode($sku, $barcodeGenerator::TYPE_CODE_128));

        // Create a new image with space for SKU below the barcode
        $combinedImageWidth = imagesx($barcodeImage) + 20; // Adjust the space as needed
        $combinedImageHeight = imagesy($barcodeImage) + 30; // Adjust the space as needed
        $combinedImage = imagecreatetruecolor($combinedImageWidth, $combinedImageHeight);

        // Set background color to white
        $whiteColor = imagecolorallocate($combinedImage, 255, 255, 255);
        imagefill($combinedImage, 0, 0, $whiteColor);

        // Copy barcode into the new image
        imagecopy($combinedImage, $barcodeImage, 0, 0, 0, 0, imagesx($barcodeImage), imagesy($barcodeImage));

        // Use a built-in font for SKU text
        $font = 5; // Built-in font number (5 represents a small font, you can experiment with different values)
        $skuColor = imagecolorallocate($combinedImage, 0, 0, 0); // Black color for SKU text

        // Calculate the position to center the SKU text
        $skuWidth = imagefontwidth($font) * strlen($sku);
        $skuX = ($combinedImageWidth - $skuWidth) / 2;
        $skuY = imagesy($barcodeImage) - 2; // Adjust the space between barcode and SKU as needed

        // Add SKU text to the new image
        imagestring($combinedImage, $font, $skuX, $skuY, $sku, $skuColor);

        // Save the combined image
        $path = storage_path('app/barcodes/');
        $filename = $sku . '.png';
        imagepng($combinedImage, $path . $filename);

        return $path . $filename;
    }

}
