<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceWriter;
use App\Support\Rules;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /**
     * Records one receipt against a bill. The invoice's cached paid total is
     * recomputed from the payment rows, never incremented in place.
     */
    public function store(Request $request, Business $business, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->doc_type !== Invoice::TYPE_BILL) {
            return response()->json(['message' => 'Only bills take payments.'], 422);
        }

        $data = $request->validate(Rules::payment());

        $this->writer->addPayment($invoice, $data);

        return new InvoiceResource($invoice->fresh(['lines', 'taxes', 'payments']));
    }

    /** Removes a wrongly-entered payment and corrects the bill's balance. */
    public function destroy(Request $request, Business $business, Invoice $invoice, Payment $payment)
    {
        $this->authorize('update', $invoice);

        $this->writer->deletePayment($payment);

        return new InvoiceResource($invoice->fresh(['lines', 'taxes', 'payments']));
    }
}
