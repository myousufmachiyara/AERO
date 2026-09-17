@php($hotel = $hotel ?? null)

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Hotel Name<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $hotel->name ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label>City</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $hotel->city ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Star Rating</label>
        <select name="star_rating" class="form-control">
            <option value="">—</option>
            @for($i = 1; $i <= 7; $i++)
                <option value="{{ $i }}" @selected(old('star_rating', $hotel->star_rating ?? '') == $i)>{{ $i }} Star</option>
            @endfor
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label>Supplier (Vendor)</label>
        <select name="supplier_id" class="form-control select2-js">
            <option value="">— None —</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $hotel->supplier_id ?? '') == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label>Contact No.</label>
        <input type="text" name="contact_no" class="form-control" value="{{ old('contact_no', $hotel->contact_no ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label>Status</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected(old('is_active', $hotel->is_active ?? true) == 1)>Active</option>
            <option value="0" @selected(old('is_active', $hotel->is_active ?? true) == 0)>Inactive</option>
        </select>
    </div>
    <div class="col-md-12 mb-3">
        <label>Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $hotel->address ?? '') }}">
    </div>
    <div class="col-md-12 mb-3">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $hotel->remarks ?? '') }}</textarea>
    </div>
</div>
@if($hotel)
<div class="alert alert-light border">
    Room types for this hotel are managed from <a href="{{ route('hotel_rooms.index', ['hotel_id' => $hotel->id]) }}">Travel Masters &rarr; Room Types</a>.
</div>
@endif
