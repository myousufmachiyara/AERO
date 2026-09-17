<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['umrah', 'hajj', 'tour', 'custom'])->default('custom');
            $table->unsignedInteger('duration_days')->nullable();
            $table->decimal('base_price', 15, 2)->default(0);
            $table->string('currency', 3)->default('PKR');
            $table->text('inclusions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
