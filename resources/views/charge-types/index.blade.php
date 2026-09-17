@extends('layouts.app')

@section('title', 'Charge Types')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;">
                <h2 class="card-title">Charge Types</h2>
                <div>
                    @can('charge_types.create')
                    <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <p class="text-muted">The open list of named charges (tax, commission, fees) that can appear on any Ticket / Hotel / Transport / Visa / Other Service line — add a new one here any time instead of changing the invoice screen.</p>
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <div class="modal-wrapper table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>S.No</th><th>Name</th><th>Code</th><th>Calculation</th><th>Default Value</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($chargeTypes as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->code }}</td>
                                <td>{{ ucfirst($item->calculation_type) }}</td>
                                <td>{{ $item->calculation_type === 'percentage' ? $item->default_value.'%' : number_format($item->default_value, 2) }}</td>
                                <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('charge_types.edit')
                                    <a href="javascript:void(0);" class="text-primary" onclick='editChargeType(@json($item))'><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('charge_types.delete')
                                    <form action="{{ route('charge_types.destroy', $item->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @can('charge_types.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" action="{{ route('charge_types.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">New Charge Type</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Withholding Tax" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Code<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" placeholder="e.g. WHT" required maxlength="20">
                        </div>
                        <div class="form-group mb-3">
                            <label>Calculation Type<span class="text-danger">*</span></label>
                            <select name="calculation_type" class="form-control" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Value<span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control" name="default_value" value="0" required>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="row"><div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Add</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </div></div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan

        @can('charge_types.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Update Charge Type</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Code<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" id="edit_code" required maxlength="20">
                        </div>
                        <div class="form-group mb-3">
                            <label>Calculation Type<span class="text-danger">*</span></label>
                            <select name="calculation_type" id="edit_calculation_type" class="form-control" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Value<span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control" name="default_value" id="edit_default_value" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Status</label>
                            <select name="is_active" id="edit_is_active" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="row"><div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </div></div>
                    </footer>
                </form>
            </section>
        </div>
        @endcan
    </div>
</div>

<script>
    function editChargeType(item) {
        $('#updateForm').attr('action', `/charge_types/${item.id}`);
        $('#edit_name').val(item.name);
        $('#edit_code').val(item.code);
        $('#edit_calculation_type').val(item.calculation_type);
        $('#edit_default_value').val(item.default_value);
        $('#edit_is_active').val(item.is_active ? '1' : '0');
        $.magnificPopup.open({ items: { src: '#updateModal' }, type: 'inline' });
    }
</script>
@endsection
