{{--
    Standalone A4 print shell shared by the Tour Invoice customer copy and the
    payment receipt: company logo + name/address/phone/licence (config/travel.php),
    a document title and a meta block. Usage:
      @extends('prints._document', ['docTitle' => 'INVOICE'])
      @section('meta') ... @endsection   @section('body') ... @endsection
--}}
@php
    $logo = collect([config('travel.company_logo_path', 'assets/img/aero-logo.png'), 'assets/img/aero-logo.jpeg', 'assets/img/aero-logo.jpg'])
        ->first(fn ($p) => is_file(public_path($p)));
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $docTitle }} — {{ $docNo ?? '' }}</title>
<style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; color: #1d2733; margin: 0; background: #eef1f4; }
    .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 14mm 18mm; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    .head { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #12355b; padding-bottom: 10px; }
    .head img { height: 64px; width: auto; }
    .company h1 { margin: 0; font-size: 20px; color: #12355b; letter-spacing: .3px; }
    .company p { margin: 2px 0 0; font-size: 11px; color: #4a5866; }
    .title-row { display: flex; justify-content: space-between; align-items: flex-end; margin: 16px 0 10px; }
    .doc-title { font-size: 22px; font-weight: 700; color: #12355b; letter-spacing: 2px; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; border: 1px solid #d6dde4; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; }
    .meta div span { color: #6b7886; display: inline-block; min-width: 110px; }
    h3 { font-size: 13px; margin: 16px 0 6px; color: #12355b; text-transform: uppercase; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d6dde4; padding: 5px 7px; vertical-align: top; text-align: left; }
    th { background: #12355b; color: #fff; font-weight: 600; font-size: 11px; }
    td.num, th.num { text-align: right; white-space: nowrap; }
    tr.sub td { background: #f3f6f9; font-weight: 600; }
    .muted { color: #6b7886; font-size: 11px; }
    .totals { width: 320px; margin-left: auto; margin-top: 12px; }
    .totals td { border: none; border-bottom: 1px solid #e3e8ed; }
    .totals tr.grand td { font-size: 14px; font-weight: 700; color: #12355b; border-bottom: 2px solid #12355b; }
    .words { margin-top: 12px; padding: 8px 12px; background: #f3f6f9; border-left: 4px solid #12355b; font-weight: 600; }
    .signs { display: flex; justify-content: space-between; margin-top: 70px; }
    .signs div { width: 200px; border-top: 1px solid #1d2733; text-align: center; padding-top: 4px; font-size: 11px; }
    .foot { margin-top: 24px; text-align: center; font-size: 10px; color: #8a96a3; }
    .stamp { display: inline-block; padding: 2px 10px; border: 2px solid currentColor; border-radius: 4px; font-weight: 700; letter-spacing: 2px; }
    .toolbar { text-align: center; margin: 12px; }
    .toolbar button { background: #12355b; color: #fff; border: 0; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 13px; }
    @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } .toolbar { display: none; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet" @if(!empty($compact)) style="min-height:0" @endif>
    <div class="head">
        @if($logo)<img src="{{ asset($logo) }}" alt="">@endif
        <div class="company">
            <h1>{{ config('travel.company_name') }}</h1>
            <p>{{ config('travel.company_address') }}</p>
            <p>Phone: {{ config('travel.company_phone') }} · {{ config('travel.company_license_line') }}</p>
        </div>
    </div>
    <div class="title-row">
        <div class="doc-title">{{ $docTitle }}</div>
        @yield('stamp')
    </div>
    <div class="meta">@yield('meta')</div>
    @yield('body')
    <div class="foot">This is a computer-generated document. Printed {{ now()->format('d/m/Y h:i A') }}.</div>
</div>
</body>
</html>
