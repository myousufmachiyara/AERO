<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Invoice → Ticket tab now mirrors the Ticket Sale Invoice exactly:
 * same Master Details (airline, trip type, From-Stay-To cities, departure,
 * Fare/Tax and the two-way %/Amount pairs) and the same per-ticket grid.
 * These columns hold those inputs per ticket; the ServiceLine keeps
 * Receivable = Ticket Total, Payable = Supplier Net, Income = the
 * difference, so the Tour Invoice summary/totals/payments keep working.
 *
 * Every step is guarded so a partly-applied run (MySQL DDL isn't
 * transactional) can simply be re-run. FK names are explicit and short
 * (MySQL's 64-char identifier limit).
 */
return new class extends Migration
{
    private const T = 'invoice_ticket_details';

    public function up(): void
    {
        $columns = [
            'pax_type' => fn (Blueprint $t) => $t->string('pax_type', 10)->default('adult'),
            'airline_id' => fn (Blueprint $t) => $t->unsignedBigInteger('airline_id')->nullable(),
            'trip_type' => fn (Blueprint $t) => $t->string('trip_type', 10)->default('one_way'),
            'leg1_from' => fn (Blueprint $t) => $t->string('leg1_from', 100)->nullable(),
            'leg1_stay' => fn (Blueprint $t) => $t->string('leg1_stay', 255)->nullable(),
            'leg1_to' => fn (Blueprint $t) => $t->string('leg1_to', 100)->nullable(),
            'leg1_departure_date' => fn (Blueprint $t) => $t->date('leg1_departure_date')->nullable(),
            'leg1_departure_time' => fn (Blueprint $t) => $t->string('leg1_departure_time', 8)->nullable(),
            'leg2_from' => fn (Blueprint $t) => $t->string('leg2_from', 100)->nullable(),
            'leg2_stay' => fn (Blueprint $t) => $t->string('leg2_stay', 255)->nullable(),
            'leg2_to' => fn (Blueprint $t) => $t->string('leg2_to', 100)->nullable(),
            'leg2_departure_date' => fn (Blueprint $t) => $t->date('leg2_departure_date')->nullable(),
            'leg2_departure_time' => fn (Blueprint $t) => $t->string('leg2_departure_time', 8)->nullable(),
            'fare_amount' => fn (Blueprint $t) => $t->decimal('fare_amount', 14, 2)->default(0),
            'tax_amount' => fn (Blueprint $t) => $t->decimal('tax_amount', 14, 2)->default(0),
            'apt_percent' => fn (Blueprint $t) => $t->decimal('apt_percent', 8, 2)->default(0),
            'apt_charges' => fn (Blueprint $t) => $t->decimal('apt_charges', 14, 2)->default(0),
            'apt_input_mode' => fn (Blueprint $t) => $t->string('apt_input_mode', 10)->default('percent'),
            'commission_percent' => fn (Blueprint $t) => $t->decimal('commission_percent', 8, 2)->default(0),
            'commission_amount' => fn (Blueprint $t) => $t->decimal('commission_amount', 14, 2)->default(0),
            'commission_input_mode' => fn (Blueprint $t) => $t->string('commission_input_mode', 10)->default('percent'),
            'wht_percent' => fn (Blueprint $t) => $t->decimal('wht_percent', 8, 2)->default(0),
            'wht_amount' => fn (Blueprint $t) => $t->decimal('wht_amount', 14, 2)->default(0),
            'wht_input_mode' => fn (Blueprint $t) => $t->string('wht_input_mode', 10)->default('percent'),
            'psf_percent' => fn (Blueprint $t) => $t->decimal('psf_percent', 8, 2)->default(0),
            'psf_amount' => fn (Blueprint $t) => $t->decimal('psf_amount', 14, 2)->default(0),
            'psf_basis' => fn (Blueprint $t) => $t->string('psf_basis', 10)->default('fare'),
            'psf_input_mode' => fn (Blueprint $t) => $t->string('psf_input_mode', 10)->default('percent'),
            'discount_percent' => fn (Blueprint $t) => $t->decimal('discount_percent', 8, 2)->default(0),
            'discount_amount' => fn (Blueprint $t) => $t->decimal('discount_amount', 14, 2)->default(0),
            'discount_input_mode' => fn (Blueprint $t) => $t->string('discount_input_mode', 10)->default('percent'),
            'sales_agent_id' => fn (Blueprint $t) => $t->unsignedBigInteger('sales_agent_id')->nullable(),
            'agent_commission_percent' => fn (Blueprint $t) => $t->decimal('agent_commission_percent', 8, 2)->default(0),
            'agent_commission_amount' => fn (Blueprint $t) => $t->decimal('agent_commission_amount', 14, 2)->default(0),
            'agent_commission_input_mode' => fn (Blueprint $t) => $t->string('agent_commission_input_mode', 10)->default('percent'),
            'total_amount' => fn (Blueprint $t) => $t->decimal('total_amount', 14, 2)->default(0),
            'supplier_net' => fn (Blueprint $t) => $t->decimal('supplier_net', 14, 2)->default(0),
        ];

        foreach ($columns as $name => $add) {
            if (!Schema::hasColumn(self::T, $name)) {
                Schema::table(self::T, fn (Blueprint $t) => $add($t));
            }
        }

        if (!$this->fkExists('itd_airline_fk', 'airline_id')) {
            Schema::table(self::T, fn (Blueprint $t) => $t->foreign('airline_id', 'itd_airline_fk')->references('id')->on('airlines')->nullOnDelete());
        }
        if (!$this->fkExists('itd_agent_fk', 'sales_agent_id')) {
            Schema::table(self::T, fn (Blueprint $t) => $t->foreign('sales_agent_id', 'itd_agent_fk')->references('id')->on('users')->nullOnDelete());
        }
    }

    public function down(): void
    {
        foreach (['itd_airline_fk' => 'airline_id', 'itd_agent_fk' => 'sales_agent_id'] as $name => $col) {
            $fks = collect(Schema::getForeignKeys(self::T));
            if ($fks->contains(fn ($fk) => ($fk['name'] ?? null) === $name)) {
                Schema::table(self::T, fn (Blueprint $t) => $t->dropForeign($name));
            } elseif ($fks->contains(fn ($fk) => ($fk['columns'] ?? []) === [$col])) {
                Schema::table(self::T, fn (Blueprint $t) => $t->dropForeign([$col]));
            }
        }

        Schema::table(self::T, function (Blueprint $t) {
            $t->dropColumn([
                'pax_type', 'airline_id', 'trip_type',
                'leg1_from', 'leg1_stay', 'leg1_to', 'leg1_departure_date', 'leg1_departure_time',
                'leg2_from', 'leg2_stay', 'leg2_to', 'leg2_departure_date', 'leg2_departure_time',
                'fare_amount', 'tax_amount', 'apt_percent', 'apt_charges', 'apt_input_mode',
                'commission_percent', 'commission_amount', 'commission_input_mode',
                'wht_percent', 'wht_amount', 'wht_input_mode',
                'psf_percent', 'psf_amount', 'psf_basis', 'psf_input_mode',
                'discount_percent', 'discount_amount', 'discount_input_mode',
                'sales_agent_id', 'agent_commission_percent', 'agent_commission_amount', 'agent_commission_input_mode',
                'total_amount', 'supplier_net',
            ]);
        });
    }

    /** By name (MySQL) or, since SQLite stores no FK names, by column. */
    private function fkExists(string $name, string $column): bool
    {
        return collect(Schema::getForeignKeys(self::T))->contains(
            fn ($fk) => ($fk['name'] ?? null) === $name || ($fk['columns'] ?? []) === [$column]
        );
    }
};
