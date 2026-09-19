<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncConflict;
use App\Models\SyncLog;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        return view('admin.devices.index', [
            'devices' => Device::with('user')->orderByDesc('last_seen_at')->get(),
            'logs' => SyncLog::with('device')->orderByDesc('created_at')->limit(40)->get(),
        ]);
    }

    /**
     * Revokes a handset's token. Used when a phone is lost — the data stays,
     * the device simply stops being able to reach it.
     */
    public function revoke(Request $request, Device $device)
    {
        $device->user->tokens()->where('name', 'device:'.$device->uuid)->delete();

        return back()->with('status', "{$device->name} can no longer sync. It will need to sign in again.");
    }
}
