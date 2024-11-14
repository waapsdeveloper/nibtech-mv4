<?php

namespace App\Exports;

use App\Models\Order_item_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Variation_model;
use TCPDF;

class IMEILabelExport
{
    // public function generatePdf()
    // {
    //     $stock_id = request('stock_id');
    //     $stock = Stock_model::find($stock_id);
    //     $variation = Variation_model::with(['product', 'storage_id', 'color_id', 'grade_id', 'sub_grade_id'])
    //                 ->find($stock->variation_id);
    //     $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
    //     $movement = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->first();
    //     $imei = $stock->imei ?? 'N/A';

    //     $new_variation = $movement->old_variation;
    //     $movementDetails = $movement->created_at . ' - ' . ($movement->admin->first_name ?? 'Unknown') . ' - ' .
    //         'From: ' . ($new_variation->product->model . ' ' . $new_variation->storage_id->name ?? '' . ' ' . $new_variation->color_id->name ?? '');

    //     // Generate Barcode as an Image
    //     $barcodeImage = 'data:image/png;base64,' . base64_encode(
    //         \DNS1D::getBarcodePNG($imei, 'C128')
    //     );

    //     // Generate the HTML view content
    //     $html = view('pdf.imei_label', compact('variation', 'imei', 'orders', 'movementDetails', 'barcodeImage'))->render();

    //     // Initialize TCPDF
    //     $pdf = new TCPDF('P', 'mm', array(62, 100), true, 'UTF-8', false);
    //     $pdf->SetCreator(PDF_CREATOR);
    //     $pdf->setPrintHeader(false);
    //     $pdf->setPrintFooter(false);
    //     $pdf->SetMargins(2, 5, 2);
    //     $pdf->AddPage();
    //     $pdf->SetFont('times', '', 9);

    //     // Render HTML to PDF
    //     $pdf->writeHTML($html, true, false, true, false, '');

    //     // Output the PDF
    //     return $pdf->Output('product_label.pdf');
    // }

    public function generatePdf()
    {
        $stock_id = request('stock_id');
        $stock = Stock_model::find($stock_id);
        // Fetch the product variation, order, and stock movements
        $variation = Variation_model::with(['product', 'storage_id', 'color_id', 'grade_id', 'sub_grade_id'])
                ->find($stock->variation_id);
        $vendor = $stock->order->customer->first_name ?? 'Unknown';
        $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();

        $last_sale_order = Order_item_model::where('stock_id', $stock_id)->whereHas('order', function($q){
            $q->whereIn('order_type_id', [3,5]);
        })->orderBy('id','desc')->first();

        if($last_sale_order->order->order_type_id == 3){
            $reference = $last_sale_order->order->reference_id;
        }else{
            $reference = $last_sale_order->reference_id.' (R)';
        }

        $last_variation = Variation_model::find($last_sale_order->variation_id);
        $model = $last_variation->product->model;
        $storage = $last_variation->storage_id->name ?? '';
        $color = $last_variation->color_id->name ?? '';
        $grade = $last_variation->grade_id->name ?? '';
        $sub_grade = $last_variation->sub_grade_id->name ?? '';

        $movement = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->first();
        $comment = $movement->description ?? '';
        $explode = explode(' || ', $comment);
        if(count($explode) == 3){
            $lock = "iCloud On";
        }else{
            $lock = "iCloud Off";
        }
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

        $pdf->SetFont('times', 'B', 9);
        $pdf->MultiCell(42, 5, $reference.' | '.$grade.' '.$sub_grade, 0, 'L', false, 0, null, null, true, 0, false, true, 0, 'T', true);
        $pdf->MultiCell(18, 4, $lock, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T', true);


        $model = $variation->product->model;
        $storage = $variation->storage_id->name ?? '';
        $color = $variation->color_id->name ?? '';
        $grade = $variation->grade_id->name ?? '';
        $sub_grade = $variation->sub_grade_id->name ?? '';
        // Write product information
        $pdf->MultiCell(62, 4, $model . ' ' . $storage . ' ' . $color . ' ' . $grade . ' ' . $sub_grade, 0, 'C', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->MultiCell(62, 0, 'IMEI: '. $imei, 0, 'C', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        // Add Barcode for IMEI
        if ($imei !== 'N/A') {
            // The IMEI barcode, set the parameters for the barcode (width, height, style, etc.)
            $pdf->write1DBarcode($imei, 'C128', '', '', '', 10, 0.4, ['position' => 'C', 'align' => 'C'], 'N');
        } else {
            $pdf->Write(0, 'IMEI not available');
        }

        // Write Stock Movement history if needed
        $pdf->Ln(2); // Add some spacing

        if(count($explode) > 1){
            $pdf->MultiCell(37, 4, 'V: '.$vendor, 0, 'L', false, 0, null, null, true, 0, false, true, 0, 'T', true);
            $pdf->MultiCell(30, 4, $explode[1], 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T', true);
        }else{
            $pdf->MultiCell(62, 4, 'V: '.$vendor, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);
        }

        $pdf->MultiCell(62, 0, 'Cmt: '. $explode[0], 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->Ln(2); // Add some spacing
        $pdf->SetFont('times', '', 9);
        $pdf->Write(0, 'Stock Movement History:', '', 0, 'L', true, 0, false, false, 0);

        if (!$movement) {
            $pdf->Write(0, 'No movement history found', '', 0, 'L', true, 0, false, false, 0);
        }else{
            $new_variation = $movement->old_variation;
            $new_model = $new_variation->product->model;
            $new_storage = $new_variation->storage_id->name ?? '';
            $new_color = $new_variation->color_id->name ?? '';
            $new_grade = $new_variation->grade_id->name ?? '';
            $new_sub_grade = $new_variation->sub_grade_id->name ?? '';
            $movementDetails = $movement->created_at . ' - ' . ($movement->admin->first_name ?? 'Unknown') . ' - ' .
                ' From: ' . ($new_model . ' ' . $new_storage . ' ' . $new_color . ' ' . $new_grade . ' ' . $new_sub_grade) . ' - ' . $movement->description;
            $pdf->Write(0, $movementDetails, '', 0, 'L', true, 0, false, false, 0);
        }

        $pdf->Ln(2); // Add some spacing
        $pdf->Write(0, 'Orders History:', '', 0, 'L', true, 0, false, false, 0);
        foreach($orders as $item){
            $customer = $item->order->customer->first_name ?? 'Unknown';
            $data = 'O: '.$item->order->reference_id.' T: '.$item->order->order_type->name . ' C: ' . $customer . ' S: ' . $item->order->order_status->name;

            $pdf->Write(0, $data, '', 0, 'L', true, 0, false, false, 0);
        }
        // Output the PDF as a response
        return $pdf->Output('product_label.pdf');

    }

}
