{{--
    Customer Account Statement — PDF body (TCPDF HTML subset: tables,
    widths in %, bgcolor, simple borders). Page frame/header/footer is
    App\Support\CustomerStatementPdf. Data: CustomerStatementService::build().
--}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    // Green = we receive, red with minus = we pay (client rule, Nov 2026).
    $bal = fn ($v) => \App\Support\Money::receivable($v);
    $rec = fn ($v) => \App\Support\Money::receivable($v);
    $pay = fn ($v) => \App\Support\Money::payable($v);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '';
    $e = fn ($v) => e((string) ($v ?? ''));
    $sec = $s['sections'];
    $hasSales = collect($sec)->contains(fn ($rows) => $rows->isNotEmpty());
    $band = 'background-color:#d4d4d4;text-align:center;font-size:10px;';
    $title = 'text-align:center;font-size:12px;border:0.3px solid #999;';
    $th = 'border-bottom:0.3px solid #999;font-size:8px;';
    $td = 'border-bottom:0.2px solid #ccc;';
@endphp

<style>
    table { font-size: 7.5px; }
    td.r, th.r { text-align: right; }
    td.c, th.c { text-align: center; }
</style>

{{-- ======================= SALE INVOICES ======================= --}}
<table cellpadding="2"><tr><td style="{{ $band }}">Sale Invoices</td></tr></table>
@if(!$hasSales)
<p style="text-align:center;color:#666;">No sale invoices in this period.</p>
@endif

@if($sec['ticket']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="10" style="{{ $title }}">Ticket Booking</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="10%" style="{{ $th }}">Invoice</th>
        <th width="13%" style="{{ $th }}">Ticket No.</th><th width="25%" style="{{ $th }}">Passenger</th>
        <th width="13%" style="{{ $th }}">Sector</th>
        <th width="8%" class="r" style="{{ $th }}">Fare</th><th width="8%" class="r" style="{{ $th }}">Taxes</th>
        <th width="5%" class="r" style="{{ $th }}">KB</th>
        <th width="9%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($sec['ticket'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="13%" style="{{ $td }}">{{ $e($r['ticket_no']) }}</td>
        <td width="25%" style="{{ $td }}">{{ $e($r['passenger']) }}{{ $r['voided'] ? ' (VOID)' : '' }}</td>
        <td width="13%" style="{{ $td }}">{{ $e($r['sector']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['fare']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['taxes']) }}</td>
        <td width="5%" class="r" style="{{ $td }}">{{ $n($r['kb']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="70%" class="r" colspan="5">Total</td>
        <td width="8%" class="r">{{ $n($sec['ticket']->sum('fare')) }}</td>
        <td width="8%" class="r">{{ $n($sec['ticket']->sum('taxes')) }}</td>
        <td width="5%" class="r">{{ $n($sec['ticket']->sum('kb')) }}</td>
        <td width="9%" class="r"><b>{!! $rec($sec['ticket']->sum('net')) !!}</b></td>
    </tr>
</table>
<table cellpadding="1.5"><tr>
    <td width="52%"></td>
    <td width="8%">Sale</td><td width="6%">{{ $s['ticket_counts']['sale'] }}</td>
    <td width="8%">Void</td><td width="6%">{{ $s['ticket_counts']['void'] }}</td>
    <td width="10%">Total</td><td width="10%">{{ $s['ticket_counts']['total'] }}</td>
</tr></table>
@endif

@if($sec['hotel']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="9" style="{{ $title }}">Hotel Booking</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="10%" style="{{ $th }}">Invoice</th>
        <th width="14%" style="{{ $th }}">Hotel</th><th width="17%" style="{{ $th }}">Passenger</th>
        <th width="9%" style="{{ $th }}">Chk-in</th><th width="9%" style="{{ $th }}">Chk-out</th>
        <th width="10%" style="{{ $th }}">Room Type</th>
        <th width="6%" class="r" style="{{ $th }}">Rooms</th><th width="6%" class="r" style="{{ $th }}">Nights</th>
        <th width="10%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($sec['hotel'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="14%" style="{{ $td }}">{{ $e($r['hotel']) }}</td>
        <td width="17%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="9%" style="{{ $td }}">{{ $d($r['check_in']) }}</td>
        <td width="9%" style="{{ $td }}">{{ $d($r['check_out']) }}</td>
        <td width="10%" style="{{ $td }}">{{ $e($r['room_type']) }}</td>
        <td width="6%" class="r" style="{{ $td }}">{{ $r['rooms'] }}</td>
        <td width="6%" class="r" style="{{ $td }}">{{ $r['nights'] }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true"><td width="90%" class="r">Total</td><td width="10%" class="r"><b>{!! $rec($sec['hotel']->sum('net')) !!}</b></td></tr>
</table>
<table cellpadding="1.5"><tr><td width="80%"></td><td width="10%">Total</td><td width="10%">{{ $sec['hotel']->count() }}</td></tr></table>
@endif

@if($sec['transport']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="8" style="{{ $title }}">Transport Booking</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="10%" style="{{ $th }}">Invoice</th>
        <th width="18%" style="{{ $th }}">Passenger</th><th width="15%" style="{{ $th }}">Vehicle</th>
        <th width="16%" style="{{ $th }}">Sector</th><th width="9%" style="{{ $th }}">Reference</th>
        <th width="14%" style="{{ $th }}">Remarks</th><th width="9%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($sec['transport'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="18%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="15%" style="{{ $td }}">{{ $e($r['vehicle']) }}</td>
        <td width="16%" style="{{ $td }}">{{ $e($r['sector']) }}</td>
        <td width="9%" style="{{ $td }}">{{ $e($r['reference']) }}</td>
        <td width="14%" style="{{ $td }}">{{ $e($r['remarks']) }}</td>
        <td width="9%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true"><td width="91%" class="r">Total</td><td width="9%" class="r"><b>{!! $rec($sec['transport']->sum('net')) !!}</b></td></tr>
</table>
<table cellpadding="1.5"><tr><td width="80%"></td><td width="10%">Total</td><td width="10%">{{ $sec['transport']->count() }}</td></tr></table>
@endif

@if($sec['visa']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="11" style="{{ $title }}">Visa Booking</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="10%" style="{{ $th }}">Invoice</th>
        <th width="21%" style="{{ $th }}">Passenger</th>
        <th width="4%" class="c" style="{{ $th }}">A</th><th width="4%" class="c" style="{{ $th }}">C</th><th width="4%" class="c" style="{{ $th }}">I</th>
        <th width="12%" style="{{ $th }}">PP No.</th><th width="9%" style="{{ $th }}">Visa Type</th>
        <th width="9%" style="{{ $th }}">Visa Apply Date</th><th width="8%" style="{{ $th }}">Visa Expiry</th>
        <th width="10%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($sec['visa'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="21%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="4%" class="c" style="{{ $td }}">{{ $r['adults'] }}</td>
        <td width="4%" class="c" style="{{ $td }}">{{ $r['children'] }}</td>
        <td width="4%" class="c" style="{{ $td }}">{{ $r['infants'] }}</td>
        <td width="12%" style="{{ $td }}">{{ $e($r['passport']) }}</td>
        <td width="9%" style="{{ $td }}">{{ $e($r['visa_type']) }}</td>
        <td width="9%" style="{{ $td }}">{{ $d($r['apply_date']) }}</td>
        <td width="8%" style="{{ $td }}">{{ $d($r['expiry_date']) }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="40%" class="r">Total</td>
        <td width="4%" class="c">{{ $sec['visa']->sum('adults') }}</td>
        <td width="4%" class="c">{{ $sec['visa']->sum('children') }}</td>
        <td width="4%" class="c">{{ $sec['visa']->sum('infants') }}</td>
        <td width="48%" class="r"><b>{!! $rec($sec['visa']->sum('net')) !!}</b></td>
    </tr>
</table>
@endif

@if($sec['other']->isNotEmpty())
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="8" style="{{ $title }}">Other Services</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="10%" style="{{ $th }}">Invoice</th>
        <th width="16%" style="{{ $th }}">Service</th><th width="18%" style="{{ $th }}">Passenger</th>
        <th width="10%" style="{{ $th }}">Travel Date</th><th width="5%" class="r" style="{{ $th }}">Qty</th>
        <th width="22%" style="{{ $th }}">Remarks</th><th width="10%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($sec['other'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
        <td width="10%" style="{{ $td }}">{{ $r['show_invoice'] ? $e($r['invoice']) : '' }}</td>
        <td width="16%" style="{{ $td }}">{{ $e($r['service']) }}</td>
        <td width="18%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="10%" style="{{ $td }}">{{ $d($r['travel_date']) }}</td>
        <td width="5%" class="r" style="{{ $td }}">{{ $r['qty'] }}</td>
        <td width="22%" style="{{ $td }}">{{ $e($r['remarks']) }}</td>
        <td width="10%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true"><td width="90%" class="r">Total</td><td width="10%" class="r"><b>{!! $rec($sec['other']->sum('net')) !!}</b></td></tr>
</table>
@endif

{{-- ======================= REFUND INVOICES ======================= --}}
@if($s['refunds']->isNotEmpty())
<br><br><table cellpadding="2"><tr><td style="{{ $band }}">Refund Invoices</td></tr></table>
<br><table cellpadding="1.5">
<thead>
    <tr><td colspan="11" style="{{ $title }}">Ticket Booking</td></tr>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="15%" style="{{ $th }}">Invoice</th>
        <th width="12%" style="{{ $th }}">Ticket No.</th><th width="20%" style="{{ $th }}">Passenger</th>
        <th width="10%" style="{{ $th }}">Sector</th>
        <th width="7%" class="r" style="{{ $th }}">Fare</th><th width="7%" class="r" style="{{ $th }}">Taxes</th>
        <th width="5%" class="r" style="{{ $th }}">KB</th>
        <th width="7%" class="r" style="{{ $th }}">CC</th><th width="8%" class="r" style="{{ $th }}">Net</th>
    </tr>
</thead>
    @foreach($s['refunds'] as $r)
    <tr nobr="true">
        <td width="9%" style="{{ $td }}">{{ $r['date_label'] }}</td>
        <td width="15%" style="{{ $td }}">{{ $e($r['invoice']) }}</td>
        <td width="12%" style="{{ $td }}">{{ $e($r['ticket_no']) }}</td>
        <td width="20%" style="{{ $td }}">{{ $e($r['passenger']) }}</td>
        <td width="10%" style="{{ $td }}">{{ $e($r['sector']) }}</td>
        <td width="7%" class="r" style="{{ $td }}">{{ $n($r['fare']) }}</td>
        <td width="7%" class="r" style="{{ $td }}">{{ $n($r['taxes']) }}</td>
        <td width="5%" class="r" style="{{ $td }}">{{ $n($r['kb']) }}</td>
        <td width="7%" class="r" style="{{ $td }}">{{ $n($r['cc']) }}</td>
        <td width="8%" class="r" style="{{ $td }}">{{ $n($r['net']) }}</td>
    </tr>
    @endforeach
    <tr nobr="true">
        <td width="66%" class="r">Total</td>
        <td width="7%" class="r">{{ $n($s['refunds']->sum('fare')) }}</td>
        <td width="7%" class="r">{{ $n($s['refunds']->sum('taxes')) }}</td>
        <td width="5%" class="r">{{ $n($s['refunds']->sum('kb')) }}</td>
        <td width="7%" class="r">{{ $n($s['refunds']->sum('cc')) }}</td>
        <td width="8%" class="r"><b>{!! $pay($s['refunds']->sum('net')) !!}</b></td>
    </tr>
</table>
<table cellpadding="1.5"><tr>
    <td width="52%"></td>
    <td width="8%">Refund</td><td width="6%">{{ $s['refund_counts']['refund'] }}</td>
    <td width="8%">Void</td><td width="6%">{{ $s['refund_counts']['void'] }}</td>
    <td width="10%">Total</td><td width="10%">{{ $s['refund_counts']['total'] }}</td>
</tr></table>
@endif

{{-- ======================= VOUCHERS ======================= --}}
@if($s['vouchers']->isNotEmpty())
<br><br><table cellpadding="2"><tr><td style="{{ $band }}">Vouchers</td></tr></table>
<br><table cellpadding="1.5">
<thead>
    <tr>
        <th width="9%" style="{{ $th }}">Date</th><th width="11%" style="{{ $th }}">Voucher No.</th>
        <th width="14%" style="{{ $th }}">Reference</th><th width="30%" style="{{ $th }}">Description</th>
        <th width="12%" style="{{ $th }}">Cheque No</th>
        <th width="12%" class="r" style="{{ $th }}">Debit</th><th width="12%" class="r" style="{{ $th }}">Credit</th>
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

{{-- ======================= CLOSING SUMMARY ======================= --}}
<br><br>
<table cellpadding="2.5" nobr="true">
    <tr><td width="22%"></td><td width="28%">Opening Balance B/F</td><td width="28%" class="r">{!! $bal($s['summary']['opening']) !!}</td><td width="22%"></td></tr>
    <tr><td></td><td>Add Sale Invoices</td><td class="r">{{ $n($s['summary']['sales']) }}</td><td></td></tr>
    <tr><td></td><td>Less Refund Invoices</td><td class="r">{{ $n($s['summary']['refunds']) }}</td><td></td></tr>
    <tr><td></td><td>Less Receipts</td><td class="r">{{ $n($s['summary']['receipts']) }}</td><td></td></tr>
    <tr><td></td><td>Add Payments</td><td class="r">{{ $n($s['summary']['payments']) }}</td><td></td></tr>
    <tr>
        <td></td>
        <td style="border:0.3px solid #999;"><b>Net Balance</b></td>
        <td class="r" style="border:0.3px solid #999;"><b>{!! $bal($s['summary']['net']) !!}</b></td>
        <td></td>
    </tr>
</table>
