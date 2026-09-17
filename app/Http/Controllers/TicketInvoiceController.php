<?php

namespace App\Http\Controllers;

/**
 * "Sale Invoice" in the sidebar — tickets only. Kept as `ticket_invoices`
 * internally to avoid colliding with the base app's inventory-focused
 * `sale_invoices` route/controller.
 */
class TicketInvoiceController extends BaseTravelInvoiceController
{
    protected function invoiceType(): string
    {
        return 'sale';
    }

    protected function allowedServiceTypes(): array
    {
        return ['ticket'];
    }

    protected function routeUri(): string
    {
        return 'ticket_invoices';
    }

    protected function viewPrefix(): string
    {
        return 'ticket-invoices';
    }
}
