<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncConflict;
use Illuminate\Http\Request;

/**
 * Changes that lost a last-write-wins comparison.
 *
 * Nothing here has been applied — the point of the screen is that a discarded
 * edit is visible rather than silently gone.
 */
class ConflictController extends Controller
{
    public function index(Request $request)
    {
        $query = SyncConflict::with('syncLog.device')->orderByDesc('created_at');

        if (! $request->boolean('all')) {
            $query->unreviewed();
        }

        return view('admin.conflicts.index', [
            'conflicts' => $query->paginate(20)->withQueryString(),
            'showingAll' => $request->boolean('all'),
        ]);
    }

    public function review(SyncConflict $conflict)
    {
        $conflict->update(['reviewed' => true]);

        return back()->with('status', 'Marked as reviewed.');
    }
}
