<?php

// InvoiceMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TCPDF;

class InvoiceMail extends Mailable
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
        $pdf->SetTitle('Invoice');
        $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('dejavusans', '', 12);

        // Additional content from your view
        $html = view('export.invoice', $this->data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // Get the TCPDF output as a string
        // $pdfOutput = $pdf->getPdfData();
        $pdfOutput = $pdf->Output('invoice.pdf', 'S'); // Get the PDF output as a string
        // $pdf = PDF::loadView('export.invoice', $this->data);

        return $this->view('email.invoice');
            // ->attachData($pdfOutput, 'invoice.pdf', [
            //     'mime' => 'application/pdf',
            // ]);

        // return $this->view('export.invoice', $this->data)
        //     ->subject('Invoice for Order #' . $this->data['order']->reference_id)
        //     ->from('wethesd@mailtrap.com', 'Your Name');
    }
}
