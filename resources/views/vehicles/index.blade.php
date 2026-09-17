@extends('layouts.app')

@section('title', 'Vehicles')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;">
                <h2 class="card-title">Vehicles</h2>
                <div>
                    @can('vehicles.create')
                    <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <div class="modal-wrapper table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>S.No</th><th>Name</th><th>Type</th><th>Capacity</th><th>Transporter</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($vehicles as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->type }}</td>
                                <td>{{ $item->capacity }}</td>
                                <td>{{ $item->supplier->name ?? '—' }}</td>
                                <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('vehicles.edit')
                                    <a href="javascript:void(0);" class="text-primary" onclick='editVehicle(@json($item))'><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('vehicles.delete')
                                    <form action="{{ route('vehicles.destroy', $item->id) }}" method="POST" style="display:inline;">
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

        @can('vehicles.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" action="{{ route('vehicles.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">New Vehicle</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Type</label>
                            <input type="text" class="form-control" name="type" placeholder="e.g. Coaster, Hiace, GMC">
                        </div>
                        <div class="form-group mb-3">
                            <label>Capacity (seats)</label>
                            <input type="number" class="form-control" name="capacity" min="1">
                        </div>
                        <div class="form-group mb-3">
                            <label>Transporter</label>
                            <select name="supplier_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($transporters as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                            </select>
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

        @can('vehicles.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Update Vehicle</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Type</label>
                            <input type="text" class="form-control" name="type" id="edit_type">
                        </div>
                        <div class="form-group mb-3">
                            <label>Capacity (seats)</label>
                            <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1">
                        </div>
                        <div class="form-group mb-3">
                            <label>Transporter</label>
                            <select name="supplier_id" id="edit_supplier_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($transporters as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                            </select>
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
    function editVehicle(item) {
        $('#updateForm').attr('action', `/vehicles/${item.id}`);
        $('#edit_name').val(item.name);
        $('#edit_type').val(item.type);
        $('#edit_capacity').val(item.capacity);
        $('#edit_supplier_id').val(item.supplier_id).trigger('change');
        $('#edit_is_active').val(item.is_active ? '1' : '0');
        $.magnificPopup.open({ items: { src: '#updateModal' }, type: 'inline' });
    }
</script>
@endsection
