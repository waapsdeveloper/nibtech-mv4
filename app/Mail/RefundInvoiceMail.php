<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TCPDF;

class RefundInvoiceMail extends Mailable implements ShouldQueue
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
        $pdf->SetTitle('Refund Invoice');
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);

        $html = view('export.refund_invoice', $this->data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdfOutput = $pdf->Output('refund-invoice.pdf', 'S');

        return $this->view('email.refund_invoice')
            ->attachData($pdfOutput, 'refund-invoice.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
