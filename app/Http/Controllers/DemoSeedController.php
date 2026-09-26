<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Fills an empty server with something to look at, from a link.
 *
 * Render's free plan has no shell, so there is no way to run a seeder against
 * a running instance by hand. Opening this does it instead, and opening it
 * again reports what is already there rather than making a second copy of it
 * — which is the behaviour that makes the link safe to send to somebody and
 * safe to reload while they are looking at it.
 *
 * It takes no login on purpose. See config/demo.php for what stops that being
 * a problem, and for the switch that closes it.
 */
class DemoSeedController extends Controller
{
    public function __invoke()
    {
        abort_unless(config('demo.enabled'), 404);

        // What was here before, so the page can say whether this visit did
        // anything or simply found the work already done.
        $before = Business::count();

        Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $businesses = Business::withCount(['invoices', 'customers', 'items'])
            ->orderBy('name')
            ->get();

        return view('demo.seeded', [
            'seededNow' => $businesses->count() > $before,
            'businesses' => $businesses,
            'owner' => User::where('is_admin', true)->orderBy('id')->first(),
            'totals' => [
                'businesses' => $businesses->count(),
                'documents' => Invoice::count(),
                'customers' => Customer::count(),
                'items' => Item::count(),
                'payments' => Payment::count(),
            ],
        ]);
    }
}
