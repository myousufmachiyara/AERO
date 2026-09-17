@extends('layouts.app')

@section('title', 'Tour Invoice')

@section('content')
@include('travel-invoices._index_body', ['pageTitle' => 'Tour Invoice', 'newLabel' => 'New Tour Invoice'])
@endsection
