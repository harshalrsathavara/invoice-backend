<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private SyncService $sync,
    ) {}

    /** Applies a batch of offline changes from a handset. */
    public function push(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['nullable', 'uuid'],
            'changes' => ['required', 'array'],
        ]);

        $result = $this->sync->push(
            $request->user(),
            $this->device($request, $data['device_uuid'] ?? null),
            $data['changes'],
        );

        return response()->json($result);
    }

    /**
     * Everything that changed at or after the cursor.
     *
     * The cursor is inclusive, so a handful of rows may arrive twice; every
     * apply is an idempotent upsert keyed on the UUID, which is a far better
     * trade than missing a row written in the same second as the last sync.
     */
    public function pull(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['nullable', 'uuid'],
            'since' => ['nullable', 'date'],
        ]);

        $result = $this->sync->pull(
            $request->user(),
            $this->device($request, $data['device_uuid'] ?? null),
            isset($data['since']) ? CarbonImmutable::parse($data['since']) : null,
        );

        return response()->json($result);
    }

    private function device(Request $request, ?string $uuid): ?Device
    {
        if (! $uuid) {
            return null;
        }

        return $request->user()->devices()->where('uuid', $uuid)->first();
    }
}
