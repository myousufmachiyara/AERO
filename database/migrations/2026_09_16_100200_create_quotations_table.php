<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no', 20)->unique();
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('visit_type')->nullable();
            $table->enum('status', ['draft', 'sent', 'approved', 'rejected', 'expired', 'converted'])->default('draft');
            // Set once this quotation is turned into an invoice (Phase 3).
            // Not a foreign key since it can point at either sale_invoices
            // or tour_invoices depending on converted_invoice_type.
            $table->enum('converted_invoice_type', ['sale', 'tour'])->nullable();
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_receivable', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2)->default(0);
            $table->decimal('total_income', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
