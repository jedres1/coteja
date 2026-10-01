<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function catalogo(Request $request)
    {
        $filter = $request->query('tipo', '');
        $search = trim((string) $request->query('search', ''));

        $query = AccountingAccount::query()->orderBy('code');

        if ($filter !== '') {
            $query->where('type', $filter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get();

        $parents = AccountingAccount::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level'])
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => "{$a->code} - {$a->name}"])
            ->values();

        $stats = [
            'activo'     => AccountingAccount::where('type', 'activo')->count(),
            'pasivo'     => AccountingAccount::where('type', 'pasivo')->count(),
            'patrimonio' => AccountingAccount::where('type', 'patrimonio')->count(),
            'gasto'      => AccountingAccount::where('type', 'gasto')->count(),
            'ingreso'    => AccountingAccount::where('type', 'ingreso')->count(),
            'total'      => AccountingAccount::count(),
        ];

        return view('admin.accounting.catalogo', compact('accounts', 'parents', 'stats', 'filter', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounting_accounts,code',
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => 'nullable|exists:accounting_accounts,id',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $parent = $data['parent_id'] ? AccountingAccount::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $account = AccountingAccount::create($data);

        return response()->json(['success' => true, 'message' => 'Cuenta creada.', 'account' => $account]);
    }

    public function update(Request $request, AccountingAccount $account)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounting_accounts,code,'.$account->id,
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => 'nullable|exists:accounting_accounts,id',
            'is_active' => 'boolean',
            'notes'     => 'nullable|string|max:1000',
        ]);

        if (isset($data['parent_id']) && $data['parent_id'] == $account->id) {
            return response()->json(['success' => false, 'message' => 'Una cuenta no puede ser su propio padre.'], 422);
        }

        $parent = $data['parent_id'] ? AccountingAccount::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $account->update($data);

        return response()->json(['success' => true, 'message' => 'Cuenta actualizada.', 'account' => $account->fresh()]);
    }

    public function destroy(AccountingAccount $account)
    {
        if ($account->children()->exists()) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar una cuenta que tiene subcuentas.'], 422);
        }

        $account->delete();

        return response()->json(['success' => true, 'message' => 'Cuenta eliminada.']);
    }
}
