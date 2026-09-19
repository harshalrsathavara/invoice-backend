<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\SyncConflict;
use App\Models\SyncLog;
use App\Services\ReportsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ReportsService $reports,
    ) {}

    public function __invoke(Request $request)
    {
        $businesses = Business::with('user')->get();

        // Everything the figures need, loaded once: the reports are computed
        // from the bills themselves, so they can never drift from them.
        $bills = Invoice::with(['lines', 'taxes'])->get();

        $summary = $this->reports->summary($bills);
        $ageing = $this->reports->ageing($bills);
        $monthly = $this->reports->monthlyBilling($bills, 6);

        $recent = Invoice::with('business')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'businesses' => $businesses,
            'summary' => $summary,
            'ageing' => $ageing,
            'monthly' => $monthly,
            'recent' => $recent,
            'topCustomers' => $this->reports->topCustomers($bills, 5),
            'devices' => Device::with('user')->orderByDesc('last_seen_at')->get(),
            'syncLogs' => SyncLog::with('device')->orderByDesc('created_at')->limit(6)->get(),
            'openConflicts' => SyncConflict::unreviewed()->count(),
        ]);
    }
}
