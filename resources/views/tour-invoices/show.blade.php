@extends('layouts.app')

@section('title', $invoice->invoice_no)

@section('content')
@include('travel-invoices._show_body')
@endsection
