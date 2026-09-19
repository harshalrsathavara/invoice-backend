<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessImages;
use App\Support\Rules;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        return BusinessResource::collection($request->user()->businesses()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(Rules::business());

        $business = $request->user()->businesses()->create($data);

        return new BusinessResource($business);
    }

    public function show(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        return new BusinessResource($business);
    }

    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $business->update($request->validate(Rules::business($business)));

        return new BusinessResource($business->fresh());
    }

    /**
     * The logo or the signature, as bytes.
     *
     * The columns have always been published in BusinessResource, but the paths
     * in them pointed at files nothing served, so a handset could not actually
     * fetch one. They are not public URLs on purpose: a signature is the
     * owner's real signature, so it goes out only to a token that owns it.
     */
    public function image(Request $request, Business $business, string $field, BusinessImages $images)
    {
        $this->authorize('view', $business);

        $image = $images->read($business, $field.'_path');

        abort_if($image === null, 404);

        return response($image['contents'])
            ->header('Content-Type', $image['mime'])
            ->header('Cache-Control', 'private, max-age=300');
    }
}
