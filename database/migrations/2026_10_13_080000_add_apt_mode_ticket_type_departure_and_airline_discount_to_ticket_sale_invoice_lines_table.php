<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Sale Invoice fixes (Oct 2026):
 *  - apt_input_mode: APT is now an "enter % or amount" pair, same as PSF.
 *  - ticket_type: Domestic / International.
 *  - airline_discount_amount: the discount the AIRLINE gives (feeds Supplier
 *    Net in the Summary); the existing discount_* columns stay the discount
 *    the company gives the customer.
 *  - leg1/leg2 departure date + time.
 *  - leg*_stay widened: "connected flights" store every extra stop city in
 *    the existing Stay column joined by "-" (From-Stay1-Stay2-To), so no
 *    extra table is needed and citiesLabel() keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->string('apt_input_mode', 10)->default('percent')->after('apt_percent');
            $table->string('ticket_type', 20)->default('international')->after('trip_type');
            $table->decimal('airline_discount_amount', 15, 2)->default(0)->after('discount_input_mode');
            $table->date('leg1_departure_date')->nullable()->after('leg1_to');
            $table->string('leg1_departure_time', 5)->nullable()->after('leg1_departure_date');
            $table->date('leg2_departure_date')->nullable()->after('leg2_to');
            $table->string('leg2_departure_time', 5)->nullable()->after('leg2_departure_date');
        });

        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->string('leg1_stay', 255)->nullable()->change();
            $table->string('leg2_stay', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn([
                'apt_input_mode', 'ticket_type', 'airline_discount_amount',
                'leg1_departure_date', 'leg1_departure_time', 'leg2_departure_date', 'leg2_departure_time',
            ]);
        });
    }
};
