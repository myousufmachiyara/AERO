@extends('layouts.app')

@section('title', 'Edit Sale Invoice (Tickets)')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('ticket_invoices.update', $invoice->id) }}" method="POST">
            @csrf @method('PUT')
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Sale Invoice — {{ $invoice->invoice_no }}</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </header>
                <div class="card-body">
                    @include('ticket-invoices._form', ['invoice' => $invoice])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('ticket_invoices.show', $invoice->id) }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Invoice</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection