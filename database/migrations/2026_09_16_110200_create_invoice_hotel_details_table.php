<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_hotel_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id')->unique();
            $table->unsignedBigInteger('hotel_id')->nullable();
            $table->unsignedBigInteger('hotel_room_id')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedInteger('nights')->default(0);
            $table->unsignedInteger('room_qty')->default(1);
            $table->unsignedInteger('extra_bed_qty')->default(0);
            $table->string('booking_name')->nullable();
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->nullOnDelete();
            $table->foreign('hotel_room_id')->references('id')->on('hotel_rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_hotel_details');
    }
};
