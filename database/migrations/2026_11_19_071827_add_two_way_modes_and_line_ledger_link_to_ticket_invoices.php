<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Short explicit name: the auto-generated one is 65 chars, over MySQL's 64 limit.
    private const LINE_FK = 'tile_line_id_fk';

    public function up(): void
    {
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
                $table->string('adjustment', 10)->nullable()->after('entry_type');
            });
        }
    }

    public function down(): void
    {
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
        return collect(Schema::getForeignKeys($table))->contains(
            fn ($fk) => ($fk['name'] ?? null) === $name || ($fk['columns'] ?? []) === ['ticket_sale_invoice_line_id']
        );
    }
};