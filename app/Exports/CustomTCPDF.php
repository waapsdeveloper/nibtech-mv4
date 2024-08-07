<?php

namespace App\Exports;

use TCPDF;

class CustomTCPDF extends TCPDF
{
    // Page header
    public function Header()
    {
        // Set font
        $this->SetFont('helvetica', 'B', 12);
        // Title
        $this->Cell(0, 10, 'Order List', 0, 1, 'C', false, '', 0, false, 'T', 'B');

        // Column headings
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(8, 6, 'No', 1);
        $this->Cell(20, 6, 'Order', 1);
        $this->Cell(25, 6, 'SKU', 1);
        $this->Cell(7, 6, 'Qty', 1);
        $this->Cell(110, 6, 'Product Name', 1);
        $this->Cell(0, 6, 'Grade', 1);
        $this->Ln();
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-10);
        // Set font
        $this->SetFont('helvetica', 'I', 9);

        // Page number
        $pageNumber = 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages();

        // Current date
        $currentDate = 'Date: ' . date('d-m-Y');

        // Page number on the left
        $this->Cell(0, 10, $pageNumber, 0, 0, 'L', false, '', 0, false, 'T', 'T');

        // Date on the right
        $this->Cell(0, 10, $currentDate, 0, 0, 'R', false, '', 0, false, 'T', 'T');
    }
}
