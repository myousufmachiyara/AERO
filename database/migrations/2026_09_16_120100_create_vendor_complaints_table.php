<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->enum('service_type', ['ticket', 'hotel', 'transport', 'visa', 'other']);
            $table->unsignedBigInteger('customer_id')->nullable();
            // Free-text pointer to the booking (invoice/ticket/PNR number) rather
            // than a hard FK to a service_line — a complaint can be raised
            // before any invoice line exists yet, or reference a booking made
            // outside the system, so it must not require one.
            $table->string('reference')->nullable();
            $table->date('complaint_date');
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_complaints');
    }
};
