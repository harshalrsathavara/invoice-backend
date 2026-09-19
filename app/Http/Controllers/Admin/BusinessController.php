<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payment;
use App\Models\User;
use App\Services\BusinessImages;
use App\Services\ReportsService;
use App\Support\Rules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function __construct(
        private ReportsService $reports,
        private BusinessImages $images,
    ) {}

    public function index()
    {
        $businesses = Business::withCount(['invoices', 'customers', 'items'])
            ->with('user')
            ->orderBy('name')
            ->get();

        return view('admin.businesses.index', compact('businesses'));
    }

    public function show(Business $business)
    {
        $bills = $business->invoices()->with(['lines', 'taxes'])->get();

        return view('admin.businesses.show', [
            'business' => $business,
            'summary' => $this->reports->summary($bills),
            'ageing' => $this->reports->ageing($bills),
            'gst' => $this->reports->gstSummary($bills),
            'monthly' => $this->reports->monthlyBilling($bills, 6),
            'topCustomers' => $this->reports->topCustomers($bills),
            'topWork' => $this->reports->topItems($bills),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Business::class);

        return view('admin.businesses.form', [
            'business' => new Business(),
            'owners' => User::orderBy('name')->get(),
        ]);
    }

    /**
     * A business belongs to an owner, and sync is per owner: a handset only
     * ever pulls the businesses of the account it signed in as. So the owner is
     * asked for here rather than assumed to be the admin doing the typing.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Business::class);

        $data = $request->validate(array_merge(Rules::business(), [
            'user_id' => ['required', 'exists:users,id'],
        ]));
        $data['fy_reset'] = $request->boolean('fy_reset');

        $owner = User::findOrFail($data['user_id']);
        unset($data['user_id']);

        $business = $owner->businesses()->create($data);

        $this->applyImages($request, $business);

        return redirect()
            ->route('admin.businesses.show', $business)
            ->with('status', "{$business->name} added. It reaches {$owner->name}'s handsets on their next sync.");
    }

    public function edit(Business $business)
    {
        $this->authorize('update', $business);

        $business->loadCount(['invoices', 'customers', 'items']);

        return view('admin.businesses.form', [
            'business' => $business,
            'owners' => User::orderBy('name')->get(),
        ]);
    }

    /**
     * These fields print on every bill, so an edit here changes what the next
     * document looks like. The handsets pick the change up on their next pull,
     * because saving bumps updated_at — the same cursor sync already uses.
     */
    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $data = $request->validate(Rules::business($business));
        $data['fy_reset'] = $request->boolean('fy_reset');

        $business->update($data);

        $this->applyImages($request, $business);

        return redirect()
            ->route('admin.businesses.show', $business)
            ->with('status', "Saved. {$business->name} will print with these details from the next document on.");
    }

    /**
     * Serves the logo or the signature.
     *
     * Through a route rather than a public URL: the signature is the owner's
     * actual signature, and a link anyone could guess is not the place for it.
     */
    public function image(Business $business, string $field)
    {
        $this->authorize('view', $business);

        $image = $this->images->read($business, $field.'_path');

        abort_if($image === null, 404);

        return response($image['contents'])
            ->header('Content-Type', $image['mime'])
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * The two images, which are posted with the rest of the form.
     *
     * A file input submits nothing when it is left alone, so "no file" has to
     * mean "keep what is there" — only an explicit tick removes one.
     */
    private function applyImages(Request $request, Business $business): void
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        foreach (['logo', 'signature'] as $kind) {
            if ($request->hasFile($kind)) {
                $this->images->store($business, $kind.'_path', $request->file($kind));
            } elseif ($request->boolean('remove_'.$kind)) {
                $this->images->remove($business, $kind.'_path');
            }
        }
    }

    /**
     * Soft delete, and cascade to everything hanging off it.
     *
     * Two reasons it has to cascade. The panel's figures are computed from the
     * bill rows themselves, so bills left behind would keep counting towards
     * money owed for a company that is gone. And sync propagates a deletion by
     * pulling the row with its deleted_at set — dropping only the parent would
     * leave the customers, catalogue and bills sitting on the handsets.
     *
     * Nothing is erased: every row keeps its number and can be restored.
     */
    public function destroy(Business $business)
    {
        $this->authorize('delete', $business);

        $name = $business->name;

        DB::transaction(function () use ($business) {
            $invoices = $business->invoices()->get();

            Payment::whereIn('invoice_id', $invoices->pluck('id'))->get()->each->delete();
            $invoices->each->delete();
            $business->customers()->get()->each->delete();
            $business->items()->get()->each->delete();

            $business->delete();
        });

        return redirect()
            ->route('admin.businesses.index')
            ->with('status', "{$name} was removed, along with its customers, catalogue and documents. Nothing is erased — the rows are marked deleted, and the handsets drop them on their next sync.");
    }
}
