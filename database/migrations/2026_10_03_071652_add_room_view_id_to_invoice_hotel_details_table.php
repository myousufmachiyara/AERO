<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice fix (client feedback, frontend-approval round): Room View
 * is now its own field on a Hotel invoice line, picked independently of
 * the chosen Room — not read off HotelRoom.room_view_id, which describes
 * the room's usual view, not necessarily what was actually booked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_hotel_details', function (Blueprint $table) {
            $table->foreignId('room_view_id')->nullable()->after('hotel_room_id')->constrained('room_views')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_hotel_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_view_id');
        });
    }
};