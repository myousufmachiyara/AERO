<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_visa_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id')->unique();
            $table->unsignedBigInteger('visa_type_id')->nullable();
            $table->date('apply_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('reference_no')->nullable();
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
            $table->foreign('visa_type_id')->references('id')->on('visa_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_visa_details');
    }
};
