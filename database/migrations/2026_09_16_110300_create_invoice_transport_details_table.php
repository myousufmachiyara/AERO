<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_transport_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id')->unique();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('sector')->nullable();
            $table->string('booking_name')->nullable();
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transport_details');
    }
};
