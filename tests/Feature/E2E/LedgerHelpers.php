<?php

namespace Tests\Feature\E2E;

use App\Models\Airline;
use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PartyLedgerService;
use Database\Seeders\DatabaseSeeder;

/**
 * Shared fixtures + ledger helpers for the end-to-end module suites.
 * Balances are Σ debits − Σ credits over live (not soft-deleted) vouchers.
 */
trait LedgerHelpers
{
    protected User $admin;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Airline $airline;   // numeric code 220
    protected Airline $airline2;  // numeric code 176

    protected function bootFixtures(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'hassan')->firstOrFail();
        $this->actingAs($this->admin);

        $ledger = app(PartyLedgerService::class);
        $this->customer = Customer::factory()->create(['name' => 'Vacations Gala']);
        $this->customer->update(['chart_of_account_id' => $ledger->syncCustomer($this->customer)->id]);
        $this->supplier = Supplier::factory()->create();
        $this->supplier->update(['chart_of_account_id' => $ledger->syncSupplier($this->supplier)->id]);
        $this->airline = Airline::factory()->create(['numeric_code' => '220', 'name' => 'Lufthansa']);
        $this->airline->update(['chart_of_account_id' => $ledger->syncAirline($this->airline)->id]);
        $this->airline2 = Airline::factory()->create(['numeric_code' => '176', 'name' => 'Emirates']);
        $this->airline2->update(['chart_of_account_id' => $ledger->syncAirline($this->airline2)->id]);
    }

    protected function balance(?int $accountId): float
    {
        if (!$accountId) {
            return 0.0;
        }

        return round((float) Voucher::where('ac_dr_sid', $accountId)->sum('amount')
            - (float) Voucher::where('ac_cr_sid', $accountId)->sum('amount'), 2);
    }

    protected function systemAccountId(string $configKey): ?int
    {
        return ChartOfAccounts::where('name', config("travel.$configKey"))->value('id');
    }

    /** Every account's balance, keyed by account name — for "same end state" comparisons. */
    protected function allBalances(): array
    {
        $ids = Voucher::pluck('ac_dr_sid')->merge(Voucher::pluck('ac_cr_sid'))->unique();

        return ChartOfAccounts::whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($a) => [$a->name => $this->balance($a->id)])
            ->filter(fn ($b) => abs($b) > 0.001)
            ->sortKeys()
            ->all();
    }

    protected function assertBooksBalance(): void
    {
        $dr = round((float) Voucher::sum('amount'), 2);
        $this->assertEquals(0.0, round(collect($this->allBalances())->sum(), 2), 'Σ of all account balances must be 0 (double entry).');
        $this->assertGreaterThanOrEqual(0, $dr);
    }
}
