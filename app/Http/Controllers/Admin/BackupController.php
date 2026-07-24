<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backups.index', [
            'backups' => Backup::with(['company', 'license.customer'])->latest('uploaded_at')->paginate(15),
        ]);
    }

    public function download(Backup $backup)
    {
        abort_unless(Storage::disk('local')->exists($backup->path), 404);
        return Storage::disk('local')->download($backup->path, $backup->filename);
    }
}
