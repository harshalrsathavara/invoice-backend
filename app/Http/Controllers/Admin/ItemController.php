<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Item;
use App\Support\Rules;
use Illuminate\Http\Request;

/**
 * The item catalogue — what the handset offers as suggestions when someone
 * types a line onto a bill. Editing a default rate here changes what the next
 * bill suggests; it never rewrites a bill already raised.
 */
class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with('business');

        if ($businessUuid = $request->query('business')) {
            $query->whereHas('business', fn ($q) => $q->where('uuid', $businessUuid));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->orderBy('name')->get();

        // What each catalogue entry has actually earned, matched on the line
        // text the way the app itself matches it.
        $lines = Invoice::liveBills()
            ->whereIn('business_id', $items->pluck('business_id')->unique())
            ->with('lines')
            ->get()
            ->flatMap(fn (Invoice $i) => $i->lines)
            ->groupBy(fn ($line) => mb_strtolower(trim($line->particulars)));

        $rows = $items->map(function (Item $item) use ($lines) {
            $used = $lines->get(mb_strtolower(trim($item->name)), collect());

            return [
                'item' => $item,
                'times' => $used->count(),
                'earned' => round($used->sum(fn ($l) => (float) $l->amount), 2),
                'last_rate' => $used->last()?->rate !== null ? (float) $used->last()->rate : null,
            ];
        })->sortByDesc('earned')->values();

        return view('admin.items.index', [
            'rows' => $rows,
            'businesses' => Business::orderBy('name')->get(),
            'filters' => $request->only(['business', 'search']),
        ]);
    }

    public function create()
    {
        return view('admin.items.form', [
            'item' => new Item(),
            'business' => null,
            'businesses' => Business::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['business_uuid' => ['required', 'uuid']]);
        $business = Business::where('uuid', $request->input('business_uuid'))->firstOrFail();
        $this->authorize('update', $business);

        $item = $business->items()->create($request->validate(Rules::item()));

        return redirect()
            ->route('admin.items.index', ['business' => $business->uuid])
            ->with('status', "{$item->name} added to {$business->name}'s catalogue.");
    }

    public function edit(Item $item)
    {
        $this->authorize('update', $item->business);

        return view('admin.items.form', [
            'item' => $item,
            'business' => $item->business,
            'businesses' => Business::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $this->authorize('update', $item->business);

        $item->update($request->validate(Rules::item($item)));

        return redirect()
            ->route('admin.items.index', ['business' => $item->business->uuid])
            ->with('status', "Saved {$item->name}. Bills already raised keep the rate they were raised at.");
    }

    public function destroy(Item $item)
    {
        $this->authorize('update', $item->business);

        $name = $item->name;
        $business = $item->business;
        $item->delete();

        return redirect()
            ->route('admin.items.index', ['business' => $business->uuid])
            ->with('status', "{$name} removed from the catalogue. Bills that used it are untouched.");
    }
}
