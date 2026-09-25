<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersTrashed;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Invoice;
use App\Services\InvoiceWriter;
use App\Support\Rules;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use FiltersTrashed;

    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /** The same filters the Bills tab offers on the handset. */
    public function index(Request $request)
    {
        $query = Invoice::with(['business', 'lines', 'taxes', 'payments']);

        // A bill deleted on a handset is soft-deleted here, not erased — this
        // is the only place it can still be read. Cancelling a bill is a
        // different thing entirely and is shown by its Cancelled status.
        $records = $this->applyRecordsFilter($query, $request);

        if ($businessUuid = $request->query('business')) {
            $query->whereHas('business', fn ($q) => $q->where('uuid', $businessUuid));
        }
        if ($type = $request->query('doc_type')) {
            $query->where('doc_type', $type);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('bill_ref', 'like', "%{$search}%")
                ->orWhere('bill_no', $search));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $invoices = $query->orderByDesc('date')->orderByDesc('bill_no')->paginate(30)->withQueryString();

        // Status is derived from the payments, so it is filtered on the page
        // rather than duplicated as a SQL rule.
        if ($status = $request->query('status')) {
            $invoices->setCollection(
                $invoices->getCollection()->filter(fn (Invoice $i) => strcasecmp($i->status, $status) === 0)->values()
            );
        }

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'businesses' => Business::orderBy('name')->get(),
            'filters' => $request->only(['business', 'doc_type', 'status', 'search', 'from', 'to']) + ['records' => $records],
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['business', 'lines', 'taxes', 'payments']);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function create(Request $request)
    {
        $businesses = Business::orderBy('name')->get();
        $business = $request->query('business')
            ? $businesses->firstWhere('uuid', $request->query('business'))
            : $businesses->first();

        abort_if($businesses->isEmpty(), 404, 'There is no business to raise a document against yet.');

        return view('admin.invoices.form', [
            'invoice' => new Invoice([
                'doc_type' => $request->query('doc_type', Invoice::TYPE_BILL),
                'date' => now()->toDateString(),
                'discount_type' => Invoice::DISCOUNT_NONE,
                'discount_value' => 0,
                'round_off' => 0,
            ]),
            'business' => $business,
            'businesses' => $businesses,
            'lines' => collect([['particulars' => '', 'quantity' => 1, 'rate' => 0]]),
            'taxes' => collect(),
        ]);
    }

    /**
     * The number is allocated by the same NumberingService the API uses, inside
     * the same locked transaction — so a document raised here can never claim a
     * number a handset is about to claim.
     */
    public function store(Request $request)
    {
        $request->validate(['business_uuid' => ['required', 'uuid']]);
        $business = Business::where('uuid', $request->input('business_uuid'))->firstOrFail();
        $this->authorize('update', $business);

        $data = $this->documentData($request, false);
        $invoice = $this->writer->create($business, $data);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', ucfirst($invoice->doc_type)." {$invoice->display_no} raised. The handsets will see it on their next sync.");
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->load(['lines', 'taxes']);

        return view('admin.invoices.form', [
            'invoice' => $invoice,
            'business' => $invoice->business,
            'businesses' => Business::orderBy('name')->get(),
            'lines' => $invoice->lines->sortBy('position')->values(),
            'taxes' => $invoice->taxes,
        ]);
    }

    /**
     * Rewrites the document. The number and the recorded receipts are kept —
     * editing a bill must not renumber it or forget what has been paid.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->is_voided) {
            return back()->withErrors(['customer_name' => 'This document is cancelled. Reinstate it before editing.'])->withInput();
        }

        $invoice = $this->writer->update($invoice, $this->documentData($request, true));

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', "Saved {$invoice->display_no}. Its number and its receipts were kept.");
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->writer->void($invoice, $data['reason']);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', "{$invoice->display_no} cancelled. The number stays used, so the series has no hole.");
    }

    public function unvoid(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $this->writer->unvoid($invoice);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', "{$invoice->display_no} reinstated.");
    }

    /**
     * Puts back a bill deleted on a handset.
     *
     * Not the same as reinstating a cancelled one: a cancelled bill is still
     * on the books and keeps its number, whereas a deleted one was hidden
     * everywhere but here. Its lines, taxes and receipts were never removed,
     * so restoring the header brings the whole document back.
     */
    public function restore(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->restore();

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', "{$invoice->display_no} restored. Its lines and receipts came back with it.");
    }

    /**
     * The form posts lines and taxes as parallel arrays of text inputs, so the
     * blank rows a person leaves behind are dropped before validation rather
     * than being rejected back at them.
     */
    private function documentData(Request $request, bool $isUpdate): array
    {
        $lines = collect($request->input('lines', []))
            ->filter(fn ($line) => trim((string) ($line['particulars'] ?? '')) !== '')
            ->map(fn ($line, $i) => [
                'uuid' => $line['uuid'] ?? null,
                'particulars' => trim($line['particulars']),
                'quantity' => $line['quantity'] === '' || $line['quantity'] === null ? 0 : $line['quantity'],
                'rate' => $line['rate'] === '' || $line['rate'] === null ? 0 : $line['rate'],
                // Blank means no GST on this line, not "leave it out".
                'gst_rate' => ($line['gst_rate'] ?? '') === '' || $line['gst_rate'] === null ? 0 : $line['gst_rate'],
                'position' => $i,
            ])
            ->values()
            ->all();

        $taxes = collect($request->input('taxes', []))
            ->filter(fn ($tax) => trim((string) ($tax['label'] ?? '')) !== '')
            ->map(fn ($tax) => [
                'uuid' => $tax['uuid'] ?? null,
                'label' => trim($tax['label']),
                'percent' => $tax['percent'] === '' || $tax['percent'] === null ? 0 : $tax['percent'],
            ])
            ->values()
            ->all();

        // An empty discount box means there is no discount, whichever unit
        // the select happens to be left on. Saying so here keeps the "a
        // discount must be more than zero" rule for people who typed one.
        $discountValue = $request->input('discount_value') === '' ? 0 : $request->input('discount_value');
        $discountType = (float) $discountValue > 0
            ? $request->input('discount_type')
            : Invoice::DISCOUNT_NONE;

        $request->merge([
            'lines' => $lines,
            'taxes' => $taxes,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'round_off' => $request->input('round_off') === '' ? 0 : $request->input('round_off'),
        ]);

        return $request->validate(Rules::invoice($isUpdate));
    }
}
