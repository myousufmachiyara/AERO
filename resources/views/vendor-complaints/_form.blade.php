@php($complaint = $complaint ?? null)
@php($prefillSupplierId = $prefillSupplierId ?? null)
<div class="row">
    <div class="col-md-3 mb-3">
        <label class="form-label">Supplier<span class="text-danger">*</span></label>
        <select name="supplier_id" class="form-control select2-js" required>
            <option value="">Select Supplier</option>
            @foreach($suppliers as $s)
            <option value="{{ $s->id }}" @selected(old('supplier_id', $complaint->supplier_id ?? $prefillSupplierId ?? '') == $s->id)>
                {{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}
            </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Service Type<span class="text-danger">*</span></label>
        <select name="service_type" class="form-control" required>
            @foreach($serviceTypes as $t)<option value="{{ $t }}" @selected(old('service_type', $complaint->service_type ?? '') === $t)>{{ ucfirst($t) }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Customer</label>
        <select name="customer_id" class="form-control select2-js">
            <option value="">— None —</option>
            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $complaint->customer_id ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Reference</label>
        <input type="text" name="reference" class="form-control" placeholder="Invoice # / PNR" value="{{ old('reference', $complaint->reference ?? '') }}">
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Complaint Date<span class="text-danger">*</span></label>
        <input type="date" name="complaint_date" class="form-control" value="{{ old('complaint_date', optional($complaint?->complaint_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required>
    </div>
</div>
<div class="row">
    <div class="col-md-2 mb-3">
        <label class="form-label">Severity<span class="text-danger">*</span></label>
        <select name="severity" class="form-control" required>
            @foreach($severities as $sv)<option value="{{ $sv }}" @selected(old('severity', $complaint->severity ?? 'medium') === $sv)>{{ ucfirst($sv) }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Status<span class="text-danger">*</span></label>
        <select name="status" class="form-control" required>
            @foreach($statuses as $st)<option value="{{ $st }}" @selected(old('status', $complaint->status ?? 'open') === $st)>{{ ucfirst($st) }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label">Description<span class="text-danger">*</span></label>
        <textarea name="description" class="form-control" rows="2" required>{{ old('description', $complaint->description ?? '') }}</textarea>
    </div>
</div>
<div class="row">
    <div class="col-md-12 mb-3">
        <label class="form-label">Resolution Notes</label>
        <textarea name="resolution_notes" class="form-control" rows="2">{{ old('resolution_notes', $complaint->resolution_notes ?? '') }}</textarea>
    </div>
</div>
<p class="text-muted mb-0">Saving this recomputes the supplier's complaint flag immediately — if it now meets or exceeds their threshold, they're marked "High Complaints" everywhere they appear (supplier record, and a ⚠ next to their name in every Supplier dropdown).</p>
