@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
@include('travel-invoices._create_body', ['pageTitle' => 'Create Sale Invoice (Tickets)'])
@endsection
