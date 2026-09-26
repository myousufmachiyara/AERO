<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client fix: APT charges, PSF, and agent commission all move from
 * plain manual-entry amounts to percentage-driven fields, computed by
 * TicketSaleInvoiceLine::recalculate():
 *   apt_charges       = fare_amount * apt_percent / 100
 *   psf_amount        = (psf_basis === 'total' ? fare+tax+apt : fare_amount) * psf_percent / 100
 *   agent_commission_amount = total_amount * agent_commission_percent / 100
 *
 * The existing apt_charges/psf_amount/agent_commission_amount columns are
 * kept as-is (they still hold the final computed dollar amount, used
 * everywhere downstream — reports, ledger postings, prints). This
 * migration only adds the new percent/basis inputs; it doesn't touch or
 * recompute any existing rows, so historical invoices keep whatever
 * amounts they were saved with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->decimal('apt_percent', 8, 2)->default(0)->after('apt_charges');
            $table->decimal('psf_percent', 8, 2)->default(0)->after('psf_amount');
            $table->string('psf_basis', 10)->default('fare')->after('psf_percent');
            $table->decimal('agent_commission_percent', 8, 2)->default(0)->after('agent_commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['apt_percent', 'psf_percent', 'psf_basis', 'agent_commission_percent']);
        });
    }
};
