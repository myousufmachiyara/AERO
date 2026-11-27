<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A receipt (or supplier payment) can now be recorded against a Ticket
 * Sale Invoice as well as a Tour Invoice. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_payments', 'ticket_sale_invoice_id')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_sale_invoice_id')->nullable()->after('travel_invoice_id');
            $table->foreign('ticket_sale_invoice_id', 'ip_tsi_fk')->references('id')->on('ticket_sale_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('invoice_payments', 'ticket_sale_invoice_id')) {
            return;
        }

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign('ip_tsi_fk');
            $table->dropColumn('ticket_sale_invoice_id');
        });
    }
};
