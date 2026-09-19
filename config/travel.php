<?php

return [

    // Names of the existing sub_head_of_accounts rows under which new
    // Supplier / Customer ledger accounts are auto-created. Looked up by
    // name (not id) so this keeps working regardless of how a given
    // installation's chart of accounts was seeded.
    'receivable_subhead_name' => env('TRAVEL_RECEIVABLE_SUBHEAD', 'Accounts Receivable'),
    'payable_subhead_name'    => env('TRAVEL_PAYABLE_SUBHEAD', 'Accounts Payable'),

    // Default vendor-complaint auto-flag rule. Can be overridden per
    // supplier via suppliers.complaint_threshold_override (Phase 4).
    'complaint_threshold' => [
        'max_count'   => (int) env('TRAVEL_COMPLAINT_MAX_COUNT', 3),
        'period_days' => (int) env('TRAVEL_COMPLAINT_PERIOD_DAYS', 90),
    ],

    // Sub-head names the Payments screen (Phase 5) offers as the "other
    // side" of a customer receipt / supplier payment voucher. Looked up
    // by name for the same resilience reason as the receivable/payable
    // sub-heads above.
    'cash_bank_subhead_names' => ['Cash', 'Bank'],

    // ── Ticket Sale Invoice (client feedback rebuild) ──────────────────

    // The agency's own name, shown on the Void action as the label for
    // the deduction the company itself retains (as opposed to the
    // deduction the supplier/airline charges).
    'company_name' => env('TRAVEL_COMPANY_NAME', 'AERO Adventure'),

    // Airlines get their own COA ledger (separate from Suppliers) because
    // commission is receivable from the airline directly. Looked up/created
    // the same resilient way as the supplier/customer sub-heads above.
    'airline_receivable_subhead_name' => env('TRAVEL_AIRLINE_RECEIVABLE_SUBHEAD', 'Accounts Receivable'),

    // System (auto-provisioned) ledger accounts a posted Ticket Sale
    // Invoice hits, besides the customer's and airline's own accounts.
    // Looked up by name and created on first use under the named
    // sub-head if missing — see TicketInvoicePostingService.
    'ticket_sales_income_subhead_name' => env('TRAVEL_TICKET_SALES_SUBHEAD', 'Sales'),
    'ticket_sales_income_account_name' => 'Ticket Sales',
    'airline_commission_income_subhead_name' => env('TRAVEL_COMMISSION_INCOME_SUBHEAD', 'Other Income'),
    'airline_commission_income_account_name' => 'Airline Commission Income',

    // 13-digit ticket number format: 3 (airline code) - 4 - 3 - 3.
    'ticket_no_segments' => [3, 4, 3, 3],
];
