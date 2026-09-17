@php($supplier = $supplier ?? null)

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Supplier Name<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $supplier->name ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label>Type<span class="text-danger">*</span></label>
        <select name="type" class="form-control" required>
            @foreach($types as $type)
                <option value="{{ $type }}" @selected(old('type', $supplier->type ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label>Contact Person</label>
        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $supplier->address ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>License No.</label>
        <input type="text" name="license_no" class="form-control" value="{{ old('license_no', $supplier->license_no ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>NTN</label>
        <input type="text" name="ntn" class="form-control" value="{{ old('ntn', $supplier->ntn ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Credit Limit</label>
        <input type="number" step="any" name="credit_limit" class="form-control" value="{{ old('credit_limit', $supplier->credit_limit ?? 0) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Credit Days</label>
        <input type="number" name="credit_days" class="form-control" value="{{ old('credit_days', $supplier->credit_days ?? 0) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Opening Balance (Payable)</label>
        <input type="number" step="any" name="opening_balance" class="form-control" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}" {{ $supplier ? 'readonly title=Opening balance is fixed after creation' : '' }}>
    </div>
    <div class="col-md-4 mb-3">
        <label>Status</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected(old('is_active', $supplier->is_active ?? true) == 1)>Active</option>
            <option value="0" @selected(old('is_active', $supplier->is_active ?? true) == 0)>Inactive</option>
        </select>
    </div>
    <div class="col-md-12 mb-3">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $supplier->remarks ?? '') }}</textarea>
    </div>
</div>
@if($supplier && $supplier->account)
<div class="alert alert-light border">
    Linked ledger account: <strong>{{ $supplier->account->account_code }} — {{ $supplier->account->name }}</strong>
    (Payables: {{ number_format($supplier->account->payables, 2) }})
</div>
@endif
