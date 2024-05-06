<?php

// InvoiceMail.php

namespace App\Mail;

use App\Exports\PacksheetExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class BulksaleInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
        // $this->order = $data['order'];
    }

    public function build()
    {

        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('dejavusans', '', 12);

        // Additional content from your view
        $html = view('export.bulksale_invoice', $this->data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // Get the TCPDF output as a string
        $pdfOutput = $pdf->getPdfData();
        // $pdf = PDF::loadView('export.invoice', $this->data);


        $pdf2 = new TCPDF();
        $pdf2->SetCreator(PDF_CREATOR);
        // $pdf2->SetTitle('Invoice');
        // $pdf2->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf2->AddPage();

        // Set font
        $pdf2->SetFont('dejavusans', '', 12);

        // Additional content from your view
        $html = view('export.bulksale_packlist', $this->data)->render();
        $pdf2->writeHTML($html, true, false, true, false, '');

        // Get the TCPDF output as a string
        $pdf2Output = $pdf2->getPdfData();

        $excelFile = Excel::download(new PacksheetExport, 'packsheet.xlsx');


        return $this->view('email.invoice')
            ->attachData($pdfOutput, 'invoice.pdf', [
                'mime' => 'application/pdf',
            ])
            ->attachData($pdf2Output, 'packlist.pdf', [
                'mime' => 'application/pdf',
            ])
            ->attach($excelFile->getFile(), [
                'as' => 'packsheet.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        // return $this->view('export.invoice', $this->data)
        //     ->subject('Invoice for Order #' . $this->data['order']->reference_id)
        //     ->from('wethesd@mailtrap.com', 'Your Name');
    }
}
