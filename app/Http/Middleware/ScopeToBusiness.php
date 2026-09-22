<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\BusinessScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the whole panel on one business, or on all of them.
 *
 * The selection lives in the session so it survives every link, and it is
 * applied by writing it into the request as the `business` filter the list
 * and report screens already understood. That is deliberate: it means the
 * switcher needed no changes in those controllers, and one filter — not two
 * that can disagree — decides what a page shows.
 *
 * A `?business=` on the URL still wins for that request, and is taken as a
 * switch in its own right. The per-page business selects are therefore a
 * second way to change the same thing rather than a rival to it, and an
 * empty one ("All") clears the selection.
 */
class ScopeToBusiness
{
    /** Session key holding the selected business's uuid. */
    public const KEY = 'admin.business';

    public function __construct(
        private BusinessScope $scope,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $businesses = Business::orderBy('name')->get();
        $chosen = null;

        if ($request->isMethod('GET') && $request->has('business')) {
            $uuid = trim((string) $request->query('business'));
            $chosen = $uuid === '' ? null : $businesses->firstWhere('uuid', $uuid);
            $request->session()->put(self::KEY, $chosen?->uuid);
        } elseif ($uuid = $request->session()->get(self::KEY)) {
            $chosen = $businesses->firstWhere('uuid', $uuid);

            // The selected business was deleted, here or from a handset.
            // Falling back to all of them beats an empty panel that gives no
            // hint why.
            if (! $chosen) {
                $request->session()->forget(self::KEY);
            } elseif ($request->isMethod('GET')) {
                $request->query->set('business', $chosen->uuid);
            }
        }

        $this->scope->set($chosen);

        View::share('currentBusiness', $chosen);
        View::share('scopeBusinesses', $businesses);

        return $next($request);
    }
}
