<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links every voucher a confirmed Tour Invoice posted (see
 * TourInvoicePostingService) so Back to Draft / Cancel can remove exactly
 * those vouchers and nothing else. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_invoice_ledger_entries')) {
            return;
        }

        Schema::create('travel_invoice_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_invoice_id');
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->string('service_type', 20);
            $table->string('entry_type', 40);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('travel_invoice_id', 'tile_travel_invoice_idx');
            $table->index('voucher_id', 'tile_voucher_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_invoice_ledger_entries');
    }
};
