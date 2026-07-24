<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::orderBy('monthly_price')->get()]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validated($request));
        return back()->with('status', 'Plan creado.');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request));
        return back()->with('status', 'Plan actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'implementation_fee' => ['required', 'numeric', 'min:0'],
            'additional_user_price' => ['required', 'numeric', 'min:0'],
            'included_users' => ['required', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1'],
            'support_included' => ['nullable', 'boolean'],
            'cloud_backup' => ['nullable', 'boolean'],
            'unlimited_documents' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'support_included' => false,
            'cloud_backup' => false,
            'unlimited_documents' => false,
            'is_active' => false,
        ];
    }
}
