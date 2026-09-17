@extends('layouts.app')

@section('title', 'Edit Quotation')

@section('content')
<div class="row">
    <form action="{{ route('quotations.update', $quotation->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
        @csrf
        @method('PUT')
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Quotation — {{ $quotation->quotation_no }}</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('quotations._form', [
                        'customers' => $customers, 'packages' => $packages, 'packageServiceMap' => $packageServiceMap,
                        'suppliers' => $suppliers, 'chargeTemplates' => $chargeTemplates, 'serviceTypes' => $serviceTypes,
                        'quotation' => $quotation,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('quotations.show', $quotation->id) }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Quotation</button>
                </footer>
            </section>
        </div>
    </form>
</div>
@endsection
