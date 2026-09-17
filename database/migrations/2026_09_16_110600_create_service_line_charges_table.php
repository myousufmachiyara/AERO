<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Open list of extra charges/deductions per service line — SPO1-6,
        // WHT, COM, PSF, taxes, etc, from the reference "Invoice Summary"
        // tab. Each row is one charge_type applied to one service_line,
        // stored either as a flat value or a percentage (charge_types.mode
        // decides which, per Phase 1), with the resolved amount cached here
        // so totals never need to re-walk charge_types at read time.
        Schema::create('service_line_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_line_id');
            $table->unsignedBigInteger('charge_type_id');
            $table->decimal('value', 15, 4)->default(0);
            $table->decimal('computed_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('service_line_id')->references('id')->on('service_lines')->cascadeOnDelete();
            $table->foreign('charge_type_id')->references('id')->on('charge_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_line_charges');
    }
};
