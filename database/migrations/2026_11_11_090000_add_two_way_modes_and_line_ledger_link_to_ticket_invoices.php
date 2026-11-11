<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client fixes (Nov 2026):
 *  - Commission, WHT and Sale (agent) Commission become "enter % OR amount"
 *    pairs like APT/PSF/Discount already are. The *_input_mode flag records
 *    which side was typed so the server keeps that side exact and derives
 *    the other one (no rounding drift from a back-calculated percent).
 *  - Refund/Void on a POSTED invoice now writes its own reversal + charge
 *    vouchers. ticket_sale_invoice_line_id / adjustment link each such
 *    ledger entry to the ticket it belongs to (null for the invoice's
 *    original Post entries).
 *  - airline_discount_amount is no longer used (field removed from the
 *    form); the column is kept so historic data isn't destroyed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->string('commission_input_mode', 10)->default('percent')->after('commission_percent');
            $table->string('wht_input_mode', 10)->default('percent')->after('wht_percent');
            $table->string('agent_commission_input_mode', 10)->default('percent')->after('agent_commission_percent');
        });

        Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
            $table->foreignId('ticket_sale_invoice_line_id')->nullable()->after('ticket_sale_invoice_id')
                ->constrained('ticket_sale_invoice_lines')->nullOnDelete();
            $table->string('adjustment', 10)->nullable()->after('entry_type'); // refund | void
        });
    }

    public function down(): void
    {
        Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_sale_invoice_line_id');
            $table->dropColumn('adjustment');
        });

        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['commission_input_mode', 'wht_input_mode', 'agent_commission_input_mode']);
        });
    }
};
