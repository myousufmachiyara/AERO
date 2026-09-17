<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared passenger list — polymorphic so the exact same table
        // serves Quotations now and Sale/Tour Invoices in Phase 3, instead
        // of three near-identical passenger tables.
        Schema::create('travel_passengers', function (Blueprint $table) {
            $table->id();
            $table->string('passengerable_type');
            $table->unsignedBigInteger('passengerable_id');
            $table->string('name');
            $table->string('passport_no_nic')->nullable();
            $table->enum('pax_type', ['adult', 'child', 'infant'])->default('adult');
            $table->string('nationality')->nullable();
            $table->date('dob')->nullable();
            $table->timestamps();

            $table->index(['passengerable_type', 'passengerable_id'], 'passengerable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_passengers');
    }
};
