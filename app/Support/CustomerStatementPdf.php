<?php

namespace App\Support;

use TCPDF;

/**
 * TCPDF page frame for the Customer Account Statement, matching the
 * client's sample: page 1 opens with the logo + company block + title +
 * From/To; every page carries the customer block with the print time; the
 * footer shows "Page x of y". The body is HTML from
 * resources/views/reports/customer_statement_pdf.blade.php.
 */
class CustomerStatementPdf extends TCPDF
{
    /** @var array Output of CustomerStatementService::build() */
    public array $statement = [];

    /** Page-1 title; the Vendor Statement sets "Vendor Account Statement". */
    public string $docTitle = 'Customer Account Statement';

    public function Header(): void
    {
        if ($this->getPage() > 1) {
            $this->SetY(6);
            $this->customerBlock();
        }
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('helvetica', '', 7.5);
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    /** Logo + company + "Customer Account Statement" + From/To + customer block (page 1 only). */
    public function renderCompanyHeader(): void
    {
        $w = $this->getPageWidth() - 16;

        $logo = $this->logoPath();
        if ($logo) {
            $this->Image($logo, 10, 7, 30);
        }

        $this->SetY(8);
        $this->SetFont('helvetica', '', 13);
        $this->Cell(0, 6, config('travel.company_name'), 0, 1, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->MultiCell($w - 70, 4, config('travel.company_address'), 0, 'C', false, 1, 43);
        $this->Ln(3);
        $this->Cell(0, 4, 'Phone: ' . config('travel.company_phone'), 0, 1, 'C');
        $this->Ln(1);
        $this->Cell(0, 4, config('travel.company_license_line'), 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('helvetica', '', 14);
        $this->Cell(0, 8, $this->docTitle, 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('helvetica', '', 8);
        $from = \Illuminate\Support\Carbon::parse($this->statement['from'])->format('d/m/Y');
        $to = \Illuminate\Support\Carbon::parse($this->statement['to'])->format('d/m/Y');
        $this->Cell(0, 4, "From: {$from} To: {$to}", 0, 1, 'R');
        $this->Ln(2);

        $this->customerBlock();
        $this->Ln(1);
    }

    /** Customer name / phone / address / NTN on the left, print time on the right. */
    protected function customerBlock(): void
    {
        $c = $this->statement['party'] ?? $this->statement['customer'];
        $y = $this->GetY();

        $this->SetFont('helvetica', '', 7.5);
        $this->SetXY(8, $y);
        $this->Cell(120, 3.6, (string) $c->name, 0, 2);
        $this->Cell(120, 3.6, 'Phone: ' . ($c->phone ?? ''), 0, 2);
        $this->Cell(120, 3.6, 'Address: ' . ($c->address ?? ''), 0, 2);
        $this->Cell(120, 3.6, 'Sale Tax Number: ' . ($c->ntn ?? ''), 0, 2);
        $afterY = $this->GetY();

        $this->SetXY(140, $y + 3);
        $this->Cell(12, 3.6, 'Print', 0, 0);
        $this->Cell(0, 3.6, $this->statement['printed_at']->format('d, M Y H:i'), 0, 0, 'R');

        $this->SetY($afterY + 1);
    }

    protected function logoPath(): ?string
    {
        $configured = public_path(config('travel.company_logo_path', 'assets/img/aero-logo.png'));
        foreach ([$configured, public_path('assets/img/aero-logo.jpeg'), public_path('assets/img/aero-logo.jpg')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
