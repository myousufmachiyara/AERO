<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_transport_details', function (Blueprint $table) {
            // Reference No / Category: the Transport tab rework asked for
            // these as real "Transport Details" fields (unlike Hotel's
            // Category, which stayed an informational, not-saved dropdown)
            // — so, unlike that Hotel precedent, these are persisted here.
            $table->string('reference_no')->nullable()->after('booking_name');
            $table->string('category')->nullable()->after('reference_no');

            // Same dual receivable/payable currency+rate and agent
            // commission pattern as invoice_hotel_details (see the
            // 2026_10_03_070000 migration) — reused as-is for Transport's
            // "Charges Details" section.
            $table->string('receivable_currency', 3)->nullable()->default('PKR')->after('category');
            $table->decimal('receivable_exchange_rate', 12, 4)->nullable()->default(1)->after('receivable_currency');
            $table->string('payable_currency', 3)->nullable()->default('PKR')->after('receivable_exchange_rate');
            $table->decimal('payable_exchange_rate', 12, 4)->nullable()->default(1)->after('payable_currency');
            $table->decimal('agent_commission_percent', 6, 3)->nullable()->default(0)->after('payable_exchange_rate');
            $table->decimal('agent_commission_amount', 15, 2)->nullable()->default(0)->after('agent_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transport_details', function (Blueprint $table) {
            $table->dropColumn([
                'reference_no', 'category',
                'receivable_currency', 'receivable_exchange_rate',
                'payable_currency', 'payable_exchange_rate',
                'agent_commission_percent', 'agent_commission_amount',
            ]);
        });
    }
};
