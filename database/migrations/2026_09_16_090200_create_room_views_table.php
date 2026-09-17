<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master list of hotel room views (Haram View, Sea View, City View, ...).
        // Kept as its own tiny master (rather than a free-text field on
        // hotel_rooms) so it stays consistent across hotels and can be
        // reused when filtering/reporting on room inventory.
        Schema::create('room_views', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_views');
    }
};
