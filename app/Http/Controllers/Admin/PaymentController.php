<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceWriter;
use App\Services\Money;
use App\Support\Rules;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private InvoiceWriter $writer,
    ) {}

    /**
     * Records one receipt. The bill's paid total is recomputed from the
     * payment rows rather than incremented, which is what keeps it honest
     * when a payment is later removed.
     */
    public function store(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->doc_type !== Invoice::TYPE_BILL) {
            return back()->withErrors(['amount' => 'Only bills take payments — a quotation or challan is not owed yet.']);
        }

        if ($invoice->is_voided) {
            return back()->withErrors(['amount' => 'This bill is cancelled. Reinstate it before recording a receipt.']);
        }

        $data = $request->validate(Rules::payment());

        // Overpaying is usually a typo, but it is occasionally deliberate (an
        // advance, a rounding gift). Warn rather than block.
        $overpay = (float) $data['amount'] - $invoice->balance;

        $this->writer->addPayment($invoice, $data);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', $overpay > Invoice::EPSILON
                ? 'Receipt recorded. It is '.Money::rupees($overpay).' more than the balance, so this bill is now overpaid.'
                : 'Receipt recorded. '.Money::rupees($invoice->fresh()->balance).' still outstanding.');
    }

    public function destroy(Invoice $invoice, Payment $payment)
    {
        $this->authorize('update', $invoice);

        abort_unless($payment->invoice_id === $invoice->id, 404);

        $this->writer->deletePayment($payment);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Receipt removed. The balance has been recalculated from what is left.');
    }
}
