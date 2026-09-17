@extends('layouts.app')

@section('title', 'Log Vendor Complaint')

@section('content')
<div class="row">
    <form action="{{ route('vendor_complaints.store') }}" method="POST">
        @csrf
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Log Vendor Complaint</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('vendor-complaints._form', [
                        'suppliers' => $suppliers, 'customers' => $customers, 'serviceTypes' => $serviceTypes,
                        'severities' => $severities, 'statuses' => $statuses, 'prefillSupplierId' => $prefillSupplierId,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('vendor_complaints.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Complaint</button>
                </footer>
            </section>
        </div>
    </form>
</div>
@endsection
