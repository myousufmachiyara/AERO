<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One header table for both "Sale Invoice" (tickets only) and
        // "Tour Invoice" (tickets + hotel/transport/visa/other services) —
        // distinguished by invoice_type. They share numbering, customer
        // linking, quotation linking and totals, and both read/write the
        // same service_lines / travel_passengers tables from Phase 2, so
        // there is nothing to duplicate between the two screens.
        Schema::create('travel_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->enum('invoice_type', ['sale', 'tour']);
            $table->date('invoice_date');
            $table->string('visit_type')->nullable();
            $table->enum('payment_mode', ['cash', 'credit'])->default('credit');
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->string('name_on_invoice')->nullable();
            $table->string('cost_center')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_receivable', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2)->default(0);
            $table->decimal('total_income', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
            $table->foreign('staff_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_invoices');
    }
};
