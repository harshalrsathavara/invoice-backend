<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * The write path for the customer master list.
 *
 * A bill stores the customer's name as text, so that it keeps the name it was
 * raised under. The consequence is that a rename has to be applied in two
 * places at once, and that rule has to hold whether the rename came from a
 * handset or from the admin panel — so it lives here, not in a controller.
 */
class CustomerWriter
{
    public function create(Business $business, array $data): Customer
    {
        return $business->customers()->create($data);
    }

    public function update(Business $business, Customer $customer, array $data): Customer
    {
        $previousName = $customer->name;

        DB::transaction(function () use ($customer, $data, $business, $previousName) {
            $customer->update($data);

            $newName = $customer->name;
            if (mb_strtolower(trim($previousName)) !== mb_strtolower(trim($newName))) {
                $business->invoices()
                    ->where('customer_name', $previousName)
                    ->update(['customer_name' => $newName]);
            }
        });

        return $customer->fresh();
    }

    /**
     * Removes a name from the master list. Their past bills are left untouched
     * — deleting a customer must never delete billing history.
     *
     * @return int how many of their bills stay on the books
     */
    public function delete(Business $business, Customer $customer): int
    {
        $billCount = $business->invoices()->where('customer_name', $customer->name)->count();

        $customer->delete();

        return $billCount;
    }
}
