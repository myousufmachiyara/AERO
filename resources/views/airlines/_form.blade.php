@php($airline = $airline ?? null)
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Airline Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $airline->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Numeric Code (3 digits) <span class="text-danger">*</span></label>
        <input type="text" name="numeric_code" maxlength="3" pattern="\d{3}" class="form-control"
               value="{{ old('numeric_code', $airline->numeric_code ?? '') }}" placeholder="e.g. 220" required>
        <small class="text-muted">The first 3 digits of every ticket # issued on this airline.</small>
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $airline->remarks ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                   @checked(old('is_active', $airline->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>