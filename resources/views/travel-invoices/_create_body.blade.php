<div class="row">
    <form action="{{ route($routeUri . '.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
        @csrf
        <div class="col-12">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">{{ $pageTitle }}</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('travel-invoices._form', [
                        'customers' => $customers, 'suppliers' => $suppliers, 'chargeTemplates' => $chargeTemplates,
                        'chargeTypes' => $chargeTypes, 'hotels' => $hotels, 'vehicles' => $vehicles,
                        'visaTypes' => $visaTypes, 'services' => $services, 'staffUsers' => $staffUsers,
                        'serviceTypes' => $serviceTypes, 'quotation' => $quotation ?? null,
                    ])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route($routeUri . '.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
                </footer>
            </section>
        </div>
    </form>
</div>
