<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    UserController,
    RoleController,
    VoucherController,
    AccountsReportController,
    PermissionController,
    ProductSubcategoryController,
    SupplierController,
    CustomerController,
    ServiceController,
    HotelController,
    HotelRoomController,
    RoomViewController,
    VehicleController,
    VisaTypeController,
    ChargeTypeController,
    ChargeTemplateController,
    PackageController,
    QuotationController,
    TourInvoiceController,
    AirlineController,
    TicketSaleInvoiceController,
    VendorComplaintController,
    InvoicePaymentController,
    TravelVendorReportController,
    TravelSalesReportController,
    TravelAccountsReportController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::put('/users/{id}/change-password', [UserController::class, 'changePassword'])->name('users.changePassword');
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggleActive');
    Route::post('/change-my-password', [UserController::class, 'changeMyPassword'])->name('users.changeMyPassword');

    // Common Modules
    $modules = [
        // User Management
        'roles' => ['controller' => RoleController::class, 'permission' => 'user_roles'],
        'permissions' => ['controller' => PermissionController::class, 'permission' => 'role_permissions'],
        'users' => ['controller' => UserController::class, 'permission' => 'users'],

        // Accounts
        'coa' => ['controller' => COAController::class, 'permission' => 'coa'],
        'shoa' => ['controller' => SubHeadOfAccController::class, 'permission' => 'shoa'],

        // Vouchers
        'vouchers' => ['controller' => VoucherController::class, 'permission' => 'vouchers'],

        // Travel Agency — Phase 1: masters
        'suppliers' => ['controller' => SupplierController::class, 'permission' => 'suppliers'],
        'customers' => ['controller' => CustomerController::class, 'permission' => 'customers'],
        'services' => ['controller' => ServiceController::class, 'permission' => 'services'],
        'hotels' => ['controller' => HotelController::class, 'permission' => 'hotels'],
        'hotel_rooms' => ['controller' => HotelRoomController::class, 'permission' => 'hotel_rooms'],
        'room_views' => ['controller' => RoomViewController::class, 'permission' => 'room_views'],
        'vehicles' => ['controller' => VehicleController::class, 'permission' => 'vehicles'],
        'visa_types' => ['controller' => VisaTypeController::class, 'permission' => 'visa_types'],
        'charge_types' => ['controller' => ChargeTypeController::class, 'permission' => 'charge_types'],
        'charge_templates' => ['controller' => ChargeTemplateController::class, 'permission' => 'charge_templates'],
        'airlines' => ['controller' => AirlineController::class, 'permission' => 'airlines'],

        // Travel Agency — Phase 2: packages & quotation
        'packages' => ['controller' => PackageController::class, 'permission' => 'packages'],
        'quotations' => ['controller' => QuotationController::class, 'permission' => 'quotations'],

        // Tour Invoice (all services) — see BaseTravelInvoiceController for
        // why this isn't named sale_invoices (already used by the base
        // app's inventory module). Ticket Sale Invoice ("Sale Invoice
        // (Tickets)" in the sidebar) has its own bespoke route group below
        // instead of this generic CRUD loop — it needs post/unpost/refund/
        // void actions the loop doesn't support.
        'tour_invoices' => ['controller' => TourInvoiceController::class, 'permission' => 'tour_invoices'],

        // Travel Agency — Phase 4: vendor complaint log / auto-flagging
        'vendor_complaints' => ['controller' => VendorComplaintController::class, 'permission' => 'vendor_complaints'],

        // Travel Agency — Phase 5: payments over the existing Voucher module
        'invoice_payments' => ['controller' => InvoicePaymentController::class, 'permission' => 'invoice_payments'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];

        // Determine route parameter
        $param = $uri === 'roles' ? '{role}' : '{id}';

        if ($uri === 'vouchers') {
            // Voucher routes with type in all relevant actions
            Route::prefix("$uri/{type}")->group(function () use ($controller, $permission) {
                Route::get('/', [$controller, 'index'])->middleware("check.permission:$permission.index")->name("vouchers.index");
                Route::get('/create', [$controller, 'create'])->middleware("check.permission:$permission.create")->name("vouchers.create");
                Route::post('/', [$controller, 'store'])->middleware("check.permission:$permission.create")->name("vouchers.store");

                Route::get('/{id}', [$controller, 'show'])->middleware("check.permission:$permission.index")->name("vouchers.show");
                Route::get('/{id}/edit', [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("vouchers.edit");
                Route::put('/{id}', [$controller, 'update'])->middleware("check.permission:$permission.edit")->name("vouchers.update");
                Route::delete('/{id}', [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("vouchers.destroy");
                Route::get('/{id}/print', [$controller, 'print'])->middleware("check.permission:$permission.print")->name('vouchers.print');
            });

            continue;
        }

        // Index & Create
        Route::get("$uri", [$controller, 'index'])->middleware("check.permission:$permission.index")->name("$uri.index");
        Route::get("$uri/create", [$controller, 'create'])->middleware("check.permission:$permission.create")->name("$uri.create");
        Route::post("$uri", [$controller, 'store'])->middleware("check.permission:$permission.create")->name("$uri.store");

        // Show, Edit, Update, Delete, Print
        Route::get("$uri/$param", [$controller, 'show'])->middleware("check.permission:$permission.index")->name("$uri.show");
        Route::get("$uri/$param/edit", [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("$uri.edit");
        Route::put("$uri/$param", [$controller, 'update'])->middleware("check.permission:$permission.edit")->name("$uri.update");
        Route::delete("$uri/$param", [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("$uri.destroy");
        Route::get("$uri/$param/print", [$controller, 'print'])->middleware("check.permission:$permission.print")->name("$uri.print");
    }

    // Reports (readonly)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('accounts', [AccountsReportController::class, 'accounts'])->name('accounts');

        // Travel Agency — Phase 6: Vendor / Sales / Accounting report suites
        Route::get('travel-vendor', [TravelVendorReportController::class, 'index'])
            ->middleware('check.permission:reports.travel_vendor')->name('travel_vendor');
        Route::get('travel-sales', [TravelSalesReportController::class, 'index'])
            ->middleware('check.permission:reports.travel_sales')->name('travel_sales');
        Route::get('travel-accounts', [TravelAccountsReportController::class, 'index'])
            ->middleware('check.permission:reports.travel_accounts')->name('travel_accounts');
    });

    Route::patch('/quotations/{id}/status', [QuotationController::class, 'updateStatus'])
        ->middleware('check.permission:quotations.edit')
        ->name('quotations.status');

    // Ticket Sale Invoice — bespoke route group (client feedback rebuild):
    // pending/posted workflow plus per-ticket refund/void, none of which
    // fit the generic CRUD loop above.
    Route::prefix('ticket_invoices')->name('ticket_invoices.')->group(function () {
        Route::get('/', [TicketSaleInvoiceController::class, 'index'])->middleware('check.permission:ticket_invoices.index')->name('index');
        Route::get('/create', [TicketSaleInvoiceController::class, 'create'])->middleware('check.permission:ticket_invoices.create')->name('create');
        Route::post('/', [TicketSaleInvoiceController::class, 'store'])->middleware('check.permission:ticket_invoices.create')->name('store');
        Route::get('/{id}', [TicketSaleInvoiceController::class, 'show'])->middleware('check.permission:ticket_invoices.index')->name('show');
        Route::get('/{id}/edit', [TicketSaleInvoiceController::class, 'edit'])->middleware('check.permission:ticket_invoices.edit')->name('edit');
        Route::put('/{id}', [TicketSaleInvoiceController::class, 'update'])->middleware('check.permission:ticket_invoices.edit')->name('update');
        Route::delete('/{id}', [TicketSaleInvoiceController::class, 'destroy'])->middleware('check.permission:ticket_invoices.delete')->name('destroy');
        Route::get('/{id}/print', [TicketSaleInvoiceController::class, 'printCustomer'])->middleware('check.permission:ticket_invoices.print')->name('print');
        Route::get('/{id}/print-detailed', [TicketSaleInvoiceController::class, 'printDetailed'])->middleware('check.permission:ticket_invoices.print')->name('print_detailed');
        Route::patch('/{id}/post', [TicketSaleInvoiceController::class, 'post'])->middleware('check.permission:ticket_invoices.edit')->name('post');
        Route::patch('/{id}/unpost', [TicketSaleInvoiceController::class, 'unpost'])->middleware('check.permission:ticket_invoices.edit')->name('unpost');
        Route::post('/{invoice}/lines/{line}/refund', [TicketSaleInvoiceController::class, 'refundLine'])->middleware('check.permission:ticket_invoices.edit')->name('lines.refund');
        Route::post('/{invoice}/lines/{line}/void', [TicketSaleInvoiceController::class, 'voidLine'])->middleware('check.permission:ticket_invoices.edit')->name('lines.void');
    });

    // Self-scoped — always shows only the logged-in agent's own commission, so no permission gate.
    Route::get('/my-commission', [TicketSaleInvoiceController::class, 'myCommission'])->name('ticket_invoices.my_commission');

    Route::patch('/tour_invoices/{id}/status', [TourInvoiceController::class, 'updateStatus'])
        ->middleware('check.permission:tour_invoices.edit')
        ->name('tour_invoices.status');
});