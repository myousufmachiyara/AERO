<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated Airlines master (client feedback on Ticket Sale Invoice):
 * previously an "airline" was just a Supplier with type=airline, but the
 * client wants a real master of airlines with a numeric code — the first
 * 3 digits of every 13-digit ticket number identify the airline, and the
 * commission we earn on a ticket is receivable from the airline itself
 * (its own ledger account), not from whichever supplier sold the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('name');
            $table->string('numeric_code', 3)->unique();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
