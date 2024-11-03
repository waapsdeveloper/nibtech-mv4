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
        // Fetch the product variation, order, and stock movements
        $variation = Variation_model::with(['product', 'storage_id', 'color_id', 'grade_id', 'sub_grade_id'])
                ->find($stock->variation_id);

        $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();

        $movement = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->first();
        // Fallback to N/A if IMEI is not available
        $imei = $stock->imei ?? 'N/A';

        // Create a new PDF document using TCPDF
        $pdf = new TCPDF('P', 'mm', array(62, 100), true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(2, 5, 2);

        // Add a page
        $pdf->AddPage();

        // Set font for the content
        $pdf->SetFont('times', '', 9);
        $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);
        $model = $variation->product->model;
        $storage = $variation->storage_id->name ?? '';
        $color = $variation->color_id->name ?? '';
        $grade = $variation->grade_id->name ?? '';
        $sub_grade = $variation->sub_grade_id->name ?? '';
        // Write product information
        $html = '
            <strong>' . $model . ' ' . $storage . ' ' . $color . ' ' . $grade . ' ' . $sub_grade . '<br>
            IMEI:</strong> ' . $imei;

        $pdf->writeHTML($html, true, false, true, false, '');

        // Add Barcode for IMEI
        if ($imei !== 'N/A') {
            // The IMEI barcode, set the parameters for the barcode (width, height, style, etc.)
            $pdf->write1DBarcode($imei, 'C128', '', '', '', 10, 0.4, ['position' => 'C', 'align' => 'C'], 'N');
        } else {
            $pdf->Write(0, 'IMEI not available');
        }

        // Write Stock Movement history if needed
        $pdf->Ln(2); // Add some spacing
        $pdf->SetFont('times', '', 9);
        $pdf->Write(0, 'Stock Movement History:', '', 0, 'L', true, 0, false, false, 0);

        $new_variation = $movement->old_variation;
        $new_model = $new_variation->product->model;
        $new_storage = $new_variation->storage_id->name ?? '';
        $new_color = $new_variation->color_id->name ?? '';
        $new_grade = $new_variation->grade_id->name ?? '';
        $new_sub_grade = $new_variation->sub_grade_id->name ?? '';
        $movementDetails = $movement->created_at . ' - ' . ($movement->admin->first_name ?? 'Unknown') . ' - ' .
            ' From: ' . ($new_model . ' ' . $new_storage . ' ' . $new_color . ' ' . $new_grade . ' ' . $new_sub_grade) . ' - ' . $movement->description;
        $pdf->Write(0, $movementDetails, '', 0, 'L', true, 0, false, false, 0);


        $pdf->Ln(2); // Add some spacing
        $pdf->Write(0, 'Orders History:', '', 0, 'L', true, 0, false, false, 0);
        foreach($orders as $item){
            $customer = $item->order->customer->first_name ?? 'Unknown';
            $data = 'Order: '.$item->order->reference_id.' T: '.$item->order->order_type->name . ' C: ' . $customer . ' S: ' . $item->order->order_status->name;

            $pdf->Write(0, $data, '', 0, 'L', true, 0, false, false, 0);
        }

        // Output the PDF as a response
        return $pdf->Output('product_label.pdf');

    }

}
