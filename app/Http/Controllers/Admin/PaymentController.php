<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Payments/Index', [
            'payments' => Payment::with(['license.customer', 'company'])->latest()->paginate(15),
            'licenses' => License::with(['customer', 'company'])->orderByDesc('created_at')->get(),
            'periods' => ['monthly' => 'Mensual', 'annual' => 'Anual', 'implementation' => 'Implementación', 'additional' => 'Usuario adicional'],
            'paymentStatuses' => ['paid' => 'Pagado', 'pending' => 'Pendiente', 'void' => 'Anulado'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $license = License::findOrFail($data['license_id']);
        $data['company_id'] = $license->company_id;
        $data['total'] = round($data['amount'] + $data['iva'], 2);

        Payment::create($data);

        return back()->with('status', 'Pago registrado.');
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $this->validated($request);
        $license = License::findOrFail($data['license_id']);
        $data['company_id'] = $license->company_id;
        $data['total'] = round($data['amount'] + $data['iva'], 2);

        $payment->update($data);

        return back()->with('status', 'Pago actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'license_id' => ['required', 'exists:licenses,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'iva' => ['required', 'numeric', 'min:0'],
            'period' => ['required', 'in:monthly,annual,implementation,additional'],
            'method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:paid,pending,void'],
            'paid_at' => ['nullable', 'date'],
        ]);
    }
}
