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
    /** Explicit short name — the auto-generated one is 65 chars, over MySQL's 64 limit. */
    private const LINE_FK = 'tile_line_id_fk';

    public function up(): void
    {
        // Each step is guarded so a partially-applied run (MySQL DDL is not
        // transactional) can simply be re-run.
        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_sale_invoice_lines', 'commission_input_mode')) {
                $table->string('commission_input_mode', 10)->default('percent')->after('commission_percent');
            }
            if (!Schema::hasColumn('ticket_sale_invoice_lines', 'wht_input_mode')) {
                $table->string('wht_input_mode', 10)->default('percent')->after('wht_percent');
            }
            if (!Schema::hasColumn('ticket_sale_invoice_lines', 'agent_commission_input_mode')) {
                $table->string('agent_commission_input_mode', 10)->default('percent')->after('agent_commission_percent');
            }
        });

        if (!Schema::hasColumn('ticket_invoice_ledger_entries', 'ticket_sale_invoice_line_id')) {
            Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('ticket_sale_invoice_line_id')->nullable()->after('ticket_sale_invoice_id');
            });
        }

        if (!$this->foreignKeyExists('ticket_invoice_ledger_entries', self::LINE_FK)) {
            Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
                $table->foreign('ticket_sale_invoice_line_id', self::LINE_FK)
                    ->references('id')->on('ticket_sale_invoice_lines')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('ticket_invoice_ledger_entries', 'adjustment')) {
            Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
                $table->string('adjustment', 10)->nullable()->after('entry_type'); // refund | void
            });
        }
    }

    public function down(): void
    {
        // MySQL: drop by our explicit name. SQLite keeps no FK names, so fall back to the column.
        $fks = collect(Schema::getForeignKeys('ticket_invoice_ledger_entries'));
        if ($fks->contains(fn ($fk) => ($fk['name'] ?? null) === self::LINE_FK)) {
            Schema::table('ticket_invoice_ledger_entries', fn (Blueprint $table) => $table->dropForeign(self::LINE_FK));
        } elseif ($fks->contains(fn ($fk) => ($fk['columns'] ?? []) === ['ticket_sale_invoice_line_id'])) {
            Schema::table('ticket_invoice_ledger_entries', fn (Blueprint $table) => $table->dropForeign(['ticket_sale_invoice_line_id']));
        }

        Schema::table('ticket_invoice_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['ticket_sale_invoice_line_id', 'adjustment']);
        });

        Schema::table('ticket_sale_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['commission_input_mode', 'wht_input_mode', 'agent_commission_input_mode']);
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        // Matches by name (MySQL) or, since SQLite stores no FK names, by column.
        return collect(Schema::getForeignKeys($table))->contains(
            fn ($fk) => ($fk['name'] ?? null) === $name || ($fk['columns'] ?? []) === ['ticket_sale_invoice_line_id']
        );
    }
};