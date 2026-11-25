<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice → Hotel tab → Room Details (client fix, Nov 2026):
 *   Single Room Total = Rate × Nights,   Rooms Total = Single Room Total × No. of Rooms
 *   Single Room Cost  = Cost × Nights,   Rooms Cost  = Single Room Cost  × No. of Rooms
 * Σ Rooms Total → Receivable, Σ Rooms Cost → Payable.
 *
 * `rate` (to customer) and `total_amount` (now Rooms Total) already exist;
 * this adds the hotel-side `cost` and `total_cost`. Guarded so a partly
 * applied run can be re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoice_hotel_rooms', 'cost')) {
            Schema::table('invoice_hotel_rooms', fn (Blueprint $t) => $t->decimal('cost', 15, 2)->default(0)->after('rate'));
        }
        if (!Schema::hasColumn('invoice_hotel_rooms', 'total_cost')) {
            Schema::table('invoice_hotel_rooms', fn (Blueprint $t) => $t->decimal('total_cost', 15, 2)->default(0)->after('total_amount'));
        }
    }

    public function down(): void
    {
        Schema::table('invoice_hotel_rooms', fn (Blueprint $t) => $t->dropColumn(['cost', 'total_cost']));
    }
};
