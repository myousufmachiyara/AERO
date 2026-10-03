<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_transport_detail_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('sector')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_transport_detail_id', 'itv_transport_detail_fk')
                ->references('id')->on('invoice_transport_details')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transport_vehicles');
    }
};
