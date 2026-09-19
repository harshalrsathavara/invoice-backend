<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackupImportException;
use App\Services\BackupImportService;
use Illuminate\Http\Request;

/**
 * Seeds the server from the app's own backup file — the one-time bridge for
 * data that already exists on a handset.
 */
class BackupImportController extends Controller
{
    public function __construct(
        private BackupImportService $importer,
    ) {}

    /** Parses and validates without applying, so the caller sees what it holds. */
    public function inspect(Request $request)
    {
        $payload = $this->payload($request);

        try {
            return response()->json($this->importer->inspect($payload));
        } catch (BackupImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $payload = $this->payload($request);

        try {
            $result = $this->importer->import(
                $request->user(),
                $payload,
                $request->boolean('replace_existing'),
            );
        } catch (BackupImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Takes the backup either as an uploaded file or as an inline JSON body,
     * because the handset already has the file on disk and a script testing
     * the endpoint would rather paste the object.
     */
    private function payload(Request $request): array
    {
        if ($request->hasFile('backup')) {
            $decoded = json_decode(file_get_contents($request->file('backup')->getRealPath()), true);

            return is_array($decoded) ? $decoded : [];
        }

        return $request->input('payload', []);
    }
}
