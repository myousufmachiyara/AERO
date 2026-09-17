@extends('layouts.app')

@section('title', 'Create Quotation')

@section('content')
<div class="row">
    <form action="{{ route('quotations.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
        @csrf
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Create Quotation</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('quotations._form', [
                        'customers' => $customers, 'packages' => $packages, 'packageServiceMap' => $packageServiceMap,
                        'suppliers' => $suppliers, 'chargeTemplates' => $chargeTemplates, 'serviceTypes' => $serviceTypes,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('quotations.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Quotation</button>
                </footer>
            </section>
        </div>
    </form>
</div>
@endsection
