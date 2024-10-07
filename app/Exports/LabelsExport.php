<?php

namespace App\Exports;

use App\Http\Livewire\Order;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use App\Models\Order_model;
use setasign\Fpdi\PdfParser\PdfParserException;

class LabelsExport
{
    public function generatePdf()
    {


        // Fetch data from the database
        $data = Order_model::
        join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->where('orders.label_url', '!=', null)->where('order_items.deleted_at', null)->where('variation.deleted_at', null)->where('products.deleted_at', null)->whereIn('orders.id', request('ids'))

        ->when(request('sort') == 4, function ($q) {
            return $q->orderBy('products.model', 'DESC')
                ->orderBy('variation.storage', 'DESC')
                ->orderBy('variation.color', 'DESC')
                ->orderBy('variation.grade', 'DESC')
                ->orderBy('variation.sku', 'DESC')
                ->orderBy('orders.reference_id', 'ASC');
        })
        ->when(request('sort') == 1 || request('sort') == '', function ($q) {
            return $q->orderBy('orders.reference_id', 'ASC'); // Secondary order by reference_id
        })
        ->select('orders.id','orders.label_url','orders.reference_id')
        ->pluck('label_url','reference_id')->unique()->toArray();
        // dd($data);
        // Output PDF to the browser
        $pdf = $this->generateMergedPdf($data);

        $pdf->Output('orders.pdf', 'I');
    }

    private function generateMergedPdf($data)
    {

        if(request('missing') == 'scan'){
            $oc = new Order();
        }
        // Create instance of Fpdi
        $pdf = new Fpdi();

        // Create Guzzle client
        $client = new Client();

        // Set the fixed page size (102mm x 210mm)
        // $pdf->AddPage('P', array(102, 210));

        // Iterate through each order data and fetch PDFs
        foreach ($data as $ref => $order) {

            if(request('missing') == 'scan'){
                $oc->getLabel($ref, false, true);
                continue;
            }
            // Add a new page with fixed size for the next label
            $pdf->AddPage('P', array(102, 210));
            // Fetch PDF content using Guzzle
            $response = $client->get($order);
            $pdfContent = $response->getBody()->getContents();

            // Convert Guzzle stream to StreamReader
            $streamReader = StreamReader::createByString($pdfContent);
            try {
            // Set the source file for the PDF
            $pdf->setSourceFile($streamReader);
            } catch (PdfParserException $e) {
                // echo 'An error occurred while parsing the PDF: ' . $e->getMessage();
                continue;
            }

            // Use the imported template
            $tplIdx = $pdf->importPage(1);
            $pdf->useTemplate($tplIdx);

        }
        if(request('missing') == 'scan'){
            return redirect()->back();
        }

        return $pdf;
    }
}
