@extends('layouts.app')

@section('title', 'Services')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;">
                <h2 class="card-title">Services</h2>
                <div>
                    @can('services.create')
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
                        <thead><tr><th>S.No</th><th>Name</th><th>Category</th><th>Default Supplier</th><th>Unit</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($services as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ ucfirst($item->category) }}</td>
                                <td>{{ $item->defaultSupplier->name ?? '—' }}</td>
                                <td>{{ $item->default_unit }}</td>
                                <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('services.edit')
                                    <a href="javascript:void(0);" class="text-primary" onclick='editService(@json($item))'><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('services.delete')
                                    <form action="{{ route('services.destroy', $item->id) }}" method="POST" style="display:inline;">
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

        @can('services.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" action="{{ route('services.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">New Service</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Ziyarat, SIM Card, Travel Insurance" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Category<span class="text-danger">*</span></label>
                            <select name="category" class="form-control" required>
                                @foreach($categories as $cat)<option value="{{ $cat }}">{{ ucfirst($cat) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Supplier</label>
                            <select name="default_supplier_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Unit</label>
                            <input type="text" class="form-control" name="default_unit" placeholder="e.g. per pax, per day">
                        </div>
                        <div class="form-group mb-3">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
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

        @can('services.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Update Service</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Category<span class="text-danger">*</span></label>
                            <select name="category" id="edit_category" class="form-control" required>
                                @foreach($categories as $cat)<option value="{{ $cat }}">{{ ucfirst($cat) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Supplier</label>
                            <select name="default_supplier_id" id="edit_default_supplier_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Default Unit</label>
                            <input type="text" class="form-control" name="default_unit" id="edit_default_unit">
                        </div>
                        <div class="form-group mb-3">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
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
    function editService(item) {
        $('#updateForm').attr('action', `/services/${item.id}`);
        $('#edit_name').val(item.name);
        $('#edit_category').val(item.category);
        $('#edit_default_supplier_id').val(item.default_supplier_id).trigger('change');
        $('#edit_default_unit').val(item.default_unit);
        $('#edit_description').val(item.description);
        $('#edit_is_active').val(item.is_active ? '1' : '0');
        $.magnificPopup.open({ items: { src: '#updateModal' }, type: 'inline' });
    }
</script>
@endsection
