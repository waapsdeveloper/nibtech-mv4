<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TCPDF;

class PartialRefundInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Partial Refund Invoice');
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);

        $html = view('export.partial_refund_invoice', $this->data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdfOutput = $pdf->Output('partial-refund-invoice.pdf', 'S');

        return $this->view('email.partial_refund_invoice')
            ->attachData($pdfOutput, 'partial-refund-invoice.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
