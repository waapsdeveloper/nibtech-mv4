<?php

namespace App\Exports;

use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Currency_model;
use App\Models\ExchangeRate;
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

        if (request()->has('type')) {
            $type = request()->get('type');
        } else {
            $type = 'cost';
        }
        if (request()->has('currency')) {
            $currency = request()->get('currency');
        } else {
            $currency = 4;
            $sign = '€';
        }
        if ($currency != 4) {
            $curr = Currency_model::find($currency);
            $exchange_rate = ExchangeRate::where('target_currency', $curr->code)->first();
            if ($exchange_rate) {
                $exchange_rate = $exchange_rate->rate;
                $sign = $curr->sign;
            } else {
                $exchange_rate = 1;
                $sign = '€';
            }

        }

        $grades = [1,2,3,4,5];

        $categories = Category_model::pluck('name','id');
        $brands = Brand_model::pluck('name','id');
        $grade_names = Grade_model::whereIn('id', $grades)->pluck('name','id');
        $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function($q){
            $q->where('stock.status',1);
        })
        ->join('products', 'product_storage_sort.product_id', '=', 'products.id')
        ->orderBy('products.model')
        // ->orderBy('product_id')
        ->orderBy('product_storage_sort.storage')
        ->select('product_storage_sort.*')
        ->with(['product','stocks' => function($q){
            $q->where('stock.status',1);
        }, 'stocks.variation'])
        ->get();

        $result = [];
        foreach($product_storage_sort as $pss){
            $product = $pss->product;
            $storage = $pss->storage_id->name ?? null;
            $data = [];
            $data['model'] = $product->model.' '.$storage;
            $data['stock_count'] = 0;
            $data['average_cost'] = 0;
            $data['average_price'] = 0;
            $data['graded_average_cost'] = [];
            $data['graded_average_price'] = [];
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
                    $data['average_price'] += ($purchase_item->price*0.06+$purchase_item->price) * $exchange_rate;
                    $data['stock_count']++;
                    if(!isset($data['graded_average_cost'][$variation->grade])){
                        $data['graded_average_cost'][$variation->grade] = 0;
                    }
                    if(!isset($data['graded_average_price'][$variation->grade])){
                        $data['graded_average_price'][$variation->grade] = 0;
                    }
                    if(!isset($data['graded_stock_count'][$variation->grade])){
                        $data['graded_stock_count'][$variation->grade] = 0;
                    }
                    $data['graded_average_cost'][$variation->grade] += $purchase_item->price;
                    $data['graded_average_price'][$variation->grade] += ($purchase_item->price*0.06+$purchase_item->price) * $exchange_rate;
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
            $data['average_price'] = $data['average_price']/$data['stock_count'];
            foreach($grades as $grade){
                if(!isset($data['graded_average_price'][$grade])){
                    continue;
                }
                if(!isset($data['graded_stock_count'][$grade])){
                    continue;
                }
                $data['graded_average_price'][$grade] = $data['graded_average_price'][$grade]/$data['graded_stock_count'][$grade];
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
                $pdf->Cell(0, 15, $categories[$category] . " - " . $brands[$brand], 0, 0, 'L');
                $pdf->SetFont('times', 'B', 12);
                $pdf->Cell(0, 15, 'DateTime: '.date('Y-m-d h:i:s'), 0, 1, 'R');
                $pdf->SetAutoPageBreak(TRUE, 15);
                // Set font

                // Add headings
                $pdf->SetFont('times', 'B', 10);
                if ($type == 'cost') {
                    $pdf->Cell(8, 0, 'No');
                    $pdf->Cell(80, 0, 'Model');
                    $pdf->Cell(12, 0, 'Count', 0, 0, 'C');
                    $pdf->Cell(18, 0, 'Average', 0, 0, 'C');
                    $pdf->Cell(18, 0, 'Premium');
                    $pdf->Cell(18, 0, 'Very Good', 0, 0, 'C');
                    $pdf->Cell(18, 0, 'Good', 0, 0, 'C');
                    $pdf->Cell(18, 0, 'Stallone', 0, 0, 'C');
                } else {
                    $pdf->Cell(8, 0, 'No');
                    $pdf->Cell(84, 0, 'Model');
                    $pdf->Cell(14, 0, 'Count', 0, 0, 'C');
                    $pdf->Cell(21, 0, 'Average', 0, 0, 'C');
                    // $pdf->Cell(18, 0, 'Premium');
                    $pdf->Cell(21, 0, 'A+', 0, 0, 'C');
                    $pdf->Cell(21, 0, 'A/A-', 0, 0, 'C');
                    $pdf->Cell(21, 0, 'B+', 0, 0, 'C');
                }

                $i = 0;
                // Set font for data
                $pdf->SetFont('times', '', 12);
                    $pdf->Ln(); // Move to the next line
                foreach($datas as $data) {
                    $i++;
                    if ($i == 43) {
                        $pdf->AddPage();
                        $pdf->SetFont('times', 'B', 10);
                        if ($type == 'cost') {
                            $pdf->Cell(8, 0, 'No');
                            $pdf->Cell(80, 0, 'Model');
                            $pdf->Cell(12, 0, 'Count', 0, 0, 'C');
                            $pdf->Cell(18, 0, 'Average', 0, 0, 'C');
                            $pdf->Cell(18, 0, 'Premium');
                            $pdf->Cell(18, 0, 'Very Good', 0, 0, 'C');
                            $pdf->Cell(18, 0, 'Good', 0, 0, 'C');
                            $pdf->Cell(18, 0, 'Stallone', 0, 0, 'C');
                        } else {
                            $pdf->Cell(8, 0, 'No');
                            $pdf->Cell(84, 0, 'Model');
                            $pdf->Cell(14, 0, 'Count', 0, 0, 'C');
                            $pdf->Cell(21, 0, 'Average', 0, 0, 'C');
                            // $pdf->Cell(18, 0, 'Premium');
                            $pdf->Cell(21, 0, 'A+', 0, 0, 'C');
                            $pdf->Cell(21, 0, 'A/A-', 0, 0, 'C');
                            $pdf->Cell(21, 0, 'B+', 0, 0, 'C');
                        }
                        $pdf->Ln(); // Move to the next line
                        $pdf->SetFont('times', '', 12);
                    }

                    // Set line style for borders
                    $pdf->SetLineStyle(['width' => 0.1, 'color' => [0, 0, 0]]);

                    // No column (serial number)
                    $pdf->MultiCell(8, 6, $i, 1, 'L', false, 0, '', '', true, 0, false, true, 6, 'T', true);
                    if ($type == 'cost'){

                        // Model Name (wraps text if too long)
                        $pdf->MultiCell(80, 6, $data['model'], 1, 'L', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        $pdf->SetFont('times', 'B', 12);
                        // Stock count
                        $pdf->MultiCell(12, 6, $data['stock_count'], 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Average cost
                        $pdf->MultiCell(18, 6, $sign.' '.number_format($data['average_price'], 2), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        $pdf->SetFont('times', '', 12);
                        // Premium Grade
                        $pdf->MultiCell(18, 6, $this->bold($data['graded_average_price'][1] ?? 0, $sign), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Very Good Grade
                        $pdf->MultiCell(18, 6, $this->bold($data['graded_average_price'][2] ?? 0, $sign), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Good Grade
                        $pdf->MultiCell(18, 6, $this->bold($data['graded_average_price'][3] ?? 0, $sign), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Stallone Grade (Grade 5)
                        $pdf->MultiCell(18, 6, $this->bold($data['graded_average_price'][5] ?? 0, $sign), 1, 'C', false, 1, '', '', true, 0, false, true, 6, 'T', true);
                    } else {
                        // Model Name (wraps text if too long)
                        $pdf->MultiCell(84, 6, $data['model'], 1, 'L', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        $pdf->SetFont('times', 'B', 12);
                        // Stock count
                        $pdf->MultiCell(14, 6, $data['stock_count'], 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Average cost
                        $pdf->MultiCell(21, 6, $sign.' '.number_format($data['average_price'], 2), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        $pdf->SetFont('times', '', 12);
                        // Premium Grade
                        // $pdf->MultiCell(18, 6, $this->bold($data['graded_average_price'][1] ?? 0), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Very Good Grade
                        $pdf->MultiCell(21, 6, $this->bold($data['graded_average_price'][2] ?? 0, $sign), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Good Grade
                        $pdf->MultiCell(21, 6, $this->bold($data['graded_average_price'][3] ?? 0, $sign), 1, 'C', false, 0, '', '', true, 0, false, true, 6, 'T', true);

                        // Stallone Grade (Grade 5)
                        $pdf->MultiCell(21, 6, $this->bold($data['graded_average_price'][5] ?? 0, $sign), 1, 'C', false, 1, '', '', true, 0, false, true, 6, 'T', true);
                    }
                }

            }
        }
        // Output PDF to the browser
        $pdf->Output('orders.pdf', 'I');
    }

    // Custom function for ellipsizing text
    private function bold($text, $sign) {
        if ($text != 0) {
            $text = $sign.' '.number_format($text,2);
        } else {
            $text = '-';
        }
        return $text;
    }


}
