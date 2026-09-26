<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client fix: PSF and Discount both become "enter either side" fields —
 * type the percent and the amount is calculated, or type the amount and
 * the percent is calculated, whichever the user actually has in hand.
 *
 * PSF already had psf_percent/psf_amount; this only adds the mode flag
 * that says which one is the authoritative, user-typed value this time.
 * Discount previously had only a flat discount_amount, so this adds the
 * matching discount_percent column alongside its own mode flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->string('psf_input_mode', 10)->default('percent')->after('psf_basis');
            $table->decimal('discount_percent', 8, 2)->default(0)->after('discount_amount');
            $table->string('discount_input_mode', 10)->default('percent')->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['psf_input_mode', 'discount_percent', 'discount_input_mode']);
        });
    }
};
