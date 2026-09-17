@extends('layouts.app')

@section('title', 'Print — ' . $invoice->invoice_no)

@section('content')
@include('travel-invoices._print_body', ['pageTitle' => 'Tour Invoice'])
@endsection
