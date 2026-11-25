<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice → Other Services tab: Booking Name (one passenger from the
 * General Information passengers list, same as Hotel/Transport) and Travel
 * Date. Remarks are saved in the service line's description. Guarded so a
 * partly-applied run can be re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoice_other_details', 'booking_name')) {
            Schema::table('invoice_other_details', fn (Blueprint $t) => $t->string('booking_name')->nullable()->after('qty'));
        }
        if (!Schema::hasColumn('invoice_other_details', 'travel_date')) {
            Schema::table('invoice_other_details', fn (Blueprint $t) => $t->date('travel_date')->nullable()->after('booking_name'));
        }
    }

    public function down(): void
    {
        Schema::table('invoice_other_details', fn (Blueprint $t) => $t->dropColumn(['booking_name', 'travel_date']));
    }
};
