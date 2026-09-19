<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records exactly which Vouchers a Ticket Sale Invoice's "Post" action
 * created, so "Unpost" can find and reverse precisely those entries
 * (rather than guessing) — one row per voucher: one for the customer
 * receivable, plus one per distinct airline touched by that invoice's
 * commission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_invoice_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sale_invoice_id')->constrained('ticket_sale_invoices')->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->string('entry_type', 30); // customer_sale, airline_commission
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_invoice_ledger_entries');
    }
};
