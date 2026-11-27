{{--
    Accounts Reports → Customer Statement tab. Same sections, columns and
    totals as the PDF (reports/customer_statement_pdf), in the app's own UI.
    Data: CustomerStatementService::build() ($statement, null until a
    customer is picked).
--}}
@php
    $n = fn ($v) => number_format((float) $v, 2);
    // Balances below zero print with a minus sign in red (client fix, Nov 2026).
    $bal = fn ($v) => (float) $v < -0.004
        ? '<span style="color:#c0392b; font-weight:bold;">-' . number_format(abs((float) $v), 2) . '</span>'
        : number_format((float) $v, 2);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '';
    $csFrom = request('from_date', $from);
    $csTo = request('to_date', $to);
@endphp

<style>
    #customer_statement .cs-band { background: #d4d4d4; text-align: center; font-weight: 600; padding: 6px; margin: 18px 0 8px; }
    #customer_statement .cs-title { text-align: center; font-size: 1.05rem; border: 1px solid #ccc; border-bottom: 0; padding: 4px; margin-top: 12px; }
    #customer_statement .cs-table td, #customer_statement .cs-table th { padding: 3px 6px; font-size: 12.5px; }
    #customer_statement .cs-table tfoot td { font-weight: 600; background: #f3f4f6; }
    #customer_statement .cs-counts { text-align: right; font-size: 12.5px; margin: 2px 0 6px; }
    #customer_statement .cs-counts span { margin-left: 18px; }
    #customer_statement .cs-summary { max-width: 460px; margin: 22px auto 6px; }
    #customer_statement .cs-summary td { padding: 4px 10px; }
</style>

<div class="tab-pane fade" id="customer_statement" role="tabpanel">

    <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3 no-print">
        <input type="hidden" name="tab" value="customer_statement">
        <div class="col-md-2">
            <input type="date" name="from_date" value="{{ $csFrom }}" class="form-control" required>
        </div>
        <div class="col-md-2">
            <input type="date" name="to_date" value="{{ $csTo }}" class="form-control" required>
        </div>
        <div class="col-md-4">
            <select name="customer_id" class="form-control select2-js" data-plugin-selecttwo required>
                <option value="">-- Select Customer --</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}{{ $c->code ? ' (' . $c->code . ')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter"></i> Show Statement</button>
        </div>
        <div class="col-md-2">
            @if ($statement)
            <a class="btn btn-danger w-100" target="_blank"
               href="{{ route('reports.customer_statement_pdf', ['customer_id' => $statement['customer']->id, 'from_date' => $statement['from'], 'to_date' => $statement['to']]) }}">
                <i class="fas fa-file-pdf"></i> Print PDF
            </a>
            @else
            <button type="button" class="btn btn-danger w-100" disabled title="Pick a customer first"><i class="fas fa-file-pdf"></i> Print PDF</button>
            @endif
        </div>
    </form>

    @if (!$statement)
        <div class="alert alert-info">Pick a date range and a customer, then click <strong>Show Statement</strong>.</div>
    @else
        @php($s = $statement)
        @php($sec = $s['sections'])
        @php($hasSales = collect($sec)->contains(fn ($rows) => $rows->isNotEmpty()))

        <div class="d-flex justify-content-between flex-wrap border rounded p-2 mb-2">
            <div>
                <div class="fw-bold fs-6">{{ $s['customer']->name }}</div>
                <div class="small">Phone: {{ $s['customer']->phone }}</div>
                <div class="small">Address: {{ $s['customer']->address }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold">Customer Account Statement</div>
                <div class="small">From: {{ $d($s['from']) }} To: {{ $d($s['to']) }}</div>
            </div>
        </div>

        {{-- ================= SALE INVOICES ================= --}}
        <div class="cs-band">Sale Invoices</div>
        @if (!$hasSales)
            <p class="text-muted text-center">No sale invoices in this period.</p>
        @endif

        @if ($sec['ticket']->isNotEmpty())
            <div class="cs-title">Ticket Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Ticket No.</th><th>Passenger</th><th>Sector</th>
                    <th class="text-end">Fare</th><th class="text-end">Taxes</th><th class="text-end">KB</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($sec['ticket'] as $r)
                    <tr class="{{ $r['voided'] ? 'text-muted' : '' }}">
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['ticket_no'] }}</td>
                        <td>{{ $r['passenger'] }} @if ($r['voided'])<span class="badge bg-secondary">VOID</span>@endif</td>
                        <td>{{ $r['sector'] }}</td>
                        <td class="text-end">{{ $n($r['fare']) }}</td>
                        <td class="text-end">{{ $n($r['taxes']) }}</td>
                        <td class="text-end">{{ $n($r['kb']) }}</td>
                        <td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end">{{ $n($sec['ticket']->sum('fare')) }}</td>
                    <td class="text-end">{{ $n($sec['ticket']->sum('taxes')) }}</td>
                    <td class="text-end">{{ $n($sec['ticket']->sum('kb')) }}</td>
                    <td class="text-end">{{ $n($sec['ticket']->sum('net')) }}</td>
                </tr></tfoot>
            </table>
            </div>
            <div class="cs-counts">
                <span>Sale <strong>{{ $s['ticket_counts']['sale'] }}</strong></span>
                <span>Void <strong>{{ $s['ticket_counts']['void'] }}</strong></span>
                <span>Total <strong>{{ $s['ticket_counts']['total'] }}</strong></span>
            </div>
        @endif

        @if ($sec['hotel']->isNotEmpty())
            <div class="cs-title">Hotel Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Hotel</th><th>Passenger</th><th>Chk-in</th><th>Chk-out</th><th>Room Type</th>
                    <th class="text-end">Rooms</th><th class="text-end">Nights</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($sec['hotel'] as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['hotel'] }}</td><td>{{ $r['passenger'] }}</td>
                        <td>{{ $d($r['check_in']) }}</td><td>{{ $d($r['check_out']) }}</td>
                        <td>{{ $r['room_type'] }}</td>
                        <td class="text-end">{{ $r['rooms'] }}</td><td class="text-end">{{ $r['nights'] }}</td>
                        <td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="9" class="text-end">Total</td><td class="text-end">{{ $n($sec['hotel']->sum('net')) }}</td></tr></tfoot>
            </table>
            </div>
            <div class="cs-counts"><span>Total <strong>{{ $sec['hotel']->count() }}</strong></span></div>
        @endif

        @if ($sec['transport']->isNotEmpty())
            <div class="cs-title">Transport Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Passenger</th><th>Vehicle</th><th>Sector</th><th>Reference</th><th>Remarks</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($sec['transport'] as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['passenger'] }}</td><td>{{ $r['vehicle'] }}</td><td>{{ $r['sector'] }}</td>
                        <td>{{ $r['reference'] }}</td><td>{{ $r['remarks'] }}</td>
                        <td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="7" class="text-end">Total</td><td class="text-end">{{ $n($sec['transport']->sum('net')) }}</td></tr></tfoot>
            </table>
            </div>
            <div class="cs-counts"><span>Total <strong>{{ $sec['transport']->count() }}</strong></span></div>
        @endif

        @if ($sec['visa']->isNotEmpty())
            <div class="cs-title">Visa Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Passenger</th><th class="text-center">A</th><th class="text-center">C</th><th class="text-center">I</th>
                    <th>PP No.</th><th>Visa Type</th><th>Visa Apply Date</th><th>Visa Expiry</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($sec['visa'] as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['passenger'] }}</td>
                        <td class="text-center">{{ $r['adults'] }}</td><td class="text-center">{{ $r['children'] }}</td><td class="text-center">{{ $r['infants'] }}</td>
                        <td>{{ $r['passport'] }}</td><td>{{ $r['visa_type'] }}</td>
                        <td>{{ $d($r['apply_date']) }}</td><td>{{ $d($r['expiry_date']) }}</td>
                        <td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="3" class="text-end">Total</td>
                    <td class="text-center">{{ $sec['visa']->sum('adults') }}</td><td class="text-center">{{ $sec['visa']->sum('children') }}</td><td class="text-center">{{ $sec['visa']->sum('infants') }}</td>
                    <td colspan="4"></td><td class="text-end">{{ $n($sec['visa']->sum('net')) }}</td>
                </tr></tfoot>
            </table>
            </div>
        @endif

        @if ($sec['other']->isNotEmpty())
            <div class="cs-title">Other Services</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Service</th><th>Passenger</th><th>Travel Date</th><th class="text-end">Qty</th><th>Remarks</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($sec['other'] as $r)
                    <tr>
                        <td>{{ $r['show_date'] ? $r['date_label'] : '' }}</td>
                        <td>{{ $r['show_invoice'] ? $r['invoice'] : '' }}</td>
                        <td>{{ $r['service'] }}</td><td>{{ $r['passenger'] }}</td><td>{{ $d($r['travel_date']) }}</td>
                        <td class="text-end">{{ $r['qty'] }}</td><td>{{ $r['remarks'] }}</td>
                        <td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="7" class="text-end">Total</td><td class="text-end">{{ $n($sec['other']->sum('net')) }}</td></tr></tfoot>
            </table>
            </div>
        @endif

        {{-- ================= REFUND INVOICES ================= --}}
        @if ($s['refunds']->isNotEmpty())
            <div class="cs-band">Refund Invoices</div>
            <div class="cs-title">Ticket Booking</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice</th><th>Ticket No.</th><th>Passenger</th><th>Sector</th>
                    <th class="text-end">Fare</th><th class="text-end">Taxes</th><th class="text-end">KB</th><th class="text-end">CC</th><th class="text-end">Net</th>
                </tr></thead>
                <tbody>
                @foreach ($s['refunds'] as $r)
                    <tr>
                        <td>{{ $r['date_label'] }}</td><td>{{ $r['invoice'] }}</td><td>{{ $r['ticket_no'] }}</td>
                        <td>{{ $r['passenger'] }} <span class="badge {{ $r['kind'] === 'void' ? 'bg-secondary' : 'bg-info' }}">{{ strtoupper($r['kind']) }}</span></td>
                        <td>{{ $r['sector'] }}</td>
                        <td class="text-end">{{ $n($r['fare']) }}</td><td class="text-end">{{ $n($r['taxes']) }}</td>
                        <td class="text-end">{{ $n($r['kb']) }}</td>
                        <td class="text-end">{{ $n($r['cc']) }}</td><td class="text-end">{{ $n($r['net']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end">{{ $n($s['refunds']->sum('fare')) }}</td><td class="text-end">{{ $n($s['refunds']->sum('taxes')) }}</td>
                    <td class="text-end">{{ $n($s['refunds']->sum('kb')) }}</td>
                    <td class="text-end">{{ $n($s['refunds']->sum('cc')) }}</td><td class="text-end">{{ $n($s['refunds']->sum('net')) }}</td>
                </tr></tfoot>
            </table>
            </div>
            <div class="cs-counts">
                <span>Refund <strong>{{ $s['refund_counts']['refund'] }}</strong></span>
                <span>Void <strong>{{ $s['refund_counts']['void'] }}</strong></span>
                <span>Total <strong>{{ $s['refund_counts']['total'] }}</strong></span>
            </div>
        @endif

        {{-- ================= VOUCHERS ================= --}}
        @if ($s['vouchers']->isNotEmpty())
            <div class="cs-band">Vouchers</div>
            <div class="table-responsive">
            <table class="table table-bordered table-sm cs-table mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Voucher No.</th><th>Reference</th><th>Description</th><th>Cheque No</th>
                    <th class="text-end">Debit</th><th class="text-end">Credit</th>
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
            <tr><td>Add Sale Invoices</td><td class="text-end">{{ $n($s['summary']['sales']) }}</td></tr>
            <tr><td>Less Refund Invoices</td><td class="text-end">{{ $n($s['summary']['refunds']) }}</td></tr>
            <tr><td>Less Receipts</td><td class="text-end">{{ $n($s['summary']['receipts']) }}</td></tr>
            <tr><td>Add Payments</td><td class="text-end">{{ $n($s['summary']['payments']) }}</td></tr>
            <tr class="fw-bold border"><td class="border">Net Balance</td><td class="text-end border">{!! $bal($s['summary']['net']) !!}</td></tr>
        </table>
    @endif
</div>
