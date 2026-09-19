<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Services\InvoiceWriter;
use App\Support\Rules;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /**
     * Documents newest first, with the same filters the Bills tab offers:
     * free text, status, kind and an optional date window.
     */
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $query = $business->invoices()->with(['lines', 'taxes', 'payments']);

        if ($type = $request->query('doc_type')) {
            $query->where('doc_type', $type);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('bill_ref', 'like', "%{$search}%")
                    ->orWhere('bill_no', $search);
            });
        }

        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $invoices = $query->orderByDesc('date')->orderByDesc('bill_no')->paginate(
            min((int) $request->query('per_page', 25), 100)
        );

        // Status is derived from the payments, so it cannot be filtered in SQL
        // without duplicating the rule. Filtering the page keeps one source of
        // truth for what "Partial" means.
        if ($status = $request->query('status')) {
            $invoices->setCollection(
                $invoices->getCollection()->filter(
                    fn (Invoice $i) => strcasecmp($i->status, $status) === 0
                )->values()
            );
        }

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['lines', 'taxes', 'payments']));
    }

    public function store(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $data = $request->validate(Rules::invoice());
        $invoice = $this->writer->create($business, $data);

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function update(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->is_voided) {
            return response()->json([
                'message' => 'This document is cancelled. Reinstate it before editing.',
            ], 422);
        }

        $invoice = $this->writer->update($invoice, $request->validate(Rules::invoice(true)));

        return new InvoiceResource($invoice);
    }

    /**
     * Cancels a document without deleting it: the number stays used and the
     * row stays visible, which is what keeps a numbered series honest.
     */
    public function void(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->writer->void($invoice, $data['reason']);

        return new InvoiceResource($invoice->fresh(['lines', 'taxes', 'payments']));
    }

    public function unvoid(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $this->writer->unvoid($invoice);

        return new InvoiceResource($invoice->fresh(['lines', 'taxes', 'payments']));
    }

    /**
     * Deletion is the last resort: unlike cancelling, it takes the payments
     * with it. Soft deleted, so it can still be recovered by the admin.
     */
    public function destroy(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

}
