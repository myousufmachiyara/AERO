<div class="row">
    <form action="{{ route($routeUri . '.update', $invoice->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
        @csrf @method('PUT')
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">{{ $pageTitle }} — {{ $invoice->invoice_no }}</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('travel-invoices._form', [
                        'customers' => $customers, 'suppliers' => $suppliers, 'chargeTemplates' => $chargeTemplates,
                        'chargeTypes' => $chargeTypes, 'hotels' => $hotels, 'vehicles' => $vehicles,
                        'visaTypes' => $visaTypes, 'services' => $services, 'staffUsers' => $staffUsers,
                        'serviceTypes' => $serviceTypes, 'invoice' => $invoice,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route($routeUri . '.show', $invoice->id) }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Invoice</button>
                </footer>
            </section>
        </div>
    </form>
</div>
