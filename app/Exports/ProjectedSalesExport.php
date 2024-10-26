<?php

namespace App\Exports;

use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Grade_model;
use App\Models\Order_item_model;
use App\Models\Product_storage_sort_model;
use Illuminate\Support\Facades\DB;
use TCPDF;
// use Picqer\Barcode\BarcodeGeneratorPNG;

class ProjectedSalesExport
{
    public function generatePdf()
    {
        ini_set('memory_limit', '2048M');


        $grades = [1,2,3,4,5];

        $categories = Category_model::pluck('name','id');
        $brands = Brand_model::pluck('name','id');
        $grade_names = Grade_model::whereIn('id', $grades)->pluck('name','id');
        $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function($q){
            $q->where('stock.status',1);
        })->orderBy('product_id')->orderBy('storage')->get();
        $months = [];
        $months[] = date('M-Y');
        $result = [];
        foreach($product_storage_sort as $pss){
            $datas = [];
            $product = $pss->product;
            $storage = $pss->storage_id->name ?? null;
            $datas['model'] = $product->model.' '.$storage;

            $available_stock = $pss->stocks->where('status',1)->count();
            $datas['available_stock'] = $available_stock;

            $variation_ids = $pss->variations->pluck('id');
            $datas[date('M-Y')] = Order_item_model::whereIn('variation_id', $variation_ids)->whereHas('order', function($q){
                $q->whereIn('order_type_id', [3,5])->whereBetween('processed_at', [date('Y-m-01'), date('Y-m-t')]);
            })->count();

            //Last 6 months sales
            for($i = 1; $i <= 6; $i++){
                $datas[date('M-Y', strtotime('-'.$i.' months'))] = Order_item_model::whereIn('variation_id', $variation_ids)->whereHas('order', function($q) use ($i){
                    $q->whereIn('order_type_id', [3,5])->whereBetween('processed_at', [date('Y-m-01', strtotime('-'.$i.' months')), date('Y-m-t', strtotime('-'.$i.' months'))]);
                })->count();

                if(!in_array(date('M-Y', strtotime('-'.$i.' months')), $months)){
                    $months[] = date('M-Y', strtotime('-'.$i.' months'));
                }
            }


            $result[$product->category][$product->brand][] = $datas;
        }
        // Create a TCPDF instance
        $pdf = new TCPDF();
        $pdf->SetMargins(10, 10, 10);


        foreach($result as $category => $cat){
            // Add heading cell at the top center
            foreach($cat as $brand => $datas){
                // Add a new page
                $pdf->AddPage('L');
                $pdf->SetFont('times', 'B', 16);
                $pdf->Cell(0, 15, $categories[$category] . " - " . $brands[$brand], 0, 1, 'C');
                $pdf->SetAutoPageBreak(TRUE, 15);
                // Set font

                $pdf->SetFont('times', 'B', 10);
                // Add headings
                $pdf->Cell(8, 0, 'No');
                $pdf->Cell(80, 0, 'Model');
                $pdf->Cell(12, 0, 'Available Stock');
                foreach($months as $month){
                    $pdf->Cell(12, 0, $month);
                }


                $i = 0;
                // Set font for data
                $pdf->SetFont('times', '', 12);
                $pdf->Ln(); // Move to the next line
                foreach($datas as $data) {
                    $i++;

                    // Set line style for borders
                    $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);

                    // No column (serial number)
                    $pdf->MultiCell(8, 6, $i, 1, 'L', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    // Model Name (wraps text if too long)
                    $pdf->MultiCell(80, 6, $data['model'], 1, 'L', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    $pdf->SetFont('times', 'B', 12);
                    // Stock count
                    $pdf->MultiCell(12, 6, $data['available_stock'], 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    foreach($months as $month){
                        $pdf->MultiCell(12, 6, $data[$month], 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);
                    }
                    // // Average cost
                    // $pdf->MultiCell(18, 6, '€ '.number_format($data['average_cost'], 2), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    // $pdf->SetFont('times', '', 12);
                    // // Premium Grade
                    // $pdf->MultiCell(18, 6, $this->bold($data['graded_average_cost'][1] ?? 0), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    // // Very Good Grade
                    // $pdf->MultiCell(18, 6, $this->bold($data['graded_average_cost'][2] ?? 0), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    // // Good Grade
                    // $pdf->MultiCell(18, 6, $this->bold($data['graded_average_cost'][3] ?? 0), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                    // // Stallone Grade (Grade 5)
                    // $pdf->MultiCell(18, 6, $this->bold($data['graded_average_cost'][5] ?? 0), 1, 'C', false, 1, '', '', true, 0, false, true, 6, 'T', true);
                }

            }
        }
        // Output PDF to the browser
        $pdf->Output('orders.pdf', 'I');
    }

    // Custom function for ellipsizing text
    private function bold($text) {
        if ($text != 0) {
            $text = '€ '.number_format($text,2);
        } else {
            $text = '-';
        }
        return $text;
    }


}
