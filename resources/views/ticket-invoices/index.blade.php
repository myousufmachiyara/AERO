@extends('layouts.app')

@section('title', 'Sale Invoice (Tickets)')

@section('content')
@include('travel-invoices._index_body', ['pageTitle' => 'Sale Invoice (Tickets)', 'newLabel' => 'New Sale Invoice'])
@endsection
