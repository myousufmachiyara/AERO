@php($customer = $customer ?? null)

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Customer Name<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label>Customer Type<span class="text-danger">*</span></label>
        <select name="customer_type" class="form-control" required>
            @foreach($types as $type)
                <option value="{{ $type }}" @selected(old('customer_type', $customer->customer_type ?? '') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label>Contact Person</label>
        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $customer->contact_person ?? '') }}" placeholder="For corporate/agent customers">
    </div>
    <div class="col-md-4 mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>CNIC / Passport No.</label>
        <input type="text" name="cnic_passport" class="form-control" value="{{ old('cnic_passport', $customer->cnic_passport ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label>Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $customer->address ?? '') }}">
    </div>
    <div class="col-md-2 mb-3">
        <label>Credit Limit</label>
        <input type="number" step="any" name="credit_limit" class="form-control" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
    </div>
    <div class="col-md-2 mb-3">
        <label>Credit Days</label>
        <input type="number" name="credit_days" class="form-control" value="{{ old('credit_days', $customer->credit_days ?? 0) }}">
    </div>
    <div class="col-md-2 mb-3">
        <label>Opening Balance (Receivable)</label>
        <input type="number" step="any" name="opening_balance" class="form-control" value="{{ old('opening_balance', $customer->opening_balance ?? 0) }}" {{ $customer ? 'readonly' : '' }}>
    </div>
    <div class="col-md-4 mb-3">
        <label>Status</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected(old('is_active', $customer->is_active ?? true) == 1)>Active</option>
            <option value="0" @selected(old('is_active', $customer->is_active ?? true) == 0)>Inactive</option>
        </select>
    </div>
    <div class="col-md-12 mb-3">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $customer->remarks ?? '') }}</textarea>
    </div>
</div>
@if($customer && $customer->account)
<div class="alert alert-light border">
    Linked ledger account: <strong>{{ $customer->account->account_code }} — {{ $customer->account->name }}</strong>
    (Receivables: {{ number_format($customer->account->receivables, 2) }})
</div>
@endif
