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
                            <select name="customer_id" id="customer_id" class="form-control select2-js" onchange="filterInvoices()">
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
                            <label class="form-label">Related Invoice</label>
                            <select name="travel_invoice_id" id="travel_invoice_id" class="form-control select2-js">
                                <option value="">— None / General —</option>
                                @foreach($invoices as $inv)
                                <option value="{{ $inv->id }}" data-customer="{{ $inv->customer_id }}" @selected(old('travel_invoice_id', $prefillInvoiceId) == $inv->id)>
                                    {{ $inv->invoice_no }} — {{ $inv->customer->name ?? '—' }} (Outstanding: {{ number_format($inv->outstandingAmount(), 2) }})
                                </option>
                                @endforeach
                            </select>
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

    function filterInvoices() {
        const customerId = document.getElementById('customer_id').value;
        const select = document.getElementById('travel_invoice_id');
        select.querySelectorAll('option[data-customer]').forEach(opt => {
            opt.hidden = customerId && opt.dataset.customer !== customerId;
        });
        if (window.jQuery) $(select).trigger('change.select2');
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleDirection();
        filterInvoices();
    });
</script>
@endsection
