@php($quotation = $quotation ?? null)
@php($existingPassengers = $quotation->passengers ?? collect())
@php($existingLines = $quotation->serviceLines ?? collect())

<style>
    #linesTable th, #paxTable th { background:#f8f9fa; }
    #linesTable td, #paxTable td { vertical-align: middle; }
    .line-income.negative { color: #dc3545; font-weight: 600; }
    .line-income.positive { color: #198754; font-weight: 600; }
</style>

<div class="row">
    <div class="col-md-2 mb-3">
        <label>Quotation Date<span class="text-danger">*</span></label>
        <input type="date" name="quotation_date" class="form-control" value="{{ old('quotation_date', optional($quotation?->quotation_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required>
    </div>
    <div class="col-md-2 mb-3">
        <label>Valid Until</label>
        <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', optional($quotation?->valid_until)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3 mb-3">
        <label>Customer<span class="text-danger">*</span></label>
        <select name="customer_id" class="form-control select2-js" required>
            <option value="">Select Customer</option>
            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label>Apply Package</label>
        <select id="packageSelect" class="form-control select2-js">
            <option value="">— None —</option>
            @foreach($packages as $p)<option value="{{ $p->id }}" @selected(old('package_id', $quotation->package_id ?? '') == $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        <input type="hidden" name="package_id" id="package_id" value="{{ old('package_id', $quotation->package_id ?? '') }}">
    </div>
    <div class="col-md-2 mb-3">
        <label>Visit Type</label>
        <input type="text" name="visit_type" class="form-control" placeholder="e.g. Umrah, Tourist" value="{{ old('visit_type', $quotation->visit_type ?? '') }}">
    </div>
</div>

<hr>
<h5>Passengers</h5>
<table class="table table-bordered" id="paxTable">
    <thead><tr><th>Name</th><th width="16%">Passport / NIC</th><th width="12%">Pax Type</th><th width="14%">Nationality</th><th width="14%">DOB</th><th width="50px"></th></tr></thead>
    <tbody>
        @forelse($existingPassengers as $i => $pax)
        <tr>
            <td><input type="text" name="passengers[{{ $i }}][name]" class="form-control" value="{{ $pax->name }}"></td>
            <td><input type="text" name="passengers[{{ $i }}][passport_no_nic]" class="form-control" value="{{ $pax->passport_no_nic }}"></td>
            <td>
                <select name="passengers[{{ $i }}][pax_type]" class="form-control">
                    <option value="adult" @selected($pax->pax_type === 'adult')>Adult</option>
                    <option value="child" @selected($pax->pax_type === 'child')>Child</option>
                    <option value="infant" @selected($pax->pax_type === 'infant')>Infant</option>
                </select>
            </td>
            <td><input type="text" name="passengers[{{ $i }}][nationality]" class="form-control" value="{{ $pax->nationality }}"></td>
            <td><input type="date" name="passengers[{{ $i }}][dob]" class="form-control" value="{{ optional($pax->dob)->format('Y-m-d') }}"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this,'paxTable')"><i class="fas fa-times"></i></button></td>
        </tr>
        @empty
        <tr>
            <td><input type="text" name="passengers[0][name]" class="form-control"></td>
            <td><input type="text" name="passengers[0][passport_no_nic]" class="form-control"></td>
            <td>
                <select name="passengers[0][pax_type]" class="form-control">
                    <option value="adult">Adult</option><option value="child">Child</option><option value="infant">Infant</option>
                </select>
            </td>
            <td><input type="text" name="passengers[0][nationality]" class="form-control"></td>
            <td><input type="date" name="passengers[0][dob]" class="form-control"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this,'paxTable')"><i class="fas fa-times"></i></button></td>
        </tr>
        @endforelse
    </tbody>
</table>
<button type="button" class="btn btn-success btn-sm mb-4" onclick="addPaxRow()">+ Add Passenger</button>

<hr>
<h5>Service Lines</h5>
<p class="text-muted">One row per Ticket / Hotel / Transport / Visa / Other Service. Full booking detail (PNR, room, check-in dates, ...) is captured later on the Sale/Tour Invoice — this is the estimate.</p>
<div class="table-scroll">
<table class="table table-bordered" id="linesTable">
    <thead>
        <tr>
            <th width="10%">Type</th>
            <th width="14%">Supplier</th>
            <th>Description</th>
            <th width="9%">Currency</th>
            <th width="8%">Exch. Rate</th>
            <th width="10%">Receivable (F)</th>
            <th width="10%">Payable (F)</th>
            <th width="10%">Income (Local)</th>
            <th width="40px"></th>
        </tr>
    </thead>
    <tbody>
        @forelse($existingLines as $i => $line)
        <tr>
            <td>
                <select name="lines[{{ $i }}][service_type]" class="form-control">
                    @foreach($serviceTypes as $t)<option value="{{ $t }}" @selected($t === $line->service_type)>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </td>
            <td>
                <select name="lines[{{ $i }}][supplier_id]" class="form-control select2-js">
                    <option value="">— None —</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($s->id == $line->supplier_id)>{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                </select>
            </td>
            <td><input type="text" name="lines[{{ $i }}][description]" class="form-control" value="{{ $line->description }}"></td>
            <td><input type="text" name="lines[{{ $i }}][currency]" class="form-control line-currency" maxlength="3" value="{{ $line->currency }}"></td>
            <td><input type="number" step="any" name="lines[{{ $i }}][exchange_rate]" class="form-control line-rate" value="{{ $line->exchange_rate }}" oninput="recalcRow(this)"></td>
            <td><input type="number" step="any" name="lines[{{ $i }}][receivable_f_amount]" class="form-control line-recv" value="{{ $line->receivable_f_amount }}" oninput="recalcRow(this)"></td>
            <td><input type="number" step="any" name="lines[{{ $i }}][payable_f_amount]" class="form-control line-pay" value="{{ $line->payable_f_amount }}" oninput="recalcRow(this)"></td>
            <td><span class="line-income">{{ number_format($line->income_l_amount, 2) }}</span></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this,'linesTable')"><i class="fas fa-times"></i></button></td>
        </tr>
        @empty
        <tr>
            <td>
                <select name="lines[0][service_type]" class="form-control">
                    @foreach($serviceTypes as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                </select>
            </td>
            <td>
                <select name="lines[0][supplier_id]" class="form-control select2-js">
                    <option value="">— None —</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                </select>
            </td>
            <td><input type="text" name="lines[0][description]" class="form-control"></td>
            <td><input type="text" name="lines[0][currency]" class="form-control line-currency" maxlength="3" value="PKR"></td>
            <td><input type="number" step="any" name="lines[0][exchange_rate]" class="form-control line-rate" value="1" oninput="recalcRow(this)"></td>
            <td><input type="number" step="any" name="lines[0][receivable_f_amount]" class="form-control line-recv" value="0" oninput="recalcRow(this)"></td>
            <td><input type="number" step="any" name="lines[0][payable_f_amount]" class="form-control line-pay" value="0" oninput="recalcRow(this)"></td>
            <td><span class="line-income">0.00</span></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this,'linesTable')"><i class="fas fa-times"></i></button></td>
        </tr>
        @endforelse
    </tbody>
</table>
</div>
<button type="button" class="btn btn-success btn-sm" onclick="addLineRow()">+ Add Service Line</button>

<hr>
<div class="row">
    <div class="col-md-6 mb-3">
        <label>Remarks</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $quotation->remarks ?? '') }}</textarea>
    </div>
    <div class="col-md-6 text-end">
        <table class="table table-borderless mb-0" style="max-width:320px;margin-left:auto;">
            <tr><th>Total Receivable</th><td class="text-end" id="totalReceivable">0.00</td></tr>
            <tr><th>Total Payable</th><td class="text-end" id="totalPayable">0.00</td></tr>
            <tr><th>Total Income</th><td class="text-end"><strong id="totalIncome">0.00</strong></td></tr>
        </table>
    </div>
</div>

<script>
    const packageData = @json($packageServiceMap);

    let paxIndex = {{ max($existingPassengers->count(), 1) }};
    let lineIndex = {{ max($existingLines->count(), 1) }};

    function addPaxRow() {
        const tbody = document.querySelector('#paxTable tbody');
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/passengers\[\d+\]/, `passengers[${paxIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
        });
        tbody.appendChild(clone);
        paxIndex++;
    }
    function removeRow(btn, tableId) {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (tbody.rows.length > 1) btn.closest('tr').remove();
        recalcTotals();
    }

    function addLineRow(prefill) {
        const tbody = document.querySelector('#linesTable tbody');
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/lines\[\d+\]/, `lines[${lineIndex}]`);
            if (el.classList.contains('line-currency')) el.value = 'PKR';
            else if (el.classList.contains('line-rate')) el.value = 1;
            else if (el.classList.contains('line-recv') || el.classList.contains('line-pay')) el.value = 0;
            else if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        clone.querySelector('.line-income').textContent = '0.00';
        clone.querySelectorAll('.select2-container').forEach(el => el.remove());
        if (prefill) {
            clone.querySelector('select[name$="[service_type]"]').value = prefill.service_type;
            clone.querySelector('input[name$="[description]"]').value = prefill.description;
        }
        tbody.appendChild(clone);
        if (window.jQuery) $(clone).find('.select2-js').select2();
        lineIndex++;
    }

    function recalcRow(input) {
        const row = input.closest('tr');
        const rate = parseFloat(row.querySelector('.line-rate').value) || 0;
        const recv = parseFloat(row.querySelector('.line-recv').value) || 0;
        const pay = parseFloat(row.querySelector('.line-pay').value) || 0;
        const income = (recv * rate) - (pay * rate);
        const el = row.querySelector('.line-income');
        el.textContent = income.toFixed(2);
        el.className = 'line-income ' + (income < 0 ? 'negative' : 'positive');
        recalcTotals();
    }

    function recalcTotals() {
        let totalRecv = 0, totalPay = 0, totalIncome = 0;
        document.querySelectorAll('#linesTable tbody tr').forEach(row => {
            const rate = parseFloat(row.querySelector('.line-rate')?.value) || 0;
            const recv = parseFloat(row.querySelector('.line-recv')?.value) || 0;
            const pay = parseFloat(row.querySelector('.line-pay')?.value) || 0;
            totalRecv += recv * rate;
            totalPay += pay * rate;
            totalIncome += (recv * rate) - (pay * rate);
        });
        document.getElementById('totalReceivable').textContent = totalRecv.toFixed(2);
        document.getElementById('totalPayable').textContent = totalPay.toFixed(2);
        document.getElementById('totalIncome').textContent = totalIncome.toFixed(2);
    }

    if (window.jQuery) {
        $('#packageSelect').on('change', function () {
            const id = this.value;
            document.getElementById('package_id').value = id;
            if (!id || !packageData[id]) return;
            packageData[id].forEach(service => addLineRow(service));
        });
    }

    document.addEventListener('DOMContentLoaded', recalcTotals);
    recalcTotals();
</script>
