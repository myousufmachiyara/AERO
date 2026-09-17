@extends('layouts.app')

@section('title', 'Edit Charge Template')

@section('content')
<style>
    #chargeItemsTable th { background: #f8f9fa; }
    #chargeItemsTable td { vertical-align: middle; }
</style>
<div class="row">
    <form action="{{ route('charge_templates.update', $template->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
        @csrf
        @method('PUT')
        <div class="col-12 mb-2">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Charge Template — {{ $template->name }}</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                </header>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Service Category<span class="text-danger">*</span></label>
                            <select name="service_category" class="form-control" required>
                                @foreach($categories as $cat)<option value="{{ $cat }}" @selected(old('service_category', $template->service_category) === $cat)>{{ ucfirst($cat) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label>Effective Date<span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', $template->effective_date->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-1 mb-3">
                            <label>Currency</label>
                            <input type="text" name="default_currency" class="form-control" value="{{ old('default_currency', $template->default_currency) }}" maxlength="3">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label>Default Exch. Rate</label>
                            <input type="number" step="any" name="default_exchange_rate" class="form-control" value="{{ old('default_exchange_rate', $template->default_exchange_rate) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label>Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1" @selected($template->is_active)>Active</option>
                                <option value="0" @selected(!$template->is_active)>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="card">
                <header class="card-header"><h2 class="card-title">Default Charges</h2></header>
                <div class="card-body">
                    <table class="table table-bordered" id="chargeItemsTable">
                        <thead>
                            <tr><th>Charge Type</th><th width="20%">Value</th><th width="50px"></th></tr>
                        </thead>
                        <tbody>
                            @forelse($template->items as $i => $item)
                            <tr>
                                <td>
                                    <select name="items[{{ $i }}][charge_type_id]" class="form-control select2-js">
                                        <option value="">Select Charge Type</option>
                                        @foreach($chargeTypes as $ct)
                                            <option value="{{ $ct->id }}" @selected($ct->id == $item->charge_type_id)>{{ $ct->name }} ({{ $ct->code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="any" name="items[{{ $i }}][value]" class="form-control" value="{{ $item->value }}"></td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeChargeRow(this)"><i class="fas fa-times"></i></button></td>
                            </tr>
                            @empty
                            <tr>
                                <td>
                                    <select name="items[0][charge_type_id]" class="form-control select2-js">
                                        <option value="">Select Charge Type</option>
                                        @foreach($chargeTypes as $ct)<option value="{{ $ct->id }}">{{ $ct->name }} ({{ $ct->code }})</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="any" name="items[0][value]" class="form-control"></td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeChargeRow(this)"><i class="fas fa-times"></i></button></td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-success btn-sm" onclick="addChargeRow()">+ Add Charge</button>
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('charge_templates.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </footer>
            </section>
        </div>
    </form>
</div>

<script>
    let chargeRowIndex = {{ max($template->items->count(), 1) }};
    function addChargeRow() {
        const tbody = document.querySelector('#chargeItemsTable tbody');
        const first = tbody.querySelector('tr').cloneNode(true);
        first.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/items\[\d+\]/, `items[${chargeRowIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
            el.classList.remove('select2-hidden-accessible');
            el.removeAttribute('data-select2-id');
        });
        first.querySelectorAll('.select2-container').forEach(el => el.remove());
        tbody.appendChild(first);
        chargeRowIndex++;
        if (window.jQuery) $(first).find('.select2-js').select2();
    }
    function removeChargeRow(btn) {
        const tbody = document.querySelector('#chargeItemsTable tbody');
        if (tbody.rows.length > 1) btn.closest('tr').remove();
    }
</script>
@endsection
