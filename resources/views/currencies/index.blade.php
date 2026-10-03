@extends('layouts.app')

@section('title', 'Currencies')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;">
                <h2 class="card-title">Currencies</h2>
                <div>
                    @can('currencies.create')
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
                        <thead><tr><th>S.No</th><th>Code</th><th>Name</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($currencies as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->code }}</td>
                                <td>{{ $item->name }}</td>
                                <td>
                                    @can('currencies.edit')
                                    <a href="javascript:void(0);" class="text-primary" onclick="editCurrency({{ $item->id }})"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('currencies.delete')
                                    <form action="{{ route('currencies.destroy', $item->id) }}" method="POST" style="display:inline;">
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

        @can('currencies.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" action="{{ route('currencies.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">New Currency</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Code<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" placeholder="e.g. USD" maxlength="3" style="text-transform:uppercase;" name="code" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" placeholder="e.g. US Dollar" name="name" required>
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

        @can('currencies.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Update Currency</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Code<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" maxlength="3" style="text-transform:uppercase;" name="code" id="edit_code" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
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
    function editCurrency(id) {
        fetch(`/currencies/${id}/edit`)
            .then(res => res.json())
            .then(data => {
                $('#updateForm').attr('action', `/currencies/${id}`);
                $('#edit_code').val(data.code);
                $('#edit_name').val(data.name);
                $.magnificPopup.open({ items: { src: '#updateModal' }, type: 'inline' });
            });
    }
</script>
@endsection