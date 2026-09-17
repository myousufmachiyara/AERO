<?php

namespace App\Http\Controllers;

/**
 * "Tour Invoice" — tickets + hotel/transport/visa/other services, all in
 * one invoice, matching the multi-tab reference screens.
 */
class TourInvoiceController extends BaseTravelInvoiceController
{
    protected function invoiceType(): string
    {
        return 'tour';
    }

    protected function allowedServiceTypes(): array
    {
        return ['ticket', 'hotel', 'transport', 'visa', 'other'];
    }

    protected function routeUri(): string
    {
        return 'tour_invoices';
    }

    protected function viewPrefix(): string
    {
        return 'tour-invoices';
    }
}
