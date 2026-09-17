@extends('layouts.app')

@section('title', 'Edit Vendor Complaint')

@section('content')
<div class="row">
    <form action="{{ route('vendor_complaints.update', $complaint->id) }}" method="POST">
        @csrf @method('PUT')
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Vendor Complaint</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('vendor-complaints._form', [
                        'suppliers' => $suppliers, 'customers' => $customers, 'serviceTypes' => $serviceTypes,
                        'severities' => $severities, 'statuses' => $statuses, 'complaint' => $complaint,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('vendor_complaints.show', $complaint->id) }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Complaint</button>
                </footer>
            </section>
        </div>
    </form>
</div>
@endsection
