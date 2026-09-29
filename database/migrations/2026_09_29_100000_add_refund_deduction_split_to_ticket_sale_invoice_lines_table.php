<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client fix: Refund now splits into the same two components Void
 * already has — the deduction kept by the airline/supplier, and the
 * deduction kept by the company itself — instead of one lump
 * "refund_charges" figure. refund_charges is kept (now computed as the
 * sum of the two new columns) so refund_amount/refund_profit's existing
 * formulas don't need to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->decimal('refund_deduction_supplier', 14, 2)->nullable()->after('refund_charges');
            $table->decimal('refund_deduction_company', 14, 2)->nullable()->after('refund_deduction_supplier');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['refund_deduction_supplier', 'refund_deduction_company']);
        });
    }
};
