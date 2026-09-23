@extends('layouts.app')

@section('title', 'Print — ' . $airline->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Airline — {{ $airline->name }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Numeric Code:</strong> {{ $airline->numeric_code }}</div>
                    <div class="col-md-4"><strong>Status:</strong> {{ $airline->is_active ? 'Active' : 'Inactive' }}</div>
                    <div class="col-md-4"><strong>Account Code:</strong> {{ $airline->account->account_code ?? '—' }}</div>
                </div>
                @if($airline->remarks)<p><strong>Remarks:</strong> {{ $airline->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection