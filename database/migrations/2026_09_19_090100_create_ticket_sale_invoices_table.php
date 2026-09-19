<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Sale Invoice — rebuilt per client feedback as its own dedicated
 * module, decoupled from the generic travel_invoices table (which still
 * serves Tour Invoice unchanged). Deliberately has NO currency/exchange
 * rate/visit_type/name_on_invoice/cost_center/staff columns — the client
 * asked for those to be removed. "created_by" is the only staff record
 * kept, and it is always the logged-in user, never a picklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sale_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->date('invoice_date');
            // Ledger-posting date, independent of the document date above —
            // defaults to invoice_date but can be moved (e.g. to align a
            // late-entered invoice with the correct accounting period).
            $table->date('adjustment_date')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            // pending = just a document, nothing posted to the ledgers yet.
            // posted = hit the customer/airline ledgers; locked against
            // edit/refund/void until unposted again.
            $table->string('status', 20)->default('pending');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();

            // Rollups over the lines, for fast listing/printing — recomputed
            // from the lines on every save, never hand-edited.
            $table->decimal('total_fare', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_apt', 14, 2)->default(0);
            $table->decimal('total_commission', 14, 2)->default(0);
            $table->decimal('total_wht', 14, 2)->default(0);
            $table->decimal('total_psf', 14, 2)->default(0);
            $table->decimal('total_discount', 14, 2)->default(0);
            // What the customer is actually charged, after any refund/void
            // adjustments on individual tickets — this is what hits the
            // customer's ledger when posted.
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sale_invoices');
    }
};
