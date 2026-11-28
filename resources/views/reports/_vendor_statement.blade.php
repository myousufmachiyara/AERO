{{--
    Accounts Reports → Vendor Statement tab: the supplier side of the
    Customer Statement — same layout, same sections and totals as the PDF
    (reports/vendor_statement_pdf). Data: VendorStatementService::build()
    ($vendorStatement, null until a supplier is picked).
--}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    // Green = we receive, red with minus = we pay (client rule, Nov 2026).
    $bal = fn ($v) => \App\Support\Money::payable($v);
    $rec = fn ($v) => \App\Support\Money::receivable($v);
    $pay = fn ($v) => \App\Support\Money::payable($v);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '';
    $vsFrom = request('from_date', $from);
    $vsTo = request('to_date', $to);
@endphp

<style>
    #vendor_statement .cs-band { background: #d4d4d4; text-align: center; font-weight: 600; padding: 6px; margin: 18px 0 8px; }
    #vendor_statement .cs-title { text-align: center; font-size: 1.05rem; border: 1px solid #ccc; border-bottom: 0; padding: 4px; margin-top: 12px; }
    #vendor_statement .cs-table td, #vendor_statement .cs-table th { padding: 3px 6px; font-size: 12.5px; }
    #vendor_statement .cs-table tfoot td { font-weight: 600; background: #f3f4f6; }
    #vendor_statement .cs-summary { max-width: 460px; margin: 22px auto 6px; }
    #vendor_statement .cs-summary td { padding: 4px 10px; }
</style>

<div class="tab-pane fade" id="vendor_statement" role="tabpanel">

    <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3 no-print">
        <input type="hidden" name="tab" value="vendor_statement">
        <div class="col-md-2"><input type="date" name="from_date" value="{{ $vsFrom }}" class="form-control" required></div>
        <div class="col-md-2"><input type="date" name="to_date" value="{{ $vsTo }}" class="form-control" required></div>
        <div class="col-md-4">
            <select name="supplier_id" class="form-control select2-js" data-plugin-selecttwo required>
                <option value="">-- Select Vendor / Supplier --</option>
                @foreach ($suppliers as $sup)
                    <option value="{{ $sup->id }}" @selected(request('supplier_id') == $sup->id)>{{ $sup->name }}{{ $sup->code ? ' (' . $sup->code . ')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i> Show Statement</button></div>
        <div class="col-md-2">
            @if ($vendorStatement)
            <a class="btn btn-danger w-100" target="_blank"
               href="{{ route('reports.vendor_statement_pdf', ['supplier_id' => $vendorStatement['supplier']->id, 'from_date' => $vendorStatement['from'], 'to_date' => $vendorStatement['to']]) }}">
                <i class="fas fa-file-pdf"></i> Print PDF
            </a>
            @else
            <button type="button" class="btn btn-danger w-100" disabled title="Pick a supplier first"><i class="fas fa-file-pdf"></i> Print PDF</button>
            @endif
        </div>
    </form>

    @if (!$vendorStatement)
        <div class="alert alert-info">Pick a date range and a vendor, then click <strong>Show Statement</strong>.</div>
    @else
        @php($s = $vendorStatement)
        @php($hasPurchases = $s['tickets']->isNotEmpty() || collect($s['services'])->contains(fn ($rows) => $rows->isNotEmpty()))

        <div class="d-flex justify-content-between flex-wrap border rounded p-2 mb-2">
            <div>
                <div class="fw-bold fs-6">{{ $s['supplier']->name }}</div>
                <div class="small">Phone: {{ $s['supplier']->phone }}</div>
                <div class="small">Address: {{ $s['supplier']->address }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold">Vendor Account Statement</div>
                <div class="small">From: {{ $d($s['from']) }} To: {{ $d($s['to']) }}</div>
            </div>
        </div>

        {{-- ================= PURCHASES ================= --}}
        <div class="cs-band">Purchase Invoices</div>
        @if (!$hasPurchases)
            <p class="text-muted text-center">No purchases from this vendor in this period.</p>
        @endif

        @if ($s['tickets']->isNotEmpty())
            <div class="cs-title">Ticket Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Ticket No.</th><th>Passenger</th><th>Sector</th><th>Customer</th>
                    <th class="text-end">Fare</th><th class="text-end">Taxes</th><th class="text-end">Comm (net)</th><th class="text-end">Net Payable</th>
                </tr></thead>
                <tbody>
                @foreach ($s['tickets'] as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['ticket_no'] }}</td>
                        <td>{{ $r['passenger'] }}{{ $r['status'] !== 'active' ? ' (' . strtoupper($r['status']) . ')' : '' }}</td>
                        <td>{{ $r['sector'] }}</td><td>{{ $r['customer'] }}</td>
                        <td class="text-end">{{ $n($r['fare']) }}</td><td class="text-end">{{ $n($r['taxes']) }}</td>
                        <td class="text-end">{{ $n($r['commission']) }}</td><td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="6" class="text-end">Total</td>
                    <td class="text-end">{{ $n($s['tickets']->sum('fare')) }}</td><td class="text-end">{{ $n($s['tickets']->sum('taxes')) }}</td>
                    <td class="text-end">{{ $n($s['tickets']->sum('commission')) }}</td><td class="text-end">{!! $pay($s['tickets']->sum('net')) !!}</td>
                </tr></tfoot>
            </table>
            </div>
        @endif

        @foreach (\App\Services\VendorStatementService::SERVICES as $key => $label)
            @php($rows = $s['services'][$key])
            @continue($rows->isEmpty())
            <div class="cs-title">{{ $label }} Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Customer</th><th>Booking Name</th><th>Details</th>
                    <th class="text-end">Payable</th><th class="text-end">Comm / Charges</th><th class="text-end">Net Payable</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['customer'] }}</td><td>{{ $r['booking'] }}</td><td>{{ $r['details'] }}</td>
                        <td class="text-end">{{ $n($r['payable']) }}</td><td class="text-end">{{ $n($r['charges']) }}</td><td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end">{{ $n($rows->sum('payable')) }}</td><td class="text-end">{{ $n($rows->sum('charges')) }}</td><td class="text-end">{!! $pay($rows->sum('net')) !!}</td>
                </tr></tfoot>
            </table>
            </div>
        @endforeach

        {{-- ================= CREDITS ================= --}}
        @if ($s['credits']->isNotEmpty())
            <div class="cs-band">Refund / Void Credits</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Credit No.</th><th>Ticket No.</th><th>Passenger</th><th>Customer</th>
                    <th class="text-end">Original Payable</th><th class="text-end">Charges Kept</th><th class="text-end">Net Credit</th>
                </tr></thead>
                <tbody>
                @foreach ($s['credits'] as $r)
                    <tr>
                        <td>{{ $r['date_label'] }}</td><td>{{ $r['invoice'] }}</td><td>{{ $r['ticket_no'] }}</td>
                        <td>{{ $r['passenger'] }}</td><td>{{ $r['customer'] }}</td>
                        <td class="text-end">{{ $n($r['original']) }}</td><td class="text-end">{{ $n($r['kept']) }}</td><td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end">{{ $n($s['credits']->sum('original')) }}</td><td class="text-end">{{ $n($s['credits']->sum('kept')) }}</td><td class="text-end">{!! $rec($s['credits']->sum('net')) !!}</td>
                </tr></tfoot>
            </table>
            </div>
        @endif

        {{-- ================= VOUCHERS ================= --}}
        @if ($s['vouchers']->isNotEmpty())
            <div class="cs-band">Vouchers</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Voucher No.</th><th>Reference</th><th>Description</th><th>Cheque No</th>
                    <th class="text-end">Debit (Paid)</th><th class="text-end">Credit</th>
                </tr></thead>
                <tbody>
                @foreach ($s['vouchers'] as $v)
                    <tr>
                        <td>{{ $v['date']->format('d/m/Y') }}</td><td>{{ $v['voucher_no'] }}</td><td>{{ $v['reference'] }}</td>
                        <td>{{ $v['description'] }}</td><td>{{ $v['cheque_no'] }}</td>
                        <td class="text-end">{{ $n($v['debit']) }}</td><td class="text-end">{{ $n($v['credit']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end">{{ $n($s['vouchers']->sum('debit')) }}</td><td class="text-end">{{ $n($s['vouchers']->sum('credit')) }}</td>
                </tr></tfoot>
            </table>
            </div>
        @endif

        {{-- ================= CLOSING SUMMARY ================= --}}
        <table class="table table-sm cs-summary">
            <tr><td>Opening Balance B/F</td><td class="text-end">{!! $bal($s['summary']['opening']) !!}</td></tr>
            <tr><td>Add Purchase Invoices</td><td class="text-end">{{ $n($s['summary']['purchases']) }}</td></tr>
            <tr><td>Less Refund / Void Credits</td><td class="text-end">{{ $n($s['summary']['credits']) }}</td></tr>
            <tr><td>Less Payments</td><td class="text-end">{{ $n($s['summary']['payments']) }}</td></tr>
            <tr><td>Add Other Credits</td><td class="text-end">{{ $n($s['summary']['other_credits']) }}</td></tr>
            <tr class="fw-bold border"><td class="border">Net Payable</td><td class="text-end border">{!! $bal($s['summary']['net']) !!}</td></tr>
        </table>
    @endif
</div>
