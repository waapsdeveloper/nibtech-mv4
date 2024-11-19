<?php

namespace App\Exports;

use App\Http\Livewire\Order;
use App\Models\Order_item_model;
use App\Models\Order_model;
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
        $vendor = $stock->order->customer->first_name ?? 'Unknown';
        // $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();

        $last_sale_order = Order_item_model::where('stock_id', $stock_id)->whereHas('order', function($q){
            $q->whereIn('order_type_id', [3,5]);
        })->orderBy('id','desc')->first();
        if($last_sale_order != null){
            if($last_sale_order->order->order_type_id == 3){
                $reference = $last_sale_order->order->reference_id;
                $r_id = $reference;
            }else{
                if($last_sale_order->order->reference_id == 999){
                    $reference = $last_sale_order->order->reference_id.' (R)';
                    $r_id = $last_sale_order->order->reference_id;
                }else{
                    $reference = $last_sale_order->order->reference_id;
                    $r_id = $reference;
                }
            }
            $last_order = Order_model::where('reference_id', $r_id)->whereIn('order_type_id',[3,5])->first();

            if($last_order != null && $last_order->delivered_at == null){
                $order_l = new Order();
                $order_l->getLabel($r_id, true, true);

                $last_order = Order_model::where('reference_id', $r_id)->whereIn('order_type_id',[3,5])->first();
            }

            $last_variation = Variation_model::find($last_sale_order->variation_id);
            $model = $last_variation->product->model;
            $storage = $last_variation->storage_id->name ?? '';
            $color = $last_variation->color_id->name ?? '';
            $grade = $last_variation->grade_id->name ?? '';
            $sub_grade = $last_variation->sub_grade_id->name ?? '';
            $shipment_date = $last_order->processed_at;
            $delivery_date = $last_order->delivered_at;

        }else{
            $reference = 'N/A';
            $model = 'N/A';
            $storage = 'N/A';
            $color = 'N/A';
            $grade = 'N/A';
            $sub_grade = 'N/A';
            $shipment_date = 'N/A';
            $delivery_date = 'N/A';

        }
        $movement = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->first();
        $comment = $movement->description ?? '';
        $explode = explode(' || ', $comment);
        $lock = "iCloud Off";
        $battery = "BS: ";
        foreach($explode as $key => $item){
            if($key == 0){
                $comment = $item;
            }elseif(str_contains($item, 'L: 1')){
                $lock = "iCloud On";
            }else{
                $battery = $item;
            }
        }
        // Fallback to N/A if IMEI is not available
        $imei = $stock->imei ?? $stock->serial_number ?? 'N/A';

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
        $pdf->MultiCell(42, 5, 'SO: '.$reference.' | '.$grade.' '.$sub_grade, 0, 'L', false, 0, null, null, true, 0, false, true, 0, 'T', true);
        $pdf->MultiCell(16, 4, $lock, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->Ln(2); // Add some spacing

        $model = $variation->product->model;
        $storage = $variation->storage_id->name ?? '';
        $color = $variation->color_id->name ?? '';
        $grade = $variation->grade_id->name ?? '';
        $sub_grade = $variation->sub_grade_id->name ?? '';
        // Write product information
        $pdf->MultiCell(58, 4, $model . ' ' . $storage . ' ' . $color . ' ' . $grade . ' ' . $sub_grade, 0, 'C', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->MultiCell(58, 0, 'IMEI: '. $imei, 0, 'C', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        // Add Barcode for IMEI
        if ($imei !== 'N/A') {
            // The IMEI barcode, set the parameters for the barcode (width, height, style, etc.)
            $pdf->write1DBarcode($imei, 'C128', '', '', '', 10, 0.4, ['position' => 'C', 'align' => 'C'], 'N');
        } else {
            $pdf->Write(0, 'IMEI not available');
        }

        // Write Stock Movement history if needed
        $pdf->Ln(2); // Add some spacing

        $pdf->MultiCell(28, 4, 'V: '.$vendor, 0, 'L', false, 0, null, null, true, 0, false, true, 0, 'T', true);
        $pdf->MultiCell(20, 4, $battery, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->Ln(2); // Add some spacing

        $pdf->MultiCell(58, 0, 'Invoice: '. $shipment_date, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->MultiCell(58, 0, 'Delivery: '. $delivery_date, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        $pdf->MultiCell(58, 0, 'Update: '. $movement->created_at, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);


        $pdf->Ln(2); // Add some spacing
        $pdf->SetFont('times', 'B', 10);
        $pdf->MultiCell(58, 0, 'Cmt: '. $comment, 0, 'L', false, 1, null, null, true, 0, false, true, 0, 'T', true);

        // $pdf->Ln(2); // Add some spacing
        // $pdf->SetFont('times', '', 9);
        // $pdf->Write(0, 'Stock Movement History:', '', 0, 'L', true, 0, false, false, 0);

        if (!$movement) {
            $pdf->Write(0, 'No movement history found', '', 0, 'L', true, 0, false, false, 0);
        }else{
            // $new_variation = $movement->old_variation;
            // $new_model = $new_variation->product->model;
            // $new_storage = $new_variation->storage_id->name ?? '';
            // $new_color = $new_variation->color_id->name ?? '';
            // $new_grade = $new_variation->grade_id->name ?? '';
            // $new_sub_grade = $new_variation->sub_grade_id->name ?? '';
            // $movementDetails = $movement->created_at . ' - ' . ($movement->admin->first_name ?? 'Unknown') . ' - ' .
            //     ' From: ' . ($new_model . ' ' . $new_storage . ' ' . $new_color . ' ' . $new_grade . ' ' . $new_sub_grade) . ' - ' . $movement->description;
            // $pdf->Write(0, $movementDetails, '', 0, 'L', true, 0, false, false, 0);
        }

        // $pdf->Ln(2); // Add some spacing
        // $pdf->Write(0, 'Orders History:', '', 0, 'L', true, 0, false, false, 0);
        // foreach($orders as $item){
            // $customer = $item->order->customer->first_name ?? 'Unknown';
            // $data = 'O: '.$item->order->reference_id.' T: '.$item->order->order_type->name . ' C: ' . $customer . ' S: ' . $item->order->order_status->name;

            // $pdf->Write(0, $data, '', 0, 'L', true, 0, false, false, 0);
        // }
        // Output the PDF as a response
        return $pdf->Output('product_label.pdf');

    }

}
