<x-layouts.app title="Cuentas bancarias | Coteja">
    @php $money = fn($v) => '$'.number_format((float)$v, 2); @endphp
    <style>
        .page-header{margin-bottom:24px}.page-header h1{margin:0;font-size:26px}.page-header p{margin:4px 0 0}
        .section-title{font-size:17px;font-weight:700;margin:0 0 16px}
        .bt-table{width:100%;border-collapse:collapse}
        .bt-table th,.bt-table td{padding:10px 14px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle}
        .bt-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:#f9fafb}
        body.dark-mode .bt-table th{background:#0f172a;color:#cbd5e1}
        .bt-table tr:last-child td{border-bottom:0}
        .empty-state{padding:40px;text-align:center;color:var(--muted);font-size:15px}
        .badge{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
        .active-badge{background:#dcfce7;color:#166534}.inactive-badge{background:#f3f4f6;color:#6b7280}
        .overlay-layer{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center}
        .overlay-backdrop{position:absolute;inset:0;background:rgb(0 0 0/.45);border:0;cursor:pointer}
        .overlay-panel{position:relative;z-index:1;min-width:340px;max-width:540px;width:100%;max-height:90vh;overflow-y:auto}
        .overlay-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}
        .overlay-header h3{margin:0;font-size:16px}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
        .form-grid label{display:grid;gap:6px;font-size:13px;color:#374151;font-weight:600}
        body.dark-mode .form-grid label{color:#d1d5db}
        .span-full{grid-column:1/-1}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 14px;border-radius:8px;border:0;background:var(--brand);color:#fff;font-weight:700;font:inherit;cursor:pointer;font-size:13px}
        .btn.secondary{background:#4b5563}.btn.danger{background:var(--bad)}.btn.sm{min-height:30px;padding:5px 10px;font-size:12px}
        .actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    </style>

    <div class="page-header">
        <h1>Cuentas bancarias</h1>
        <p class="muted">Gestión de cuentas para registrar y conciliar transacciones.</p>
    </div>

    <div class="card" style="margin-bottom:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h2 class="section-title" style="margin:0">Cuentas registradas</h2>
            <button class="btn" type="button" onclick="document.getElementById('new-account-overlay').style.display='flex'">Nueva cuenta</button>
        </div>

        @if($accounts->isEmpty())
            <div class="empty-state">No hay cuentas bancarias registradas.</div>
        @else
            <table class="bt-table">
                <thead>
                    <tr>
                        <th>Nombre</th><th>Banco</th><th>Número</th><th>Tipo</th><th>Moneda</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td><strong>{{ $account->name }}</strong></td>
                            <td>{{ $account->bank_name }}</td>
                            <td>{{ $account->account_number ?: '—' }}</td>
                            <td>{{ ucfirst($account->account_type) }}</td>
                            <td>{{ $account->currency }}</td>
                            <td><span class="badge {{ $account->is_active ? 'active-badge' : 'inactive-badge' }}">{{ $account->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                            <td>
                                <button class="btn secondary sm" type="button"
                                    onclick="openEditAccount({{ $account->id }}, {{ json_encode($account) }})">
                                    Editar
                                </button>
                                @if($account->bankTransactions->count() === 0 && $account->billingPayments->count() === 0)
                                    <form method="post" action="{{ route('admin.bank-transactions.accounts.destroy', $account) }}" style="display:inline" onsubmit="return confirm('¿Eliminar esta cuenta?')">
                                        @csrf @method('DELETE')
                                        <button class="btn danger sm" type="submit">Eliminar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- New account overlay --}}
    <div id="new-account-overlay" class="overlay-layer" style="display:none">
        <button class="overlay-backdrop" type="button" onclick="document.getElementById('new-account-overlay').style.display='none'"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <div><h3>Nueva cuenta bancaria</h3></div>
                <button class="btn secondary sm" type="button" onclick="document.getElementById('new-account-overlay').style.display='none'">Cerrar</button>
            </div>
            <form method="post" action="{{ route('admin.bank-transactions.accounts.store') }}">
                @csrf
                <div class="form-grid">
                    <label>Nombre de la cuenta <span style="color:var(--bad)">*</span><input name="name" required maxlength="150" placeholder="Cuenta corriente principal"></label>
                    <label>Banco <span style="color:var(--bad)">*</span><input name="bank_name" required maxlength="100" placeholder="Banco Agrícola"></label>
                    <label>Número de cuenta<input name="account_number" maxlength="50" placeholder="Últimos 4 dígitos o completo"></label>
                    <label>Tipo de cuenta
                        <select name="account_type">
                            <option value="corriente">Corriente</option>
                            <option value="ahorros">Ahorros</option>
                            <option value="otro">Otro</option>
                        </select>
                    </label>
                    <label>Moneda<input name="currency" value="USD" maxlength="3"></label>
                    <label>Saldo inicial<input name="opening_balance" type="number" step="0.01" min="0" value="0"></label>
                    <label class="span-full">Notas<textarea name="notes" rows="2" maxlength="1000"></textarea></label>
                </div>
                <div class="actions-row">
                    <button class="btn" type="submit">Guardar cuenta</button>
                    <button class="btn secondary" type="button" onclick="document.getElementById('new-account-overlay').style.display='none'">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit account overlay --}}
    <div id="edit-account-overlay" class="overlay-layer" style="display:none">
        <button class="overlay-backdrop" type="button" onclick="document.getElementById('edit-account-overlay').style.display='none'"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <div><h3>Editar cuenta bancaria</h3></div>
                <button class="btn secondary sm" type="button" onclick="document.getElementById('edit-account-overlay').style.display='none'">Cerrar</button>
            </div>
            <form id="edit-account-form" method="post" action="">
                @csrf @method('PUT')
                <div class="form-grid">
                    <label>Nombre <span style="color:var(--bad)">*</span><input id="edit-name" name="name" required maxlength="150"></label>
                    <label>Banco <span style="color:var(--bad)">*</span><input id="edit-bank_name" name="bank_name" required maxlength="100"></label>
                    <label>Número<input id="edit-account_number" name="account_number" maxlength="50"></label>
                    <label>Tipo
                        <select id="edit-account_type" name="account_type">
                            <option value="corriente">Corriente</option>
                            <option value="ahorros">Ahorros</option>
                            <option value="otro">Otro</option>
                        </select>
                    </label>
                    <label>Moneda<input id="edit-currency" name="currency" maxlength="3"></label>
                    <label>Estado
                        <select id="edit-is_active" name="is_active">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </label>
                    <label class="span-full">Notas<textarea id="edit-notes" name="notes" rows="2" maxlength="1000"></textarea></label>
                </div>
                <div class="actions-row">
                    <button class="btn" type="submit">Guardar cambios</button>
                    <button class="btn secondary" type="button" onclick="document.getElementById('edit-account-overlay').style.display='none'">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditAccount(id, data) {
            document.getElementById('edit-account-form').action = '/admin/control-bancario/cuentas/' + id;
            document.getElementById('edit-name').value = data.name || '';
            document.getElementById('edit-bank_name').value = data.bank_name || '';
            document.getElementById('edit-account_number').value = data.account_number || '';
            document.getElementById('edit-account_type').value = data.account_type || 'corriente';
            document.getElementById('edit-currency').value = data.currency || 'USD';
            document.getElementById('edit-is_active').value = data.is_active ? '1' : '0';
            document.getElementById('edit-notes').value = data.notes || '';
            document.getElementById('edit-account-overlay').style.display = 'flex';
        }
    </script>
</x-layouts.app>
