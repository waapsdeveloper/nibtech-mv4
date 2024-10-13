<?php

namespace App\Exports;

use App\Models\Order_item_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Variation_model;
use TCPDF;

class IMEILabelExport
{
    public function generatePdf()
    {
        $stock_id = request('stock_id');
        $stock = Stock_model::find($stock_id);

        if (!$stock) {
            return response()->json(['error' => 'Stock not found'], 404);
        }

        // Fetch the product variation, order, and stock movements
        $variation = Variation_model::with(['product', 'storage', 'color', 'grade'])
                    ->find($stock->variation_id);

        $orders = Order_item_model::where('stock_id', $stock_id)
                    ->orderBy('id', 'desc')->get();

        $stock_operations = Stock_operations_model::where('stock_id', $stock_id)
                            ->orderBy('id', 'desc')->get();

        // Use a fallback if the IMEI is not available
        $imei = $stock->imei ?? 'N/A';

        // Create a new PDF document using TCPDF
        $pdf = new TCPDF('P', 'mm', array(62, 100), true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Product Label');
        $pdf->SetSubject('Product Label with History');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(2, 5, 2);

        // Add a page
        $pdf->AddPage();

        // Set font for the content
        $pdf->SetFont('helvetica', '', 9);

        // Prepare product information
        $model = $variation->product->model ?? 'N/A';
        $storage = $variation->storage->name ?? 'N/A';
        $color = $variation->color->name ?? 'N/A';
        $grade = $variation->grade->name ?? 'N/A';

        // Write product information
        $html = '
            <h5 style="margin:0px; padding:0px;">
                <strong>' . $model . ' ' . $storage . ' ' . $color . ' ' . $grade . '</strong><br>
                <strong>IMEI:</strong> ' . $imei . '
            </h5>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Add Barcode for IMEI if available
        if ($imei !== 'N/A') {
            $pdf->write1DBarcode($imei, 'C128', '', '', '', 10, 0.4, ['position' => 'C', 'align' => 'C'], 'N');
        } else {
            $pdf->Write(0, 'IMEI not available', '', 0, 'L', true, 0, false, false, 0);
        }

        // Add stock movement history
        $pdf->Ln(5); // Add some spacing
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Write(0, 'Stock Movement History:', '', 0, 'L', true, 0, false, false, 0);

        // Write Stock Movements history
        foreach ($stock_operations as $movement) {
            $new_variation = $movement->new_variation;

            // Use fallback if new variation is not available
            $new_model = $new_variation->product->model ?? 'N/A';
            $new_storage = $new_variation->storage->name ?? 'N/A';
            $new_color = $new_variation->color->name ?? 'N/A';
            $new_grade = $new_variation->grade->name ?? 'N/A';
            $admin = $movement->admin->first_name ?? 'Unknown';

            $movementDetails = $movement->created_at . ' - ' . $admin . ' - ' .
                ' To: ' . $new_model . ' ' . $new_storage . ' ' . $new_color . ' ' . $new_grade .
                ' - ' . $movement->description;

            $pdf->Write(0, $movementDetails, '', 0, 'L', true, 0, false, false, 0);
        }

        // Output the PDF as a response
        return $pdf->Output('product_label.pdf', 'I');
    }
}
