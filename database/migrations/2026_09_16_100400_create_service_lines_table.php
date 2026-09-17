<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one shared Receivable/Payable/Income line shape used by every
        // Ticket/Hotel/Transport/Visa/Other-Service row — reused by
        // Quotations now and by Sale/Tour Invoices in Phase 3 via the
        // polymorphic linkable_type/linkable_id, so "convert quotation to
        // invoice" is a row copy rather than re-entry.
        //
        // At the Quotation stage staff capture the service type, supplier,
        // pricing and a free-text description ("3 nights, Anjum Hotel,
        // Quad room"). The structured per-type detail tables (PNR, flight
        // legs, check-in/out dates, visa dates, ...) are added in Phase 3
        // against the finalised Sale/Tour Invoice, once there is a real
        // booking to record rather than an estimate.
        Schema::create('service_lines', function (Blueprint $table) {
            $table->id();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->enum('service_type', ['ticket', 'hotel', 'transport', 'visa', 'other']);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('charge_template_id')->nullable();
            $table->string('description')->nullable();
            $table->string('currency', 3)->default('PKR');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('receivable_f_amount', 15, 2)->default(0);
            $table->decimal('receivable_l_amount', 15, 2)->default(0);
            $table->decimal('payable_f_amount', 15, 2)->default(0);
            $table->decimal('payable_l_amount', 15, 2)->default(0);
            $table->decimal('income_l_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id'], 'linkable_index');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('charge_template_id')->references('id')->on('charge_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_lines');
    }
};
