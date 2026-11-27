{{--
    Vendor Account Statement — PDF body (TCPDF HTML subset). Page frame is
    App\Support\CustomerStatementPdf with the vendor title. Data:
    VendorStatementService::build().
--}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    $bal = fn ($v) => (float) $v < -0.004
        ? '<span style="color:#c0392b; font-weight:bold;">-' . number_format(abs((float) $v), 2) . '</span>'
        : number_format((float) $v, 2);
    $e = fn ($v) => e((string) ($v ?? ''));
    $hasPurchases = $s['tickets']->isNotEmpty() || collect($s['services'])->contains(fn ($rows) => $rows->isNotEmpty());
    $band = 'background-color:#d4d4d4;text-align:center;font-size:10px;';
    $title = 'text-align:center;font-size:12px;border:0.3px solid #999;';
    $th = 'border-bottom:0.3px solid #999;font-size:8px;';
    $td = 'border-bottom:0.2px solid #ccc;';
@endphp

<style>
    table { font-size: 7.5px; }
    td.r, th.r { text-align: right; }
</style>

<table cellpadding="2"><tr><td style="{{ $band }}">Purchase Invoices</td></tr></table>
@if(!$hasPurchases)
<p style="text-align:center;color:#666;">No purchases from this vendor in this period.</p>
@endif

@if($s['tickets']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="10" style="{{ $title }}">Ticket Booking</td></tr>
    <tr>
        <th width="8%" style="{{ $th }}">Date</th><th width="9%" style="{{ $th }}">Invoice</th>
        <th width="12%" style="{{ $th }}">Ticket No.</th><th width="15%" style="{{ $th }}">Passenger</th>
        <th width="10%" style="{{ $th }}">Sector</th><th width="13%" style="{{ $th }}">Customer</th>
        <th width="8%" class="r" style="{{ $th }}">Fare</th><th width="8%" class="r" style="{{ $th }}">Taxes</th>
        <th width="8%" class="r" style="{{ $th }}">Comm</th><th width="9%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($s['tickets'] as $r)
    <tr nobr="true">
        <td width="8%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="9%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="12%" style="{{ $td }}">{{ $e($r['ticket_no']) }}</td>
        <td width="15%" style="{{ $td }}">{{ $e($r['passenger']) }}{{ $r['status'] !== 'active' ? ' (' . strtoupper($r['status']) . ')' : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $e($r['sector']) }}</td>
        <td width="13%" style="{{ $td }}">{{ $e($r['customer']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['fare']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['taxes']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['commission']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="67%" class="r">Total</td>
        <td width="8%" class="r">{{ $n($s['tickets']->sum('fare')) }}</td>
        <td width="8%" class="r">{{ $n($s['tickets']->sum('taxes')) }}</td>
        <td width="8%" class="r">{{ $n($s['tickets']->sum('commission')) }}</td>
        <td width="9%" class="r"><b>{{ $n($s['tickets']->sum('net')) }}</b></td>
    </tr>
</table>
@endif

@foreach(\App\Services\VendorStatementService::SERVICES as $key => $label)
    @php($rows = $s['services'][$key])
    @continue($rows->isEmpty())
<br><br><table cellpadding="1.5">
<thead>
    <tr><td colspan="8" style="{{ $title }}">{{ $label }} Booking</td></tr>
    <tr>
        <th width="8%" style="{{ $th }}">Date</th><th width="9%" style="{{ $th }}">Invoice</th>
        <th width="13%" style="{{ $th }}">Customer</th><th width="12%" style="{{ $th }}">Booking Name</th>
        <th width="31%" style="{{ $th }}">Details</th>
        <th width="9%" class="r" style="{{ $th }}">Payable</th><th width="9%" class="r" style="{{ $th }}">Comm</th>
        <th width="9%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($rows as $r)
    <tr nobr="true">
        <td width="8%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="9%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="13%" style="{{ $td }}">{{ $e($r['customer']) }}</td>
        <td width="12%" style="{{ $td }}">{{ $e($r['booking']) }}</td>
        <td width="31%" style="{{ $td }}">{{ $e($r['details']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['payable']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['charges']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="73%" class="r">Total</td>
        <td width="9%" class="r">{{ $n($rows->sum('payable')) }}</td>
        <td width="9%" class="r">{{ $n($rows->sum('charges')) }}</td>
        <td width="9%" class="r"><b>{{ $n($rows->sum('net')) }}</b></td>
    </tr>
</table>
@endforeach

@if($s['credits']->isNotEmpty())
<br><br><table cellpadding="2"><tr><td style="{{ $band }}">Refund / Void Credits</td></tr></table>
<br><table cellpadding="1.5">
<thead>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="15%" style="{{ $th }}">Credit No.</th>
        <th width="13%" style="{{ $th }}">Ticket No.</th><th width="17%" style="{{ $th }}">Passenger</th>
        <th width="16%" style="{{ $th }}">Customer</th>
        <th width="10%" class="r" style="{{ $th }}">Original</th><th width="10%" class="r" style="{{ $th }}">Kept</th>
        <th width="10%" class="r" style="{{ $th }}">Net Credit</th>
    </tr>
</thead>
    @foreach($s['credits'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['date_label'] }}</td>
        <td width="15%" style="{{ $td }}">{{ $e($r['invoice']) }}</td>
        <td width="13%" style="{{ $td }}">{{ $e($r['ticket_no']) }}</td>
        <td width="17%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="16%" style="{{ $td }}">{{ $e($r['customer']) }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['original']) }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['kept']) }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="70%" class="r">Total</td>
        <td width="10%" class="r">{{ $n($s['credits']->sum('original')) }}</td>
        <td width="10%" class="r">{{ $n($s['credits']->sum('kept')) }}</td>
        <td width="10%" class="r"><b>{{ $n($s['credits']->sum('net')) }}</b></td>
    </tr>
</table>
@endif

@if($s['vouchers']->isNotEmpty())
<br><br><table cellpadding="2"><tr><td style="{{ $band }}">Vouchers</td></tr></table>
<br><table cellpadding="1.5">
<thead>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="11%" style="{{ $th }}">Voucher No.</th>
        <th width="14%" style="{{ $th }}">Reference</th><th width="30%" style="{{ $th }}">Description</th>
        <th width="12%" style="{{ $th }}">Cheque No</th>
        <th width="12%" class="r" style="{{ $th }}">Debit (Paid)</th><th width="12%" class="r" style="{{ $th }}">Credit</th>
    </tr>
</thead>
    @foreach($s['vouchers'] as $v)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $v['date']->format('d/m/Y') }}</td>
        <td width="11%" style="{{ $td }}">{{ $v['voucher_no'] }}</td>
        <td width="14%" style="{{ $td }}">{{ $e($v['reference']) }}</td>
        <td width="30%" style="{{ $td }}">{{ $e($v['description']) }}</td>
        <td width="12%" style="{{ $td }}">{{ $e($v['cheque_no']) }}</td>
        <td width="12%" class="r" style="{{ $td }}">{{ $n($v['debit']) }}</td>
        <td width="12%" class="r" style="{{ $td }}">{{ $n($v['credit']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="76%" class="r">Total</td>
        <td width="12%" class="r"><b>{{ $n($s['vouchers']->sum('debit')) }}</b></td>
        <td width="12%" class="r"><b>{{ $n($s['vouchers']->sum('credit')) }}</b></td>
    </tr>
</table>
@endif

<br><br>
<table cellpadding="2.5" nobr="true">
    <tr><td width="22%"></td><td width="28%">Opening Balance B/F</td><td width="28%" class="r">{!! $bal($s['summary']['opening']) !!}</td><td width="22%"></td></tr>
    <tr><td></td><td>Add Purchase Invoices</td><td class="r">{{ $n($s['summary']['purchases']) }}</td><td></td></tr>
    <tr><td></td><td>Less Refund / Void Credits</td><td class="r">{{ $n($s['summary']['credits']) }}</td><td></td></tr>
    <tr><td></td><td>Less Payments</td><td class="r">{{ $n($s['summary']['payments']) }}</td><td></td></tr>
    <tr><td></td><td>Add Other Credits</td><td class="r">{{ $n($s['summary']['other_credits']) }}</td><td></td></tr>
    <tr>
        <td></td>
        <td style="border:0.3px solid #999;"><b>Net Payable</b></td>
        <td class="r" style="border:0.3px solid #999;"><b>{!! $bal($s['summary']['net']) !!}</b></td>
        <td></td>
    </tr>
</table>
