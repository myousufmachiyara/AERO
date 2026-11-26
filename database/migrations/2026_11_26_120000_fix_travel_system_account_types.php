<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Income and cost accounts that ticket/tour posting creates were saved with
 * account_type 'income' / 'expense', which Profit & Loss does not read.
 * Re-type them as 'revenue' / 'cogs'. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $costNames = [config('travel.ticket_cost_expense_account_name', 'Ticket Cost'),
            'Hotel Cost', 'Transport Cost', 'Visa Cost', 'Other Services Cost'];

        DB::table('chart_of_accounts')->whereIn('account_type', ['income', 'expense'])->whereIn('name', $costNames)
            ->update(['account_type' => 'cogs']);
        DB::table('chart_of_accounts')->where('account_type', 'income')->update(['account_type' => 'revenue']);
        DB::table('chart_of_accounts')->where('account_type', 'expense')->update(['account_type' => 'cogs']);
    }

    public function down(): void
    {
        // no-op: the old types were never read by the reports
    }
};
