<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TravelInvoice;
use App\Models\Voucher;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * A travel-scoped screen over the existing Voucher module: recording a
 * customer receipt or a supplier payment here always posts a real
 * double-entry Voucher (so ledgers/trial balance/party statements keep
 * working exactly as before), while also keeping an invoice_payments row
 * so a Sale/Tour Invoice can show how much of itself has been collected
 * without walking the general ledger.
 */
class InvoicePaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = InvoicePayment::with('customer', 'supplier', 'travelInvoice')->latest('payment_date');

        if ($request->filled('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        return view('invoice-payments.index', [
            'payments' => $query->paginate(25)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('invoice-payments.create', array_merge($this->referenceData(), [
            'prefillDirection' => $request->get('direction', 'receipt'),
            'prefillCustomerId' => $request->integer('customer_id') ?: null,
            'prefillSupplierId' => $request->integer('supplier_id') ?: null,
            'prefillInvoiceId' => $request->integer('travel_invoice_id') ?: null,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::beginTransaction();
        try {
            $cashBankAccountId = $validated['cash_bank_account_id'];

            if ($validated['direction'] === 'receipt') {
                $customer = Customer::findOrFail($validated['customer_id']);
                if (!$customer->chart_of_account_id) {
                    throw new \RuntimeException('This customer has no ledger account yet — re-save the customer record first.');
                }
                // Receiving cash increases Cash/Bank (debit) and reduces what the customer owes (credit to their AR account).
                $drAccountId = $cashBankAccountId;
                $crAccountId = $customer->chart_of_account_id;
            } else {
                $supplier = Supplier::findOrFail($validated['supplier_id']);
                if (!$supplier->chart_of_account_id) {
                    throw new \RuntimeException('This supplier has no ledger account yet — re-save the supplier record first.');
                }
                // Paying a supplier reduces what we owe them (debit to their AP account) and reduces Cash/Bank (credit).
                $drAccountId = $supplier->chart_of_account_id;
                $crAccountId = $cashBankAccountId;
            }

            $voucher = Voucher::create([
                'voucher_type' => $validated['direction'] === 'receipt' ? 'receipt' : 'payment',
                'date' => $validated['payment_date'],
                'ac_dr_sid' => $drAccountId,
                'ac_cr_sid' => $crAccountId,
                'amount' => $validated['amount'],
                'reference' => $validated['reference'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'description' => $validated['direction'] === 'receipt'
                    ? 'Travel: customer receipt' . (!empty($validated['travel_invoice_id']) ? ' against invoice #' . $validated['travel_invoice_id'] : '')
                    : 'Travel: supplier payment' . (!empty($validated['travel_invoice_id']) ? ' re. invoice #' . $validated['travel_invoice_id'] : ''),
            ]);

            $payment = InvoicePayment::create([
                'voucher_id' => $voucher->id,
                'direction' => $validated['direction'],
                'customer_id' => $validated['customer_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'travel_invoice_id' => $validated['travel_invoice_id'] ?? null,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_mode' => $validated['payment_mode'],
                'reference' => $validated['reference'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('invoice_payments.show', $payment->id)->with('success', 'Payment recorded and posted to the ledger.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[InvoicePayment] store failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not record payment: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $payment = InvoicePayment::with('customer', 'supplier', 'travelInvoice', 'voucher', 'creator')->findOrFail($id);

        return view('invoice-payments.show', compact('payment'));
    }

    /**
     * Financial records aren't edited in place here — reverse (delete,
     * which also removes the posted voucher) and re-record instead, so
     * the ledger never carries a silently-changed amount. edit()/update()
     * exist only so the generic route loop's routes don't 404/fatal if
     * ever hit directly; the UI never links to them (see index/show views).
     */
    public function edit(string $id)
    {
        return redirect()->route('invoice_payments.show', $id)
            ->with('error', 'Payments cannot be edited — delete and re-record instead, so the ledger stays accurate.');
    }

    public function update(Request $request, string $id)
    {
        return $this->edit($id);
    }

    public function destroy(string $id)
    {
        $payment = InvoicePayment::findOrFail($id);

        DB::beginTransaction();
        try {
            $voucher = $payment->voucher;
            $payment->delete();
            $voucher?->delete();

            DB::commit();

            return redirect()->route('invoice_payments.index')->with('success', 'Payment reversed and removed from the ledger.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[InvoicePayment] destroy failed: ' . $e->getMessage());

            return back()->with('error', 'Could not delete payment: ' . $e->getMessage());
        }
    }

    public function print(string $id)
    {
        $payment = InvoicePayment::with('customer', 'supplier', 'travelInvoice', 'voucher')->findOrFail($id);

        return view('invoice-payments.print', compact('payment'));
    }

    protected function referenceData(): array
    {
        $cashBankAccounts = ChartOfAccounts::whereHas(
            'subHeadOfAccount',
            fn ($q) => $q->whereIn('name', config('travel.cash_bank_subhead_names'))
        )->orderBy('name')->get();

        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'invoices' => TravelInvoice::with('customer')->latest('invoice_date')->get(),
            'cashBankAccounts' => $cashBankAccounts,
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(InvoicePayment::DIRECTIONS)],
            'customer_id' => 'nullable|required_if:direction,receipt|exists:customers,id',
            'supplier_id' => 'nullable|required_if:direction,payment|exists:suppliers,id',
            'travel_invoice_id' => 'nullable|exists:travel_invoices,id',
            'cash_bank_account_id' => 'required|exists:chart_of_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => ['required', Rule::in(InvoicePayment::MODES)],
            'reference' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        return $data;
    }
}
