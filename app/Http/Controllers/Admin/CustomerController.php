<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersTrashed;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Str;
use App\Services\CustomerWriter;
use App\Services\ReportsService;
use App\Support\BusinessScope;
use App\Support\Rules;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use FiltersTrashed;

    public function __construct(
        private ReportsService $reports,
        private CustomerWriter $writer,
    ) {}

    /**
     * The ledger: every customer with what they owe, heaviest debt first —
     * which is the order anyone chasing money actually wants.
     */
    public function index(Request $request)
    {
        $query = Customer::with('business');

        // Deleting a customer on the handset only soft-deletes them here, so
        // the record is still on file; it is just hidden by default.
        $records = $this->applyRecordsFilter($query, $request);

        if ($businessUuid = $request->query('business')) {
            $query->whereHas('business', fn ($q) => $q->where('uuid', $businessUuid));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $customers = $query->orderBy('name')->get();

        // One query for the bills, then rolled up in memory so "outstanding"
        // means exactly what it means everywhere else.
        $bills = Invoice::liveBills()
            ->whereIn('business_id', $customers->pluck('business_id')->unique())
            ->get()
            ->groupBy(fn (Invoice $i) => mb_strtolower($i->customer_name));

        $rows = $customers->map(function (Customer $customer) use ($bills) {
            $theirs = $bills->get(mb_strtolower($customer->name), collect());

            return [
                'customer' => $customer,
                'bill_count' => $theirs->count(),
                'billed' => round($theirs->sum(fn ($i) => (float) $i->total), 2),
                'collected' => round($theirs->sum(fn ($i) => (float) $i->paid_amount), 2),
                'outstanding' => round($theirs->sum(fn ($i) => (float) $i->total - (float) $i->paid_amount), 2),
            ];
        })->sortByDesc('outstanding')->values();

        return view('admin.customers.index', [
            'rows' => $rows,
            'businesses' => Business::orderBy('name')->get(),
            'filters' => $request->only(['business', 'search']) + ['records' => $records],
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->load('business');

        $invoices = $customer->business->invoices()
            ->with(['lines', 'taxes', 'payments'])
            ->where('customer_name', $customer->name)
            ->orderByDesc('date')
            ->get();

        return view('admin.customers.show', compact('customer', 'invoices'));
    }

    public function create(Request $request, BusinessScope $scope)
    {
        return view('admin.customers.form', [
            'customer' => new Customer(),
            // Whichever business the panel is open on is the one being added
            // to, so the select starts there rather than on the first name
            // alphabetically.
            'business' => $scope->current(),
            'businesses' => Business::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $business = $this->businessFrom($request);
        $this->authorize('update', $business);

        $customer = $this->writer->create($business, $request->validate(Rules::customer($business)));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', "{$customer->name} added to {$business->name}.");
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer->business);

        return view('admin.customers.form', [
            'customer' => $customer,
            'business' => $customer->business,
            'businesses' => Business::orderBy('name')->get(),
        ]);
    }

    /**
     * A rename also rewrites the name on this business's existing bills — the
     * bill stores the name as text, so without that the ledger would split one
     * person into two. That rule lives in CustomerWriter, shared with the API.
     */
    public function update(Request $request, Customer $customer)
    {
        $business = $customer->business;
        $this->authorize('update', $business);

        $data = $request->validate(Rules::customer($business, $customer));
        $previousName = $customer->name;

        $customer = $this->writer->update($business, $customer, $data);

        $renamed = mb_strtolower(trim($previousName)) !== mb_strtolower(trim($customer->name));

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', $renamed
                ? "Renamed to {$customer->name}. Their existing bills were updated to match."
                : "Saved {$customer->name}.");
    }

    public function destroy(Customer $customer)
    {
        $business = $customer->business;
        $this->authorize('update', $business);

        $name = $customer->name;
        $billCount = $this->writer->delete($business, $customer);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', $billCount > 0
                ? "{$name} removed from the list. Their {$billCount} ".\Str::plural('bill', $billCount).' stay on the books.'
                : "{$name} removed from the list.");
    }

    /**
     * Puts a soft-deleted customer back on the list.
     *
     * The row was never thrown away — a deletion on the handset only sets
     * deleted_at here — so undoing one is just clearing that column. The
     * handset picks the restored row up on its next pull.
     */
    public function restore(Customer $customer)
    {
        $this->authorize('update', $customer->business);

        $customer->restore();

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', "{$customer->name} restored to the customer list.");
    }

    /** One customer's ledger on a page built to be printed and handed over. */
    public function statement(Customer $customer)
    {
        $this->authorize('view', $customer->business);

        $invoices = $customer->business->invoices()
            ->where('customer_name', $customer->name)
            ->with('payments')
            ->orderBy('date')
            ->orderBy('bill_no')
            ->get();

        // A running balance only means anything if bills and receipts are
        // interleaved in the order they actually happened.
        $entries = collect();
        foreach ($invoices as $invoice) {
            if ($invoice->doc_type !== Invoice::TYPE_BILL || $invoice->is_voided) {
                continue;
            }

            $entries->push([
                'date' => $invoice->date,
                'kind' => 'bill',
                'ref' => $invoice->display_no,
                'detail' => $invoice->lines->pluck('particulars')->filter()->take(2)->implode(', '),
                'debit' => (float) $invoice->total,
                'credit' => 0.0,
                'invoice' => $invoice,
            ]);

            foreach ($invoice->payments as $payment) {
                $entries->push([
                    'date' => $payment->date,
                    'kind' => 'payment',
                    'ref' => $invoice->display_no,
                    'detail' => ucfirst($payment->mode).($payment->note ? ' · '.$payment->note : ''),
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                    'invoice' => $invoice,
                ]);
            }
        }

        $running = 0.0;
        $entries = $entries->sortBy([['date', 'asc'], ['kind', 'desc']])->values()->map(function ($entry) use (&$running) {
            $running += $entry['debit'] - $entry['credit'];
            $entry['balance'] = round($running, 2);

            return $entry;
        });

        return view('admin.customers.statement', [
            'customer' => $customer,
            'business' => $customer->business,
            'entries' => $entries,
            'closing' => round($running, 2),
        ]);
    }

    private function businessFrom(Request $request): Business
    {
        $request->validate(['business_uuid' => ['required', 'uuid']]);

        return Business::where('uuid', $request->input('business_uuid'))->firstOrFail();
    }
}
