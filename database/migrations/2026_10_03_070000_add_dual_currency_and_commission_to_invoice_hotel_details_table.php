<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice fix (client feedback, Hotel tab round 2):
 *
 * - The single shared `exchange_rate` on service_lines was being used for
 *   both the receivable and the payable side, but the client wants two
 *   independent currencies/rates — one for what the vendor is actually
 *   billed in, one for what the customer is actually charged in — so both
 *   can be shown and converted to local currency separately. Those two
 *   pairs live here (hotel-specific detail), the shared `currency` column
 *   on service_lines stays as the line's "main"/invoice currency.
 * - Agent Commission is a new per-line field: staff pick a % here, we
 *   compute and store the amount, but it's explicitly NOT netted out of
 *   PSF (receivable - payable) — it's tracked separately per the client's
 *   instruction ("that commission will not be minus from PSF just
 *   calculate commission and save it").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_hotel_details', function (Blueprint $table) {
            $table->string('receivable_currency', 3)->nullable()->default('PKR')->after('booking_name');
            $table->decimal('receivable_exchange_rate', 12, 4)->nullable()->default(1)->after('receivable_currency');
            $table->string('payable_currency', 3)->nullable()->default('PKR')->after('receivable_exchange_rate');
            $table->decimal('payable_exchange_rate', 12, 4)->nullable()->default(1)->after('payable_currency');
            $table->decimal('agent_commission_percent', 6, 3)->nullable()->default(0)->after('payable_exchange_rate');
            $table->decimal('agent_commission_amount', 15, 2)->nullable()->default(0)->after('agent_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_hotel_details', function (Blueprint $table) {
            $table->dropColumn([
                'receivable_currency', 'receivable_exchange_rate',
                'payable_currency', 'payable_exchange_rate',
                'agent_commission_percent', 'agent_commission_amount',
            ]);
        });
    }
};
