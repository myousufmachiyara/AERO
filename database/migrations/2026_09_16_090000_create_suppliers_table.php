<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->string('name');
            // FIX (2026-09-16): keep supplier categories aligned with the service
            // tabs (ticket/hotel/transport/visa/other) so vendor reports can
            // group suppliers the same way invoice lines do.
            $table->enum('type', ['airline', 'hotel', 'visa_agency', 'transport', 'other'])->default('other');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('license_no')->nullable();
            $table->string('ntn')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->unsignedInteger('credit_days')->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->string('flagged_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
