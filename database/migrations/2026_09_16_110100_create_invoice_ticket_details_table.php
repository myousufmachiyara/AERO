<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_ticket_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id')->unique();
            $table->string('pnr')->nullable();
            $table->string('gds')->nullable();
            $table->string('airline')->nullable();
            $table->string('ticket_no')->nullable();
            $table->enum('ticket_type', ['domestic', 'international'])->default('international');
            $table->string('sector')->nullable();
            $table->string('tour_code')->nullable();
            $table->date('issue_date')->nullable();
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
        });

        Schema::create('invoice_ticket_flights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_ticket_detail_id');
            $table->string('city')->nullable();
            $table->string('flight_no')->nullable();
            $table->date('dep_date')->nullable();
            $table->string('dep_time', 10)->nullable();
            $table->string('arr_time', 10)->nullable();
            $table->string('fare_basis')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_ticket_detail_id', 'itf_ticket_detail_fk')
                ->references('id')->on('invoice_ticket_details')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_ticket_flights');
        Schema::dropIfExists('invoice_ticket_details');
    }
};
