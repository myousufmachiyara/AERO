@extends('layouts.app')

@section('title', 'Print (Internal) — ' . $invoice->invoice_no)

@section('content')
@include('travel-invoices._print_body', ['pageTitle' => 'Tour Invoice — Internal Copy'])
@endsection
