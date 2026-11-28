<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Visa tab: save its Receivable/Payable currency and exchange rate (same as Hotel / Transport). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_visa_details', function (Blueprint $table) {
            $table->string('receivable_currency', 10)->default('PKR')->after('reference_no');
            $table->decimal('receivable_exchange_rate', 15, 4)->default(1)->after('receivable_currency');
            $table->string('payable_currency', 10)->default('PKR')->after('receivable_exchange_rate');
            $table->decimal('payable_exchange_rate', 15, 4)->default(1)->after('payable_currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_visa_details', function (Blueprint $table) {
            $table->dropColumn(['receivable_currency', 'receivable_exchange_rate', 'payable_currency', 'payable_exchange_rate']);
        });
    }
};
