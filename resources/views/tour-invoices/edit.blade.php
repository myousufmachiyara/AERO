@extends('layouts.app')

@section('title', 'Edit Tour Invoice')

@section('content')
@include('travel-invoices._edit_body', ['pageTitle' => 'Edit Tour Invoice'])
@endsection
