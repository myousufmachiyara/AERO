<?php

namespace App\Services;

use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\SubHeadOfAccounts;
use Illuminate\Support\Facades\Log;

/**
 * Keeps Supplier / Customer master records mirrored into the existing
 * Chart of Accounts, so every existing accounting screen (ledgers,
 * receivables/payables, vouchers, trial balance, party statements) keeps
 * working unchanged for travel-agency suppliers and customers — they are
 * COA accounts under the hood, exactly like the vendors/customers the base
 * Purchase/Sale Invoice modules already point at.
 */
class PartyLedgerService
{
    public function syncSupplier(Supplier $supplier): ChartOfAccounts
    {
        return $this->syncAccount(
            existingAccountId: $supplier->chart_of_account_id,
            subHeadName: config('travel.payable_subhead_name'),
            accountType: 'vendor',
            name: $supplier->name,
            // Fields default at the DB level, but a freshly-created model only
            // reflects attributes actually passed to create() — coerce nulls
            // here so an omitted field never reaches the strictly-typed args below.
            creditLimit: (float) ($supplier->credit_limit ?? 0),
            payables: (float) ($supplier->opening_balance ?? 0),
            receivables: 0,
            address: $supplier->address,
            contactNo: $supplier->phone,
        );
    }

    public function syncCustomer(Customer $customer): ChartOfAccounts
    {
        return $this->syncAccount(
            existingAccountId: $customer->chart_of_account_id,
            subHeadName: config('travel.receivable_subhead_name'),
            accountType: 'customer',
            name: $customer->name,
            creditLimit: (float) ($customer->credit_limit ?? 0),
            payables: 0,
            receivables: (float) ($customer->opening_balance ?? 0),
            address: $customer->address,
            contactNo: $customer->phone,
        );
    }

    protected function syncAccount(
        ?int $existingAccountId,
        string $subHeadName,
        string $accountType,
        string $name,
        float $creditLimit,
        float $payables,
        float $receivables,
        ?string $address,
        ?string $contactNo,
    ): ChartOfAccounts {
        $userId = auth()->id();

        if ($existingAccountId) {
            $account = ChartOfAccounts::findOrFail($existingAccountId);
            $account->fill([
                'name'         => $name,
                'account_type' => $accountType,
                'credit_limit' => $creditLimit,
                'payables'     => $payables,
                'receivables'  => $receivables,
                'address'      => $address,
                'contact_no'   => $contactNo,
                'updated_by'   => $userId,
            ]);
            $account->save();

            return $account;
        }

        $subHead = SubHeadOfAccounts::where('name', $subHeadName)->first();

        if (!$subHead) {
            // Defensive fallback: the named sub-head should exist from the
            // base seeder, but don't hard-fail party creation if a client's
            // chart of accounts was customised and the name changed.
            Log::warning("[PartyLedgerService] Sub-head '{$subHeadName}' not found; falling back to first sub-head.");
            $subHead = SubHeadOfAccounts::firstOrFail();
        }

        $account = ChartOfAccounts::create([
            'shoa_id'      => $subHead->id,
            'account_code' => ChartOfAccounts::nextAccountCode($subHead->id),
            'name'         => $name,
            'account_type' => $accountType,
            'receivables'  => $receivables,
            'payables'     => $payables,
            'credit_limit' => $creditLimit,
            'opening_date' => now(),
            'address'      => $address,
            'contact_no'   => $contactNo,
            'created_by'   => $userId,
            'updated_by'   => $userId,
        ]);

        return $account;
    }
}
