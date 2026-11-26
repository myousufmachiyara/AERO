@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $u = auth()->user();
    $canMoney = $u->can('reports.travel_sales') || $u->can('reports.accounts');
    $pkr = fn ($v) => number_format((float) $v, 0);
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $statusBadge = [
        'pending' => 'bg-warning text-dark', 'posted' => 'bg-success',
        'draft' => 'bg-secondary', 'confirmed' => 'bg-success', 'cancelled' => 'bg-danger',
    ];
    $serviceColors = ['ticket' => '#0088CC', 'hotel' => '#2BAAB1', 'transport' => '#734BA9', 'visa' => '#E36159', 'other' => '#F0AD4E'];
    $svcChart = isset($byService) ? collect($byService)->map(fn ($r, $k) => ['label' => \App\Services\DashboardService::SERVICES[$k], 'value' => max(0, $r['income']), 'color' => $serviceColors[$k]])->values()->all() : [];
    $trendChange = function ($pct) {
        if ($pct === null) return '<span class="text-muted">no data last month</span>';
        $up = $pct >= 0;
        return '<span class="' . ($up ? 'text-success' : 'text-danger') . '"><i class="fas fa-arrow-' . ($up ? 'up' : 'down') . '"></i> ' . abs($pct) . '%</span> <span class="text-muted">vs last month</span>';
    };
@endphp

<style>
    .dash-head { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:18px; }
    .dash-head h2 { margin:0; font-weight:600; }
    .dash-head .sub { color:#777; margin:2px 0 0; }
    .dash-actions .btn { margin:2px 0; }
    .dash .widget-summary .widget-summary-col-icon { width:72px; }
    .dash .widget-summary .summary-icon { width:58px; height:58px; line-height:58px; font-size:1.55rem; }
    .dash .widget-summary .summary .amount { font-size:1.6rem; }
    .dash .kpi { height:calc(100% - 1rem); }
    .dash .kpi .card-body { height:100%; }
    .dash .widget-summary .summary .title { font-size:.95rem; }
    .dash .summary-footer { font-size:.8rem; }
    .dash .mini { display:flex; align-items:center; gap:12px; }
    .dash .mini .ico { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; flex:none; }
    .dash .mini .lbl { color:#777; font-size:.8rem; text-transform:uppercase; letter-spacing:.5px; }
    .dash .mini .val { font-size:1.25rem; font-weight:600; color:#222; line-height:1.2; }
    .dash .card-body.tight { padding:0; }
    .dash .table td, .dash .table th { vertical-align:middle; }
    .dash .attn-item { display:flex; align-items:center; gap:12px; padding:11px 16px; border-bottom:1px solid #eee; color:#333; text-decoration:none; }
    .dash .attn-item:last-child { border-bottom:0; }
    .dash .attn-item:hover { background:#f7f9fb; }
    .dash .attn-item .ico { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex:none; }
    .dash .attn-item .cnt { margin-left:auto; font-weight:700; font-size:1.05rem; }
    .dash .legend-row { display:flex; align-items:center; gap:8px; padding:4px 0; font-size:.85rem; }
    .dash .legend-row .sw { width:10px; height:10px; border-radius:2px; flex:none; }
    .dash .legend-row .amt { margin-left:auto; font-weight:600; }
    .dash .chart-box { position:relative; height:290px; }
    .dash .donut-box { position:relative; height:190px; }
    .dash .empty { color:#999; text-align:center; padding:28px 12px; }
    .dash .bar-track { height:6px; background:#edf0f3; border-radius:3px; overflow:hidden; }
    .dash .bar-fill { height:100%; background:#E36159; }
    .dash .date-chip { display:inline-block; min-width:54px; text-align:center; border:1px solid #dde3e8; border-radius:6px; padding:2px 6px; font-size:.78rem; line-height:1.2; }
    .dash .date-chip b { display:block; font-size:1rem; }
</style>

<div class="dash">
    {{-- ── Heading + quick actions ── --}}
    <div class="dash-head">
        <div>
            <h2 class="text-dark">{{ $greeting }}, {{ $u->name }}</h2>
            <p class="sub">{{ $today->format('l, jS F Y') }} · figures for {{ $monthStart->format('F Y') }} to date</p>
        </div>
        <div class="dash-actions">
            @can('ticket_invoices.create')<a href="{{ route('ticket_invoices.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-ticket-alt"></i> New Ticket Invoice</a>@endcan
            @can('tour_invoices.create')<a href="{{ route('tour_invoices.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-suitcase-rolling"></i> New Tour Invoice</a>@endcan
            @can('invoice_payments.create')<a href="{{ route('invoice_payments.create') }}" class="btn btn-success btn-sm"><i class="fas fa-hand-holding-usd"></i> Record Payment</a>@endcan
            @can('reports.accounts')<a href="{{ route('reports.accounts') }}" class="btn btn-default btn-sm"><i class="fas fa-file-invoice"></i> Customer Statement</a>@endcan
        </div>
    </div>

    @if($canMoney)
    {{-- ── Headline KPIs ── --}}
    <div class="row">
        <div class="col-sm-6 col-xl-3">
            <section class="card card-featured-left card-featured-primary mb-3 kpi">
                <div class="card-body">
                    <div class="widget-summary">
                        <div class="widget-summary-col widget-summary-col-icon"><div class="summary-icon bg-primary"><i class="fas fa-chart-line"></i></div></div>
                        <div class="widget-summary-col">
                            <div class="summary">
                                <h4 class="title">Sales this month</h4>
                                <div class="info"><strong class="amount">{{ $pkr($kpi['sales_month']) }}</strong> <span class="text-primary">PKR</span></div>
                                <div class="small mt-1">{!! $trendChange($kpi['sales_change']) !!}</div>
                            </div>
                            <div class="summary-footer">
                                <span class="text-muted">Today {{ $pkr($kpi['sales_today']) }} · {{ $kpi['invoices_month'] }} invoices</span>
                                @can('reports.travel_sales')<a class="text-primary text-uppercase ms-2" href="{{ route('reports.travel_sales') }}">(view)</a>@endcan
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-sm-6 col-xl-3">
            <section class="card card-featured-left card-featured-tertiary mb-3 kpi">
                <div class="card-body">
                    <div class="widget-summary">
                        <div class="widget-summary-col widget-summary-col-icon"><div class="summary-icon bg-tertiary"><i class="fas fa-coins"></i></div></div>
                        <div class="widget-summary-col">
                            <div class="summary">
                                <h4 class="title">Income this month</h4>
                                <div class="info"><strong class="amount">{{ $pkr($kpi['income_month']) }}</strong> <span class="text-tertiary">PKR</span></div>
                                <div class="small mt-1">{!! $trendChange($kpi['income_change']) !!}</div>
                            </div>
                            <div class="summary-footer">
                                <span class="text-muted">Margin {{ $kpi['income_margin'] }}% of sales</span>
                                @can('reports.travel_sales')<a class="text-tertiary text-uppercase ms-2" href="{{ route('reports.travel_sales', ['tab' => 'by_service']) }}">(by service)</a>@endcan
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-sm-6 col-xl-3">
            <section class="card card-featured-left card-featured-secondary mb-3 kpi">
                <div class="card-body">
                    <div class="widget-summary">
                        <div class="widget-summary-col widget-summary-col-icon"><div class="summary-icon bg-secondary"><i class="fas fa-user-clock"></i></div></div>
                        <div class="widget-summary-col">
                            <div class="summary">
                                <h4 class="title">Receivable from customers</h4>
                                <div class="info"><strong class="amount">{{ $pkr($kpi['receivable']) }}</strong> <span class="text-secondary">PKR</span></div>
                                <div class="small mt-1 text-muted">{{ $kpi['receivable_count'] }} {{ \Illuminate\Support\Str::plural('customer', $kpi['receivable_count']) }} owe us</div>
                            </div>
                            <div class="summary-footer">
                                @can('reports.travel_accounts')<a class="text-secondary text-uppercase" href="{{ route('reports.travel_accounts') }}">(outstanding)</a>@endcan
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-sm-6 col-xl-3">
            <section class="card card-featured-left card-featured-quaternary mb-3 kpi">
                <div class="card-body">
                    <div class="widget-summary">
                        <div class="widget-summary-col widget-summary-col-icon"><div class="summary-icon bg-quaternary"><i class="fas fa-file-invoice-dollar"></i></div></div>
                        <div class="widget-summary-col">
                            <div class="summary">
                                <h4 class="title">Payable to suppliers</h4>
                                <div class="info"><strong class="amount">{{ $pkr($kpi['payable']) }}</strong> <span class="text-quaternary">PKR</span></div>
                                <div class="small mt-1 text-muted">Owed to {{ $kpi['payable_count'] }} suppliers / airlines</div>
                            </div>
                            <div class="summary-footer">
                                @can('reports.travel_accounts')<a class="text-quaternary text-uppercase" href="{{ route('reports.travel_accounts', ['tab' => 'payables']) }}">(payables)</a>@endcan
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- ── Cash strip ── --}}
    <div class="row">
        @foreach([
            ['Cash & Bank balance', $kpi['cash'], 'fa-university', '#0088CC'],
            ['Received this month', $kpi['received_month'], 'fa-arrow-down', '#47A447'],
            ['Paid to suppliers this month', $kpi['paid_month'], 'fa-arrow-up', '#E36159'],
            ['Net cash movement', $kpi['received_month'] - $kpi['paid_month'], 'fa-exchange-alt', '#734BA9'],
        ] as [$label, $val, $icon, $color])
        <div class="col-sm-6 col-xl-3">
            <section class="card mb-3">
                <div class="card-body py-3">
                    <div class="mini">
                        <div class="ico" style="background:{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                        <div><div class="lbl">{{ $label }}</div><div class="val">{{ $pkr($val) }} <small class="text-muted">PKR</small></div></div>
                    </div>
                </div>
            </section>
        </div>
        @endforeach
    </div>

    {{-- ── Charts ── --}}
    <div class="row">
        <div class="col-lg-8">
            <section class="card mb-3">
                <header class="card-header">
                    <h2 class="card-title">Sales &amp; income — last 6 months</h2>
                    <p class="card-subtitle">Ticket Sale and Tour Invoices (cancelled tours excluded), PKR</p>
                </header>
                <div class="card-body"><div class="chart-box"><canvas id="dashTrend" aria-label="Sales and income for the last six months"></canvas></div></div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card mb-3">
                <header class="card-header">
                    <h2 class="card-title">Income by service</h2>
                    <p class="card-subtitle">{{ $monthStart->format('F Y') }} to date</p>
                </header>
                <div class="card-body">
                    @php($incomeTotal = collect($byService)->sum('income'))
                    @if($incomeTotal <= 0)
                        <div class="empty"><i class="fas fa-chart-pie fa-2x mb-2"></i><br>No income booked this month yet.</div>
                    @else
                        <div class="donut-box"><canvas id="dashServices" aria-label="Income by service this month"></canvas></div>
                    @endif
                    <div class="mt-3">
                        @foreach(\App\Services\DashboardService::SERVICES as $key => $label)
                        <div class="legend-row">
                            <span class="sw" style="background:{{ $serviceColors[$key] }}"></span>
                            <span>{{ $label }}</span>
                            <span class="text-muted small">sales {{ $pkr($byService[$key]['receivable']) }}</span>
                            <span class="amt">{{ $pkr($byService[$key]['income']) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
    @endif

    {{-- ── Work queues ── --}}
    <div class="row">
        <div class="col-lg-4">
            <section class="card mb-3">
                <header class="card-header"><h2 class="card-title">Needs attention</h2></header>
                <div class="card-body tight">
                    @foreach($attention as $a)
                        @can($a['perm'])
                        @php($tones = ['warning' => ['#fcf3e3', '#B7791F'], 'danger' => ['#fbe9e8', '#C0392B'], 'dark' => ['#eceef1', '#333']])
                        <a class="attn-item" href="{{ $a['route'] }}">
                            <span class="ico" style="background:{{ $tones[$a['tone']][0] }}; color:{{ $tones[$a['tone']][1] }}"><i class="fas {{ $a['icon'] }}"></i></span>
                            <span>{{ $a['label'] }}</span>
                            <span class="cnt {{ $a['count'] ? '' : 'text-muted' }}">{{ $a['count'] }}</span>
                        </a>
                        @endcan
                    @endforeach
                </div>
            </section>
        </div>
        <div class="col-lg-8">
            <section class="card mb-3">
                <header class="card-header">
                    <h2 class="card-title">Upcoming travel — next 7 days</h2>
                    <p class="card-subtitle">Flight departures and hotel check-ins</p>
                </header>
                <div class="card-body tight">
                    @if($departures->isEmpty())
                        <div class="empty"><i class="fas fa-plane fa-2x mb-2"></i><br>No departures or check-ins in the next 7 days.</div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th class="ps-3">Date</th><th></th><th>Passenger / booking</th><th>Route / hotel</th><th>Customer</th><th class="pe-3">Invoice</th></tr></thead>
                            <tbody>
                            @foreach($departures as $d)
                                <tr>
                                    <td class="ps-3"><span class="date-chip"><b>{{ $d['date']->format('d') }}</b>{{ $d['date']->format('M') }}</span></td>
                                    <td><i class="fas {{ $d['icon'] }} text-primary" title="{{ $d['kind'] }}"></i>@if($d['time']) <span class="small text-muted">{{ \Illuminate\Support\Str::of($d['time'])->substr(0, 5) }}</span>@endif</td>
                                    <td>{{ $d['who'] ?: '—' }}</td>
                                    <td>{{ $d['what'] ?: '—' }}</td>
                                    <td>{{ $d['customer'] }}</td>
                                    <td class="pe-3"><a href="{{ $d['url'] }}">{{ $d['invoice'] }}</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    {{-- ── Recent activity ── --}}
    <div class="row">
        <div class="col-lg-7">
            <section class="card mb-3">
                <header class="card-header"><h2 class="card-title">Recent invoices</h2></header>
                <div class="card-body tight">
                    @if($recentInvoices->isEmpty())
                        <div class="empty"><i class="fas fa-file-invoice fa-2x mb-2"></i><br>No invoices yet. Create the first one with the buttons above.</div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th class="ps-3">Invoice</th><th>Date</th><th>Type</th><th>Customer</th><th class="text-end">Amount</th><th class="pe-3">Status</th></tr></thead>
                            <tbody>
                            @foreach($recentInvoices as $i)
                                <tr>
                                    <td class="ps-3"><a href="{{ $i['url'] }}">{{ $i['no'] }}</a></td>
                                    <td>{{ $i['date']->format('d/m/Y') }}</td>
                                    <td><span class="badge {{ $i['type'] === 'Ticket' ? 'bg-primary' : 'bg-tertiary' }}">{{ $i['type'] }}</span></td>
                                    <td>{{ $i['customer'] }}</td>
                                    <td class="text-end">{{ number_format((float) $i['amount'], 2) }}</td>
                                    <td class="pe-3"><span class="badge {{ $statusBadge[$i['status']] ?? 'bg-secondary' }}">{{ ucfirst($i['status']) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            @if($canMoney)
            <section class="card mb-3">
                <header class="card-header"><h2 class="card-title">Top outstanding customers</h2></header>
                <div class="card-body">
                    @php($maxOwed = max(1, (float) $topCustomers->max('balance')))
                    @forelse($topCustomers as $c)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>
                                    @can('reports.accounts')
                                        <a href="{{ route('reports.customer_statement_pdf', ['customer_id' => $c['id'], 'from_date' => $today->copy()->startOfYear()->toDateString(), 'to_date' => $today->toDateString()]) }}" target="_blank" title="Statement (PDF)">{{ $c['name'] }}</a>
                                    @else {{ $c['name'] }} @endcan
                                </span>
                                <strong>{{ number_format($c['balance'], 2) }}</strong>
                            </div>
                            <div class="bar-track"><div class="bar-fill" style="width:{{ round($c['balance'] / $maxOwed * 100) }}%"></div></div>
                        </div>
                    @empty
                        <div class="empty py-3">No customer owes anything right now.</div>
                    @endforelse
                </div>
            </section>
            @endif
            @can('invoice_payments.index')
            <section class="card mb-3">
                <header class="card-header"><h2 class="card-title">Recent payments</h2></header>
                <div class="card-body tight">
                    @if($recentPayments->isEmpty())
                        <div class="empty py-3">No receipts or payments recorded yet.</div>
                    @else
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach($recentPayments as $p)
                            @php($in = $p->direction === 'receipt')
                            <tr>
                                <td class="ps-3"><i class="fas {{ $in ? 'fa-arrow-down text-success' : 'fa-arrow-up text-danger' }}"></i></td>
                                <td>
                                    <a href="{{ route('invoice_payments.show', $p->id) }}">{{ $in ? ($p->customer->name ?? '—') : ($p->supplier->name ?? '—') }}</a>
                                    <div class="small text-muted">{{ $p->payment_date->format('d/m/Y') }} · {{ $in ? 'Receipt' : 'Supplier payment' }}{{ $p->travelInvoice ? ' · ' . $p->travelInvoice->invoice_no : '' }}</div>
                                </td>
                                <td class="text-end pe-3 {{ $in ? 'text-success' : 'text-danger' }}"><strong>{{ $in ? '+' : '−' }}{{ number_format((float) $p->amount, 2) }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </section>
            @endcan
        </div>
    </div>
</div>

@if($canMoney)
<script>
    // Chart.js is loaded in the footer, after this view — draw once the page has loaded.
    window.addEventListener('load', function () {
        if (typeof Chart === 'undefined') return;
        var pkr = function (v) { return Number(v).toLocaleString('en-PK', { maximumFractionDigits: 0 }); };
        var trend = @json($trend);

        new Chart(document.getElementById('dashTrend'), {
            type: 'bar',
            data: {
                labels: trend.map(function (r) { return r.label; }),
                datasets: [
                    { label: 'Sales', data: trend.map(function (r) { return r.sales; }), backgroundColor: '#0088CC', borderRadius: 4, maxBarThickness: 38 },
                    { label: 'Income', data: trend.map(function (r) { return r.income; }), backgroundColor: '#2BAAB1', borderRadius: 4, maxBarThickness: 38 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', align: 'end' }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + pkr(c.parsed.y) + ' PKR'; } } } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return pkr(v); } }, grid: { color: '#eef1f4' } }, x: { grid: { display: false } } }
            }
        });

        var donut = document.getElementById('dashServices');
        if (donut) {
            var svc = @json($svcChart);
            new Chart(donut, {
                type: 'doughnut',
                data: { labels: svc.map(function (s) { return s.label; }), datasets: [{ data: svc.map(function (s) { return s.value; }), backgroundColor: svc.map(function (s) { return s.color; }), borderWidth: 2, borderColor: '#fff' }] },
                options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return c.label + ': ' + pkr(c.parsed) + ' PKR'; } } } } }
            });
        }
    });
</script>
@endif
@endsection
