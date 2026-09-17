@php($package = $package ?? null)
@php($existingServices = $package->services ?? collect())

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Package Name<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $package->name ?? '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label>Category<span class="text-danger">*</span></label>
        <select name="category" class="form-control" required>
            @foreach($categories as $cat)<option value="{{ $cat }}" @selected(old('category', $package->category ?? '') === $cat)>{{ ucfirst($cat) }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-2 mb-3">
        <label>Duration (days)</label>
        <input type="number" name="duration_days" class="form-control" min="1" value="{{ old('duration_days', $package->duration_days ?? '') }}">
    </div>
    <div class="col-md-2 mb-3">
        <label>Base Price</label>
        <input type="number" step="any" name="base_price" class="form-control" value="{{ old('base_price', $package->base_price ?? 0) }}">
    </div>
    <div class="col-md-1 mb-3">
        <label>Currency</label>
        <input type="text" name="currency" class="form-control" maxlength="3" value="{{ old('currency', $package->currency ?? 'PKR') }}">
    </div>
    <div class="col-md-12 mb-3">
        <label>Inclusions</label>
        <textarea name="inclusions" class="form-control" rows="2" placeholder="What's included, in plain language for the customer">{{ old('inclusions', $package->inclusions ?? '') }}</textarea>
    </div>
    <div class="col-md-3 mb-3">
        <label>Status</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected(old('is_active', $package->is_active ?? true) == 1)>Active</option>
            <option value="0" @selected(old('is_active', $package->is_active ?? true) == 0)>Inactive</option>
        </select>
    </div>
</div>

<hr>
<h5>Bundled Services</h5>
<p class="text-muted">What this package includes by default — applying it on a Quotation pre-fills one line per row below.</p>
<table class="table table-bordered" id="pkgServicesTable">
    <thead><tr><th width="18%">Service Type</th><th>Reference</th><th width="10%">Qty</th><th>Notes</th><th width="50px"></th></tr></thead>
    <tbody>
        @forelse($existingServices as $i => $s)
        <tr>
            <td>
                <select name="services[{{ $i }}][service_type]" class="form-control pkg-service-type" onchange="togglePkgReference(this)">
                    <option value="">Select Type</option>
                    @foreach($serviceTypes as $t)<option value="{{ $t }}" @selected($t === $s->service_type)>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </td>
            <td>
                <select name="services[{{ $i }}][hotel_room_id]" class="form-control pkg-ref" data-type="hotel" style="{{ $s->service_type === 'hotel' ? '' : 'display:none' }}">
                    <option value="">Select Room</option>
                    @foreach($hotelRooms as $r)<option value="{{ $r->id }}" @selected($r->id == $s->hotel_room_id)>{{ $r->hotel->name }} — {{ $r->room_type }}</option>@endforeach
                </select>
                <select name="services[{{ $i }}][vehicle_id]" class="form-control pkg-ref" data-type="transport" style="{{ $s->service_type === 'transport' ? '' : 'display:none' }}">
                    <option value="">Select Vehicle</option>
                    @foreach($vehicles as $v)<option value="{{ $v->id }}" @selected($v->id == $s->vehicle_id)>{{ $v->name }}</option>@endforeach
                </select>
                <select name="services[{{ $i }}][visa_type_id]" class="form-control pkg-ref" data-type="visa" style="{{ $s->service_type === 'visa' ? '' : 'display:none' }}">
                    <option value="">Select Visa Type</option>
                    @foreach($visaTypes as $vt)<option value="{{ $vt->id }}" @selected($vt->id == $s->visa_type_id)>{{ $vt->name }}</option>@endforeach
                </select>
                <select name="services[{{ $i }}][service_id]" class="form-control pkg-ref" data-type="other" style="{{ $s->service_type === 'other' ? '' : 'display:none' }}">
                    <option value="">Select Service</option>
                    @foreach($services as $sv)<option value="{{ $sv->id }}" @selected($sv->id == $s->service_id)>{{ $sv->name }}</option>@endforeach
                </select>
                <span class="pkg-ref-ticket text-muted" data-type="ticket" style="{{ $s->service_type === 'ticket' ? '' : 'display:none' }}">Filled in on the invoice</span>
            </td>
            <td><input type="number" name="services[{{ $i }}][qty]" class="form-control" min="1" value="{{ $s->qty }}"></td>
            <td><input type="text" name="services[{{ $i }}][notes]" class="form-control" value="{{ $s->notes }}"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removePkgRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>
        @empty
        <tr>
            <td>
                <select name="services[0][service_type]" class="form-control pkg-service-type" onchange="togglePkgReference(this)">
                    <option value="">Select Type</option>
                    @foreach($serviceTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                </select>
            </td>
            <td>
                <select name="services[0][hotel_room_id]" class="form-control pkg-ref" data-type="hotel" style="display:none">
                    <option value="">Select Room</option>
                    @foreach($hotelRooms as $r)<option value="{{ $r->id }}">{{ $r->hotel->name }} — {{ $r->room_type }}</option>@endforeach
                </select>
                <select name="services[0][vehicle_id]" class="form-control pkg-ref" data-type="transport" style="display:none">
                    <option value="">Select Vehicle</option>
                    @foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                </select>
                <select name="services[0][visa_type_id]" class="form-control pkg-ref" data-type="visa" style="display:none">
                    <option value="">Select Visa Type</option>
                    @foreach($visaTypes as $vt)<option value="{{ $vt->id }}">{{ $vt->name }}</option>@endforeach
                </select>
                <select name="services[0][service_id]" class="form-control pkg-ref" data-type="other" style="display:none">
                    <option value="">Select Service</option>
                    @foreach($services as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                </select>
                <span class="pkg-ref-ticket text-muted" data-type="ticket" style="display:none">Filled in on the invoice</span>
            </td>
            <td><input type="number" name="services[0][qty]" class="form-control" min="1" value="1"></td>
            <td><input type="text" name="services[0][notes]" class="form-control"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removePkgRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>
        @endforelse
    </tbody>
</table>
<button type="button" class="btn btn-success btn-sm" onclick="addPkgRow()">+ Add Service</button>

<script>
    let pkgRowIndex = {{ max($existingServices->count(), 1) }};

    function togglePkgReference(select) {
        const row = select.closest('tr');
        row.querySelectorAll('.pkg-ref, .pkg-ref-ticket').forEach(el => {
            el.style.display = (el.dataset.type === select.value) ? '' : 'none';
        });
    }

    function addPkgRow() {
        const tbody = document.querySelector('#pkgServicesTable tbody');
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/services\[\d+\]/, `services[${pkgRowIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else if (el.type !== 'number' || !el.name.includes('qty')) el.value = '';
        });
        clone.querySelectorAll('.pkg-ref, .pkg-ref-ticket').forEach(el => el.style.display = 'none');
        tbody.appendChild(clone);
        pkgRowIndex++;
    }

    function removePkgRow(btn) {
        const tbody = document.querySelector('#pkgServicesTable tbody');
        if (tbody.rows.length > 1) btn.closest('tr').remove();
    }
</script>
