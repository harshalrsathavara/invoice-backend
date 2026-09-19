<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Models\Business;
use App\Models\Item;
use App\Support\Rules;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        return ItemResource::collection($business->items()->orderBy('name')->get());
    }

    public function store(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        return new ItemResource($business->items()->create($request->validate(Rules::item())));
    }

    public function update(Request $request, Business $business, Item $item)
    {
        $this->authorize('update', $business);

        $item->update($request->validate(Rules::item($item)));

        return new ItemResource($item->fresh());
    }

    public function destroy(Request $request, Business $business, Item $item)
    {
        $this->authorize('update', $business);

        $item->delete();

        return response()->json(['message' => 'Item removed.']);
    }

}
