<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use App\Services\CustomerWriter;
use App\Support\Rules;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerWriter $writer,
    ) {}

    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $query = $business->customers()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return CustomerResource::collection($query->get());
    }

    public function store(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $customer = $this->writer->create($business, $request->validate(Rules::customer($business)));

        return new CustomerResource($customer);
    }

    /**
     * Invoices store the customer name as text, so a rename is also applied to
     * this business's existing invoices — otherwise the ledger would split the
     * same person into two entries.
     */
    public function update(Request $request, Business $business, Customer $customer)
    {
        $this->authorize('update', $business);

        $data = $request->validate(Rules::customer($business, $customer));

        return new CustomerResource($this->writer->update($business, $customer, $data));
    }

    /**
     * Removes a customer from the master list. Their past invoices are left
     * untouched — deleting a name must never delete billing history.
     */
    public function destroy(Request $request, Business $business, Customer $customer)
    {
        $this->authorize('update', $business);

        $invoiceCount = $this->writer->delete($business, $customer);

        return response()->json([
            'message' => 'Customer removed. Their bills are untouched.',
            'invoice_count' => $invoiceCount,
        ]);
    }

}
