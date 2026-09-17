@extends('layouts.app')

@section('title', 'Hotel Room Types')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;">
                <h2 class="card-title">Hotel Room Types</h2>
                <div>
                    @can('hotel_rooms.create')
                    <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                        <i class="fas fa-plus"></i> Add Room Type
                    </button>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <select name="hotel_id" class="form-control" onchange="this.form.submit()">
                            <option value="all">All Hotels</option>
                            @foreach($hotels as $h)
                                <option value="{{ $h->id }}" @selected(request('hotel_id') == $h->id)>{{ $h->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="modal-wrapper table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Hotel</th><th>Room Type</th><th>View</th><th>Capacity</th><th>Default Rate</th><th>Currency</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse ($rooms as $item)
                            <tr>
                                <td>{{ $item->hotel->name ?? '—' }}</td>
                                <td>{{ $item->room_type }}</td>
                                <td>{{ $item->roomView->name ?? '—' }}</td>
                                <td>{{ $item->capacity }}</td>
                                <td>{{ number_format($item->default_rate, 2) }}</td>
                                <td>{{ $item->currency }}</td>
                                <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('hotel_rooms.edit')
                                    <a href="javascript:void(0);" class="text-primary" onclick='editRoom(@json($item))'><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('hotel_rooms.delete')
                                    <form action="{{ route('hotel_rooms.destroy', $item->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted">No room types found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @can('hotel_rooms.create')
        <div id="addModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="post" action="{{ route('hotel_rooms.store') }}" onkeydown="return event.key != 'Enter';">
                    @csrf
                    <header class="card-header"><h2 class="card-title">New Room Type</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Hotel<span class="text-danger">*</span></label>
                            <select name="hotel_id" class="form-control select2-js" required>
                                <option value="">Select Hotel</option>
                                @foreach($hotels as $h)<option value="{{ $h->id }}" @selected(request('hotel_id') == $h->id)>{{ $h->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Room Type<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room_type" placeholder="e.g. Quad, Triple, Double" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Room View</label>
                            <select name="room_view_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($roomViews as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Capacity<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="capacity" value="1" min="1" required>
                        </div>
                        <div class="row">
                            <div class="col-8">
                                <div class="form-group mb-3">
                                    <label>Default Rate<span class="text-danger">*</span></label>
                                    <input type="number" step="any" class="form-control" name="default_rate" value="0" required>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group mb-3">
                                    <label>Currency</label>
                                    <input type="text" class="form-control" name="currency" value="PKR" maxlength="3">
                                </div>
                            </div>
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

        @can('hotel_rooms.edit')
        <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
            <section class="card">
                <form method="POST" id="updateForm" action="" onkeydown="return event.key != 'Enter';">
                    @csrf @method('PUT')
                    <header class="card-header"><h2 class="card-title">Update Room Type</h2></header>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Hotel<span class="text-danger">*</span></label>
                            <select name="hotel_id" id="edit_hotel_id" class="form-control select2-js" required>
                                @foreach($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Room Type<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room_type" id="edit_room_type" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Room View</label>
                            <select name="room_view_id" id="edit_room_view_id" class="form-control select2-js">
                                <option value="">— None —</option>
                                @foreach($roomViews as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Capacity<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1" required>
                        </div>
                        <div class="row">
                            <div class="col-8">
                                <div class="form-group mb-3">
                                    <label>Default Rate<span class="text-danger">*</span></label>
                                    <input type="number" step="any" class="form-control" name="default_rate" id="edit_default_rate" required>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group mb-3">
                                    <label>Currency</label>
                                    <input type="text" class="form-control" name="currency" id="edit_currency" maxlength="3">
                                </div>
                            </div>
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
    function editRoom(item) {
        $('#updateForm').attr('action', `/hotel_rooms/${item.id}`);
        $('#edit_hotel_id').val(item.hotel_id).trigger('change');
        $('#edit_room_type').val(item.room_type);
        $('#edit_room_view_id').val(item.room_view_id).trigger('change');
        $('#edit_capacity').val(item.capacity);
        $('#edit_default_rate').val(item.default_rate);
        $('#edit_currency').val(item.currency);
        $('#edit_is_active').val(item.is_active ? '1' : '0');
        $.magnificPopup.open({ items: { src: '#updateModal' }, type: 'inline' });
    }
</script>
@endsection
