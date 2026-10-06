<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Traits\ExportsCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CustomerController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $geography = json_decode(file_get_contents(public_path('catalogs/division-geografica.json')), true) ?: [];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => Customer::withCount(['companies', 'licenses'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('nrc', 'like', "%{$search}%")
                            ->orWhere('business_activity', 'like', "%{$search}%")
                            ->orWhere('activity_description', 'like', "%{$search}%")
                            ->orWhere('billing_email', 'like', "%{$search}%")
                            ->orWhere('billing_phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
            'geography' => $geography,
            'documentTypes' => ['13' => 'DUI', '36' => 'NIT', '37' => 'Otro / extranjero', '03' => 'Pasaporte', '02' => 'Carnet residente'],
            'dteTypes' => ['01' => 'Factura', '03' => 'CCF', '05' => 'Nota crédito', '06' => 'Nota débito', '07' => 'Retención', '11' => 'Exportación', '14' => 'Sujeto excluido'],
            'statuses' => ['active' => 'Activo', 'suspended' => 'Suspendido'],
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        $customer = Customer::create($data);

        User::create([
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'role' => 'customer',
            'is_active' => $customer->status === 'active',
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('status', 'Cliente creado.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();

        $customer->update($data);
        $customer->users()->update(['is_active' => $customer->status === 'active']);

        return back()->with('status', 'Cliente actualizado.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return back()->with('status', 'Cliente eliminado.');
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Customer::withCount(['companies', 'licenses'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('nrc', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get()
            ->map(fn ($c) => [
                $c->name,
                $c->trade_name,
                $c->email,
                $c->phone,
                $c->document_number,
                $c->nrc,
                $c->status,
                $c->companies_count,
                $c->licenses_count,
            ]);

        return $this->streamCsv(
            'clientes-' . now()->format('Ymd') . '.csv',
            ['Nombre', 'Nombre Comercial', 'Email', 'Teléfono', 'NIT/DUI', 'NRC', 'Estado', 'Empresas', 'Licencias'],
            $rows
        );
    }
}
