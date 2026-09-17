<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_other_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id')->unique();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_other_details');
    }
};
