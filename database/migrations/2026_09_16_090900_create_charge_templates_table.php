<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dated rate card per service category (e.g. "Catalyst Visa
        // 22/07/2026") — selecting one on an invoice/quotation line
        // pre-fills its default exchange rate and its charge_template_items
        // (WHT %, COM %, PSF %, city/airline tax, ...) instead of staff
        // re-typing them on every line.
        Schema::create('charge_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('service_category', ['ticket', 'hotel', 'transport', 'visa', 'other'])->default('other');
            $table->date('effective_date');
            $table->string('default_currency', 3)->default('PKR');
            $table->decimal('default_exchange_rate', 12, 4)->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('charge_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('charge_template_id');
            $table->unsignedBigInteger('charge_type_id');
            $table->decimal('value', 10, 4)->default(0); // overrides charge_types.default_value for this template
            $table->timestamps();

            $table->foreign('charge_template_id')->references('id')->on('charge_templates')->cascadeOnDelete();
            $table->foreign('charge_type_id')->references('id')->on('charge_types')->cascadeOnDelete();
            $table->unique(['charge_template_id', 'charge_type_id'], 'template_charge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_template_items');
        Schema::dropIfExists('charge_templates');
    }
};
