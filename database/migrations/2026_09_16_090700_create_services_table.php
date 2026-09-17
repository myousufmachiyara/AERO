<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog of sellable services beyond the four hard-coded tabs
        // (Ticket/Hotel/Transport/Visa) — feeds the "Other Services" line
        // on Sale/Tour invoices and quotations (e.g. Ziyarat, SIM card,
        // travel insurance, porterage).
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['ticket', 'hotel', 'transport', 'visa', 'other'])->default('other');
            $table->unsignedBigInteger('default_supplier_id')->nullable();
            $table->string('default_unit')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('default_supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
