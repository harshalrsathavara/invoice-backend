<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ReportsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The figures the business is asked for.
 *
 * Everything is computed from the saved bills rather than stored, so a report
 * can never drift from the bills it describes.
 */
class ReportController extends Controller
{
    public function __construct(
        private ReportsService $reports,
    ) {}

    public function __invoke(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        [$from, $to, $label] = $this->period($request);

        $bills = $business->invoices()
            ->with(['lines', 'taxes'])
            ->between($from, $to)
            ->get();

        return response()->json([
            'period' => ['label' => $label, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'summary' => $this->reports->summary($bills),
            'gst' => $this->reports->gstSummary($bills),
            'ageing' => $this->reports->ageing($bills),
            'monthly' => $this->reports->monthlyBilling($bills, 6, $to),
            'top_customers' => $this->reports->topCustomers($bills),
            'top_work' => $this->reports->topItems($bills),
        ]);
    }

    /**
     * The same period choices the Reports screen offers: this month, last
     * month, this financial year, all time, or a custom window.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: string}
     */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();

        return match ($request->query('period', 'this_month')) {
            'last_month' => [
                $now->subMonth()->startOfMonth(),
                $now->subMonth()->endOfMonth(),
                'Last month',
            ],
            'this_fy' => [
                $fy = $now->month >= 4
                    ? $now->startOfYear()->addMonths(3)
                    : $now->subYear()->startOfYear()->addMonths(3),
                $fy->addYear()->subDay(),
                'FY '.Business::financialYearOf($now),
            ],
            'all_time' => [null, null, 'All time'],
            'custom' => [
                $from = CarbonImmutable::parse($request->query('from', $now->startOfMonth()->toDateString())),
                $to = CarbonImmutable::parse($request->query('to', $now->toDateString())),
                $from->format('d M Y').' – '.$to->format('d M Y'),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth(), 'This month'],
        };
    }
}
