<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Open, admin-maintained list of named charges/taxes that can appear
        // on an invoice service line (WHT, COM, PSF, City Tax, Airline Tax,
        // or any future government levy) — replaces a fixed set of SPO1-6
        // columns so a new charge type is a master entry, not a migration.
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('calculation_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('default_value', 10, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_types');
    }
};
