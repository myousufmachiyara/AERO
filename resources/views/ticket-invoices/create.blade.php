@extends('layouts.app')

@section('title', 'New Sale Invoice (Tickets)')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('ticket_invoices.store') }}" method="POST">
            @csrf
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">New Sale Invoice (Tickets)</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </header>
                <div class="card-body">
                    @include('ticket-invoices._form', ['quotation' => $quotation ?? null])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('ticket_invoices.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection