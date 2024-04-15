<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use GuzzleHttp\Client;
use TCPDF;

class DeliveryNotesExport
{
    public function generatePdf()
    {
        try {
            // Fetch data from the database
            $data = DB::table('orders')->where('delivery_note_url', '!=', null)->where('status', '2')->pluck('delivery_note_url')->toArray();

            // Generate PDF content
            $pdf = $this->generateMergedPdf($data);

            // Output PDF to the browser
            $pdf->Output('orders.pdf', 'I');
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    private function generateMergedPdf($data)
    {
        // Create instance of TCPDF
        $pdf = new TCPDF();

        // Iterate through each order data and fetch PDFs
        foreach ($data as $order) {
            try {
                // Fetch PDF content using Guzzle
                $client = new Client();
                $response = $client->get($order);
                $pdfContent = $response->getBody()->getContents();

                // Add a new page
                $pdf->AddPage();

                // Set font
                $pdf->SetFont('times', '', 12);

                // Set auto page break
                $pdf->SetAutoPageBreak(true, 10);

                // Output the PDF content to the current page
                $pdf->writeHTML($pdfContent);
            } catch (\Exception $e) {
                echo 'Error: ' . $e->getMessage();
            }
        }

        return $pdf;
    }
}
