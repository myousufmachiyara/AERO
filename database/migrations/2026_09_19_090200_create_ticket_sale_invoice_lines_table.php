<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per ticket — a single Ticket Sale Invoice can carry several
 * (client feedback: "single ticket invoice should handle multiple
 * tickets"). Field order below follows the exact sequence the client
 * gave, minus what they asked removed (currency/exchange rate/receivable/
 * payable as such — the customer/airline ledger postings are computed
 * from these amounts instead, see TicketInvoicePostingService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sale_invoice_id')->constrained('ticket_sale_invoices')->cascadeOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();

            $table->string('pax_name');
            $table->string('pax_type', 20)->default('adult'); // adult, child, infant
            $table->string('pnr', 50)->nullable();
            // 13 digits stored dashed as NNN-NNNN-NNN-NNN; first 3 = airline code.
            $table->string('ticket_no', 20)->nullable()->index();

            // Cities: From-Stay-To for one way, From-Stay-To/From-Stay-To for return.
            $table->string('trip_type', 20)->default('one_way'); // one_way, return
            $table->string('leg1_from', 100)->nullable();
            $table->string('leg1_stay', 100)->nullable();
            $table->string('leg1_to', 100)->nullable();
            $table->string('leg2_from', 100)->nullable();
            $table->string('leg2_stay', 100)->nullable();
            $table->string('leg2_to', 100)->nullable();

            $table->decimal('fare_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('apt_charges', 14, 2)->default(0);
            $table->decimal('commission_percent', 6, 2)->default(0); // % of fare_amount
            $table->decimal('commission_amount', 14, 2)->default(0); // computed, receivable from airline
            $table->decimal('wht_amount', 14, 2)->default(0);
            $table->decimal('psf_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            // fare + tax + apt + psf - discount = receivable from customer for this ticket.
            $table->decimal('total_amount', 14, 2)->default(0);

            // Sales-agent-wise commission — an employee (User), selected from
            // a dropdown, distinct from the airline commission above: this is
            // what the agency pays its own staff for making the sale, and is
            // what "My Commission" reports back to that agent.
            $table->foreignId('sales_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('agent_commission_amount', 14, 2)->default(0);

            // Lifecycle: active -> refunded OR active -> voided (terminal).
            $table->string('status', 20)->default('active');

            // Refund — allowed any time while the invoice is pending.
            $table->date('refund_date')->nullable();
            $table->date('refund_adjustment_date')->nullable();
            $table->decimal('refund_fare_amount', 14, 2)->nullable();
            $table->decimal('refund_tax_amount', 14, 2)->nullable();
            $table->decimal('refund_charges', 14, 2)->nullable();
            // refund_amount = refund_fare_amount + refund_tax_amount - refund_charges (returned to customer)
            $table->decimal('refund_amount', 14, 2)->nullable();
            // refund_profit = original total_amount - refund_amount (what the agency keeps)
            $table->decimal('refund_profit', 14, 2)->nullable();

            // Void — only within the same day the invoice was issued.
            $table->date('void_date')->nullable();
            $table->decimal('void_deduction_supplier', 14, 2)->nullable();
            $table->decimal('void_deduction_company', 14, 2)->nullable();
            $table->decimal('void_total_deduction', 14, 2)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sale_invoice_lines');
    }
};
