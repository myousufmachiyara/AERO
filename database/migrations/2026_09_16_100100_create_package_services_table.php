<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a package bundles by default — used to pre-fill a
        // Quotation's service lines when staff pick "Apply Package".
        // Only one of hotel_room_id/vehicle_id/visa_type_id/service_id is
        // set, matching service_type.
        Schema::create('package_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->enum('service_type', ['ticket', 'hotel', 'transport', 'visa', 'other']);
            $table->unsignedBigInteger('hotel_room_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('visa_type_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('hotel_room_id')->references('id')->on('hotel_rooms')->nullOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('visa_type_id')->references('id')->on('visa_types')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_services');
    }
};
