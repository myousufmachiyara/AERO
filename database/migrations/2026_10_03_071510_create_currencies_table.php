<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice fix (client feedback, frontend-approval round): the
 * Currency field on a service line was free text with nothing backing it
 * anywhere in the app. Small admin-maintained master, same shape as
 * RoomView/VisaType — code is what service_lines.currency already stores
 * (a 3-char string), name is just the display label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};