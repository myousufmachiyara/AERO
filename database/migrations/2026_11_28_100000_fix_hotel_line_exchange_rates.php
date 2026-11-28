<?php

use App\Models\ServiceLine;
use App\Models\TravelInvoice;
use App\Services\TourInvoicePostingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Client fix (Nov 28): Hotel lines were stored in the foreign currency
 * (Receivable/Payable × 1) instead of × their Receivable/Payable exchange
 * rates, so statements and ledgers showed the pre-conversion figure while
 * the Hotel tab showed the converted one. Recompute the stored PKR amounts
 * for existing hotel lines, refresh invoice totals, and re-post confirmed
 * tour invoices so their vouchers carry the corrected amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        $touched = [];

        $rows = DB::table('service_lines as sl')
            ->join('invoice_hotel_details as hd', 'hd.service_line_id', '=', 'sl.id')
            ->where('sl.service_type', 'hotel')
            ->select('sl.id', 'hd.receivable_exchange_rate as rr', 'hd.payable_exchange_rate as pr')
            ->get();

        foreach ($rows as $r) {
            $line = ServiceLine::find($r->id);
            if (!$line) {
                continue;
            }
            $rr = (float) ($r->rr ?: 1);
            $pr = (float) ($r->pr ?: 1);
            $charges = round((float) $line->income_l_amount - ((float) $line->receivable_l_amount - (float) $line->payable_l_amount), 2);
            $recv = round((float) $line->receivable_f_amount * $rr, 2);
            $pay = round((float) $line->payable_f_amount * $pr, 2);
            if (abs($recv - (float) $line->receivable_l_amount) < 0.005 && abs($pay - (float) $line->payable_l_amount) < 0.005) {
                continue;
            }
            $line->receivable_l_amount = $recv;
            $line->payable_l_amount = $pay;
            $line->income_l_amount = round($recv - $pay + $charges, 2);
            $line->saveQuietly();
            $touched[$line->linkable_type][$line->linkable_id] = true;
        }

        foreach ($touched as $type => $ids) {
            foreach (array_keys($ids) as $id) {
                $doc = $type::find($id);
                if (!$doc) {
                    continue;
                }
                if (method_exists($doc, 'recalculateTotals')) {
                    $doc->recalculateTotals();
                }
                if ($doc instanceof TravelInvoice) {
                    $posting = app(TourInvoicePostingService::class);
                    if ($posting->isPosted($doc)) {
                        try {
                            DB::transaction(function () use ($posting, $doc) {
                                $posting->unpost($doc);
                                $posting->post($doc->fresh());
                            });
                        } catch (\Throwable $e) {
                            Log::warning("Hotel rate fix: could not re-post tour invoice {$doc->invoice_no}: {$e->getMessage()}");
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Data correction only; nothing to undo.
    }
};
