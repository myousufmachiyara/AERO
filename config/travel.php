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
];
