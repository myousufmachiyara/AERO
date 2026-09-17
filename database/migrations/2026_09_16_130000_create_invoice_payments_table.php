<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A travel-scoped record of who was paid/received-from and
        // (optionally) which Sale/Tour Invoice it applies to. It never
        // duplicates the accounting engine's own ledger: every payment
        // here also posts a real double-entry Voucher (voucher_id),
        // so trial balance / party statements keep working unchanged.
        // This table exists purely so a Sale/Tour Invoice can show "how
        // much of this invoice has been collected" without walking the
        // whole general ledger.
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->unique();
            $table->enum('direction', ['receipt', 'payment']);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('travel_invoice_id')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_mode', ['cash', 'bank', 'cheque', 'online'])->default('bank');
            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('travel_invoice_id')->references('id')->on('travel_invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
