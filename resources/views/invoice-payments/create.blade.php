@extends('layouts.app')

@section('title', 'Record Payment')

@section('content')
<div class="row">
    <form action="{{ route('invoice_payments.store') }}" method="POST">
        @csrf
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Record Payment</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Direction<span class="text-danger">*</span></label>
                            <select name="direction" id="direction" class="form-control" required onchange="toggleDirection()">
                                <option value="receipt" @selected(old('direction', $prefillDirection) === 'receipt')>Receipt from Customer</option>
                                <option value="payment" @selected(old('direction', $prefillDirection) === 'payment')>Payment to Supplier</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3" id="customerWrap">
                            <label class="form-label">Customer<span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-control select2-js" >
                                <option value="">Select Customer</option>
                                @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $prefillCustomerId) == $c->id)>{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3" id="supplierWrap" style="display:none;">
                            <label class="form-label">Supplier<span class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-control select2-js">
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $prefillSupplierId) == $s->id)>{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Invoice # <small class="text-muted" id="invoice-hint"></small></label>
                            <select name="invoice_ref" id="invoice_ref" class="form-control select2-js @error('invoice_ref') is-invalid @enderror @error('travel_invoice_id') is-invalid @enderror">
                                <option value="">— None / General —</option>
                            </select>
                            @error('invoice_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('travel_invoice_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Cash / Bank Account<span class="text-danger">*</span></label>
                            <select name="cash_bank_account_id" class="form-control select2-js" required>
                                <option value="">Select Account</option>
                                @foreach($cashBankAccounts as $a)<option value="{{ $a->id }}" @selected(old('cash_bank_account_id') == $a->id)>{{ $a->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Payment Date<span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Amount<span class="text-danger">*</span></label>
                            <input type="number" step="any" name="amount" class="form-control" value="{{ old('amount') }}" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Mode<span class="text-danger">*</span></label>
                            <select name="payment_mode" class="form-control" required>
                                @foreach(['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque', 'online' => 'Online'] as $val => $lbl)
                                <option value="{{ $val }}" @selected(old('payment_mode', 'bank') === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="Cheque # / Transaction ID" value="{{ old('reference') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                    <p class="text-muted mb-0">This posts a real double-entry voucher — {{--  --}}a receipt debits the chosen Cash/Bank account and credits the customer's ledger; a payment debits the supplier's ledger and credits Cash/Bank — so it flows straight into your existing Vouchers, Trial Balance and Party Statement screens.</p>
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('invoice_payments.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save & Post</button>
                </footer>
            </section>
        </div>
    </form>
</div>

<script>
    function toggleDirection() {
        const dir = document.getElementById('direction').value;
        document.getElementById('customerWrap').style.display = dir === 'receipt' ? '' : 'none';
        document.getElementById('supplierWrap').style.display = dir === 'payment' ? '' : 'none';
        document.getElementById('customer_id').required = dir === 'receipt';
        document.getElementById('supplier_id').required = dir === 'payment';
    }

    // Ticket Sale + Tour invoices; the list is rebuilt for the chosen
    // customer (receipt) or for the invoices that use the chosen supplier
    // (payment), so the searchable dropdown only ever shows matching ones.
    const PAYMENT_INVOICES = @json($invoices);
    let invoiceSelected = @json(old('invoice_ref', $prefillInvoiceRef ?? null));

    function money(v) { return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function filterInvoices() {
        const dir = document.getElementById('direction').value;
        const customerId = document.getElementById('customer_id').value;
        const supplierId = document.getElementById('supplier_id').value;
        const select = document.getElementById('invoice_ref');
        const current = select.value || invoiceSelected;
        invoiceSelected = null;

        const list = PAYMENT_INVOICES.filter(inv => dir === 'receipt'
            ? (customerId && String(inv.customer_id) === customerId)
            : (supplierId && inv.suppliers.map(String).includes(supplierId)));

        select.innerHTML = '';
        select.add(new Option('— None / General —', ''));
        list.forEach(inv => {
            const label = inv.no + ' · ' + inv.type + ' · ' + inv.date + ' — ' + inv.customer + ' (Outstanding: ' + money(inv.outstanding) + ')';
            select.add(new Option(label, inv.ref, false, inv.ref === current));
        });

        const who = dir === 'receipt' ? 'customer' : 'supplier';
        const picked = dir === 'receipt' ? customerId : supplierId;
        document.getElementById('invoice-hint').textContent = !picked ? '(pick the ' + who + ' first)' : '(' + list.length + ' invoice' + (list.length === 1 ? '' : 's') + ')';
        if (window.jQuery) jQuery(select).trigger('change.select2');
    }

    function onInvoicePicked() {
        // Pre-fill the amount with what is still outstanding on a receipt.
        const ref = document.getElementById('invoice_ref').value;
        const amount = document.querySelector('[name="amount"]');
        const inv = PAYMENT_INVOICES.find(i => i.ref === ref);
        if (inv && amount && !parseFloat(amount.value) && document.getElementById('direction').value === 'receipt' && inv.outstanding > 0) {
            amount.value = inv.outstanding.toFixed(2);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleDirection();
        filterInvoices();
        // select2 lives on the footer jQuery: listen there as well as natively.
        const hook = (id, fn) => {
            const el = document.getElementById(id);
            el.addEventListener('change', fn);
            if (window.jQuery) jQuery(el).on('select2:select select2:clear', fn);
        };
        hook('customer_id', filterInvoices);
        hook('supplier_id', filterInvoices);
        hook('direction', filterInvoices);
        hook('invoice_ref', onInvoicePicked);
    });
</script>
@endsection
