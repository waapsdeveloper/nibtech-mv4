<?php

namespace App\Exports;

use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Grade_model;
use App\Models\Product_storage_sort_model;
use Illuminate\Support\Facades\DB;
use TCPDF;
// use Picqer\Barcode\BarcodeGeneratorPNG;

class StockSummeryExport
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

        $result = [];
        foreach($product_storage_sort as $pss){
            $product = $pss->product;
            $storage = $pss->storage_id->name ?? null;
            $data = [];
            $data['model'] = $product->model.' '.$storage;
            $data['stock_count'] = 0;
            $data['average_cost'] = 0;
            $data['graded_average_cost'] = [];
            $data['graded_stock_count'] = [];

            // print_r($pss->stocks->where('status',1));
            foreach($pss->stocks->where('status',1) as $stock){
                $variation = $stock->variation;
                if(in_array($variation->grade, $grades)){
                    $purchase_item = $stock->order_items->where('order_id',$stock->order_id)->first();
                    if($purchase_item == null){
                        echo 'Purchase item not found for stock id: '.$stock->id;
                        continue;
                    }
                    $data['average_cost'] += $purchase_item->price;
                    $data['stock_count']++;
                    if(!isset($data['graded_average_cost'][$variation->grade])){
                        $data['graded_average_cost'][$variation->grade] = 0;
                    }
                    if(!isset($data['graded_stock_count'][$variation->grade])){
                        $data['graded_stock_count'][$variation->grade] = 0;
                    }
                    $data['graded_average_cost'][$variation->grade] += $purchase_item->price;
                    $data['graded_stock_count'][$variation->grade]++;
                }
            }
            if($data['stock_count'] == 0){
                continue;
            }
            $data['average_cost'] = $data['average_cost']/$data['stock_count'];
            foreach($grades as $grade){
                if(!isset($data['graded_average_cost'][$grade])){
                    continue;
                }
                if(!isset($data['graded_stock_count'][$grade])){
                    continue;
                }
                $data['graded_average_cost'][$grade] = $data['graded_average_cost'][$grade]/$data['graded_stock_count'][$grade];
            }
            $result[$product->category][$product->brand][] = $data;
        }
        // Create a TCPDF instance
        $pdf = new TCPDF();
        $pdf->SetMargins(10, 10, 10);

        foreach($result as $category => $cat){
            // Add heading cell at the top center
            foreach($cat as $brand => $datas){
                // Add a new page
                $pdf->AddPage();
                $pdf->SetFont('times', 'B', 16);
                $pdf->Cell(0, 15, $categories[$category] . " - " . $brands[$brand], 0, 1, 'C');
                $pdf->SetAutoPageBreak(TRUE, 15);
                // Set font

                $pdf->SetFont('times', 'B', 10);
                // Add headings
                $pdf->Cell(8, 0, 'No');
                $pdf->Cell(80, 0, 'Model');
                $pdf->Cell(12, 0, 'Count');
                $pdf->Cell(18, 0, 'Average');
                $pdf->Cell(18, 0, 'Premium');
                $pdf->Cell(18, 0, 'Very Good');
                $pdf->Cell(18, 0, 'Good');
                $pdf->Cell(18, 0, 'Stallone');

                $i = 0;
                // Set font for data
                $pdf->SetFont('times', '', 12);

                foreach($datas as $data){
                    $i++;
                    $pdf->Ln();
                    // Set line style for all borders
                    $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);
                    // $pdf->Cell(110, 10, $order->name, 1);
                    // Add Product Name (ellipsize to fit within 110)
                    $pdf->MultiCell(8, 0, $i, 1, 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);
                    $pdf->MultiCell(80, 0, $data['model'], 1, 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);
                    $pdf->MultiCell(12, 0, $data['stock_count'], 1, 'C', false, 0, '', '', true, 0, false, true, 0, 'T', true);
                    $pdf->MultiCell(18, 0, number_format($data['average_cost'],2), 1, 'C', false, 0, '', '', true, 0, false, true, 0, 'T', true);
                    $pdf->MultiCell(18, 0, $this->bold($data['graded_average_cost'][1] ?? 0), 1, 'C', false, 0, '', '', true);
                    $pdf->MultiCell(18, 0, $this->bold($data['graded_average_cost'][2] ?? 0), 1, 'C', false, 0, '', '', true);
                    $pdf->MultiCell(18, 0, $this->bold($data['graded_average_cost'][3] ?? 0), 1, 'C', false, 0, '', '', true);
                    $pdf->MultiCell(18, 0, $this->bold($data['graded_average_cost'][5] ?? 0), 1, 'C', false, 0, '', '', true);

                }
            }
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
        if ($text != 0) {
            $text = number_format($text,2);
        } else {
            $text = '-';
        }
        return $text;
    }


}
