<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupApiController extends Controller
{
    public function uploadLatest(Request $request)
    {
        $data = $request->validate([
            'licenseKey' => ['required', 'string'],
            'deviceId' => ['nullable', 'string'],
            'checksum' => ['nullable', 'string'],
            'backup' => ['required', 'file', 'max:102400'],
        ]);

        $license = License::with('plan', 'company')->where('license_key', $data['licenseKey'])->firstOrFail();

        abort_unless($license->isActive() && $license->plan->cloud_backup, 403);

        $file = $request->file('backup');
        $path = 'backups/'.$license->license_key.'/facturacion-latest.db.gz.enc';
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $backup = Backup::updateOrCreate(
            ['license_id' => $license->id],
            [
                'company_id' => $license->company_id,
                'filename' => 'facturacion-latest.db.gz.enc',
                'path' => $path,
                'size_bytes' => Storage::disk('local')->size($path),
                'checksum' => $data['checksum'] ?? hash_file('sha256', $file->getRealPath()),
                'encryption' => 'aes-256-gcm',
                'compression' => 'gzip',
                'device_id' => $data['deviceId'] ?? null,
                'uploaded_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'backupId' => $backup->id, 'uploadedAt' => $backup->uploaded_at]);
    }
}
