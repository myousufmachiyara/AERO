<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice fix (client feedback, Hotel tab round 2): "Room Details"
 * becomes a repeatable grid (room type / room view / no. of room / rate /
 * total amount) instead of one fixed Room Type + Room View + No. of Room
 * per hotel booking — a single hotel booking can cover more than one room
 * type (e.g. 2 Deluxe + 1 Suite), same pattern as service_line_charges.
 *
 * invoice_hotel_details.hotel_room_id / room_view_id / room_qty are left
 * in place (not dropped) for backward compatibility with existing data
 * and the ServiceLineWriter API — the Hotel tab UI no longer writes them
 * directly, it writes one row per room here instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_hotel_detail_id');
            $table->unsignedBigInteger('hotel_room_id')->nullable();
            $table->unsignedBigInteger('room_view_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_hotel_detail_id', 'ihr_hotel_detail_fk')
                ->references('id')->on('invoice_hotel_details')->cascadeOnDelete();
            $table->foreign('hotel_room_id')->references('id')->on('hotel_rooms')->nullOnDelete();
            $table->foreign('room_view_id')->references('id')->on('room_views')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_hotel_rooms');
    }
};
