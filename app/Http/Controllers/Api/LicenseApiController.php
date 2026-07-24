<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDevice;
use Illuminate\Http\Request;

class LicenseApiController extends Controller
{
    public function validateLicense(Request $request)
    {
        $data = $request->validate([
            'licenseKey' => ['required', 'string'],
            'deviceId' => ['required', 'string'],
            'deviceName' => ['nullable', 'string'],
        ]);

        $license = License::with(['plan', 'company', 'customer'])
            ->where('license_key', $data['licenseKey'])
            ->first();

        if (!$license) {
            return response()->json(['valid' => false, 'status' => 'not_found'], 404);
        }

        $activeDevices = $license->devices()->where('is_active', true)->count();
        $device = $license->devices()->where('device_id', $data['deviceId'])->first();

        if (!$device && $activeDevices >= $license->max_devices) {
            return response()->json(['valid' => false, 'status' => 'device_limit'], 403);
        }

        LicenseDevice::updateOrCreate(
            ['license_id' => $license->id, 'device_id' => $data['deviceId']],
            ['device_name' => $data['deviceName'] ?? null, 'is_active' => true, 'activated_at' => now(), 'last_seen_at' => now()]
        );

        $license->update(['last_validated_at' => now()]);

        return response()->json([
            'valid' => $license->isActive(),
            'status' => $license->status,
            'expiresAt' => optional($license->expires_at)->toDateString(),
            'graceDays' => $license->grace_days,
            'maxUsers' => $license->max_users,
            'maxDevices' => $license->max_devices,
            'plan' => $license->plan->code,
            'company' => ['name' => $license->company->business_name, 'nitDui' => $license->company->nit_dui],
            'features' => [
                'cloudBackup' => $license->plan->cloud_backup,
                'support' => $license->plan->support_included,
                'unlimitedDocuments' => $license->plan->unlimited_documents,
            ],
        ]);
    }
}
