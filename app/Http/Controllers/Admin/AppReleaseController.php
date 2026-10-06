<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppReleaseController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Releases/Index', ['releases' => AppRelease::latest()->get()]);
    }

    public function store(Request $request)
    {
        AppRelease::create($request->validate([
            'platform' => ['required', 'in:windows,ios,macos,linux'],
            'version' => ['required', 'string', 'max:50'],
            'filename' => ['required', 'string', 'max:255'],
            'download_url' => ['required', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => false]);

        return back()->with('status', 'Version publicada.');
    }
}
