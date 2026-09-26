<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client fix: WHT also moves from a manual amount to a percentage — of
 * the airline commission (commission_amount), not the fare — since WHT
 * is withholding tax on the commission income, not on the ticket sale
 * itself. wht_amount = commission_amount * wht_percent / 100.
 *
 * Doesn't touch existing rows — historical invoices keep whatever
 * wht_amount they were saved with; wht_percent defaults to 0 for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->decimal('wht_percent', 8, 2)->default(0)->after('wht_amount');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn('wht_percent');
        });
    }
};
