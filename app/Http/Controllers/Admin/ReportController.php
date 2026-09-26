<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ReportsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportsService $reports,
    ) {}

    /**
     * Tax collected between two dates — the figures a return is filed from.
     *
     * Defaults to the current Indian financial quarter, because that is the
     * window this is nearly always wanted for.
     */
    public function gst(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'business' => ['nullable', 'uuid'],
        ]);

        [$from, $to] = $this->window($request);

        $businesses = Business::orderBy('name')->get();
        $business = $request->query('business') ? $businesses->firstWhere('uuid', $request->query('business')) : null;

        $query = Invoice::with(['lines', 'taxes', 'payments'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());

        if ($business) {
            $query->where('business_id', $business->id);
        }

        $bills = $query->get();

        return view('admin.reports.gst', [
            'gst' => $this->reports->gstSummary($bills),
            'summary' => $this->reports->summary($bills),
            'bills' => $bills->filter(fn (Invoice $b) => $b->tax_rows !== [] && ! $b->is_voided)
                ->sortBy('date')->values(),
            'businesses' => $businesses,
            'business' => $business,
            'from' => $from,
            'to' => $to,
            'presets' => $this->presets(),
        ]);
    }

    /** Money actually received, newest first — the other half of the ledger. */
    public function payments(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'business' => ['nullable', 'uuid'],
            'mode' => ['nullable', 'in:'.implode(',', Payment::MODES)],
        ]);

        [$from, $to] = $this->window($request);

        $businesses = Business::orderBy('name')->get();
        $business = $request->query('business') ? $businesses->firstWhere('uuid', $request->query('business')) : null;

        $query = Payment::with(['invoice.business'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());

        if ($business) {
            $query->whereHas('invoice', fn ($q) => $q->where('business_id', $business->id));
        }
        if ($mode = $request->query('mode')) {
            $query->where('mode', $mode);
        }

        $payments = $query->orderByDesc('date')->orderByDesc('id')->get();

        // Per-day totals for the trend, oldest first so the chart reads left
        // to right. Days with nothing received are real information, so they
        // are filled in rather than skipped.
        $byDay = $payments->groupBy(fn (Payment $p) => CarbonImmutable::parse($p->date)->toDateString());
        $daily = collect();
        $cursor = $from;
        $days = $from->diffInDays($to) + 1;
        if ($days <= 92) {
            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                $daily->push([
                    'label' => $cursor->format('d M'),
                    'value' => round((float) ($byDay->get($key)?->sum('amount') ?? 0), 2),
                    'bill_count' => $byDay->get($key)?->count() ?? 0,
                ]);
                $cursor = $cursor->addDay();
            }
        }

        return view('admin.reports.payments', [
            'payments' => $payments,
            'daily' => $daily,
            'total' => round((float) $payments->sum('amount'), 2),
            'byMode' => $payments->groupBy('mode')
                ->map(fn ($rows, $mode) => [
                    'mode' => $mode,
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'count' => $rows->count(),
                ])
                ->sortByDesc('amount')
                ->values(),
            'businesses' => $businesses,
            'business' => $business,
            'from' => $from,
            'to' => $to,
            'mode' => $request->query('mode'),
            'presets' => $this->presets(),
        ]);
    }

    /**
     * The chase list: what is owed, oldest first, with a phone number beside
     * each name so it can actually be acted on.
     */
    public function overdue(Request $request)
    {
        $request->validate([
            'business' => ['nullable', 'uuid'],
            'days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        // Everything still owed, unless an age is asked for. It used to
        // default to 60 days, which meant a bill part paid this week — the
        // one most likely to be chased next — was missing from the page
        // named after exactly that.
        $minDays = (int) $request->query('days', 0);
        $today = CarbonImmutable::now()->startOfDay();

        $businesses = Business::orderBy('name')->get();
        $business = $request->query('business') ? $businesses->firstWhere('uuid', $request->query('business')) : null;

        $query = Invoice::liveBills()->with(['business', 'payments']);
        if ($business) {
            $query->where('business_id', $business->id);
        }

        $phones = Customer::all()->keyBy(fn (Customer $c) => $c->business_id.'|'.mb_strtolower($c->name));

        $rows = $query->get()
            ->filter(fn (Invoice $i) => $i->balance > Invoice::EPSILON)
            ->map(function (Invoice $invoice) use ($today, $phones) {
                $age = (int) CarbonImmutable::parse($invoice->date)->startOfDay()->diffInDays($today, false);
                $customer = $phones->get($invoice->business_id.'|'.mb_strtolower($invoice->customer_name));

                return [
                    'invoice' => $invoice,
                    'age' => $age,
                    'owed' => round($invoice->balance, 2),
                    'customer' => $customer,
                    'phone' => $customer?->phone,
                ];
            })
            ->filter(fn (array $row) => $row['age'] >= $minDays)
            ->sortByDesc('age')
            ->values();

        return view('admin.reports.overdue', [
            'rows' => $rows,
            'total' => round((float) $rows->sum('owed'), 2),
            'businesses' => $businesses,
            'business' => $business,
            'minDays' => $minDays,
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function window(Request $request): array
    {
        $to = $request->query('to')
            ? CarbonImmutable::parse($request->query('to'))->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $from = $request->query('from')
            ? CarbonImmutable::parse($request->query('from'))->startOfDay()
            : $this->quarterStart($to);

        // A backwards window is a typo, not a query. Swap rather than return
        // an empty page that looks like "nothing happened".
        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    /** Indian financial quarters: Apr–Jun, Jul–Sep, Oct–Dec, Jan–Mar. */
    private function quarterStart(CarbonImmutable $on): CarbonImmutable
    {
        $startMonth = match (true) {
            $on->month >= 10 => 10,
            $on->month >= 7 => 7,
            $on->month >= 4 => 4,
            default => 1,
        };

        return $on->setDate($on->year, $startMonth, 1)->startOfDay();
    }

    private function presets(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $quarter = $this->quarterStart($today);

        return [
            'This month' => [$today->startOfMonth(), $today],
            'Last month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'This quarter' => [$quarter, $today],
            'Last quarter' => [$quarter->subMonths(3), $quarter->subDay()],
            'This financial year' => [
                $today->month >= 4 ? $today->setDate($today->year, 4, 1) : $today->setDate($today->year - 1, 4, 1),
                $today,
            ],
        ];
    }
}
