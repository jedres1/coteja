<x-layouts.app title="Conceptos de Nómina | Coteja">
    <style>
        .page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .page-head h1 { margin:0; font-size:24px; }
        .page-head p { margin:4px 0 0; color:var(--muted); font-size:14px; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .alert.ok { background:#ecfdf5; color:#166534; border-left:4px solid var(--ok); }
        .alert.bad { background:#fef2f2; color:#991b1b; border-left:4px solid var(--bad); }
        .card-section-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 16px; border-bottom:1px solid var(--line); }
        .card-section-head h3 { margin:0; font-size:15px; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .form-grid label { display:grid; gap:5px; font-size:13px; font-weight:600; color:#374151; }
        .form-grid input,.form-grid select { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; box-sizing:border-box; }
        .form-grid select[multiple] { min-height:96px; }
        .span-full { grid-column:1/-1; }
        .nom-table { width:100%; border-collapse:collapse; font-size:13px; }
        .nom-table th,.nom-table td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
        .nom-table th { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); background:#f9fafb; white-space:nowrap; }
        .empty { padding:36px; text-align:center; color:var(--muted); }
        .row-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font:inherit; font-weight:700; cursor:pointer; font-size:13px; text-decoration:none; }
        .btn.secondary { background:#4b5563; }
        .btn.sm { min-height:28px; padding:5px 10px; font-size:12px; }
        .btn.danger { background:var(--bad); }
        .overlay-modal { position:relative; display:inline-block; }
        .overlay-modal > summary { list-style:none; cursor:pointer; }
        .overlay-modal > summary::-webkit-details-marker { display:none; }
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; width:min(720px,calc(100vw - 32px)); max-height:92vh; overflow-y:auto; padding:20px; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; position:sticky; top:0; background:var(--panel); padding-bottom:12px; border-bottom:1px solid var(--line); }
        .overlay-header h3 { margin:0; font-size:16px; }
        @media(max-width:640px) { .form-grid { grid-template-columns:1fr; } .span-full { grid-column:auto; } }
    </style>

    <div class="page-head">
        <div><h1>Gestión de conceptos</h1><p>Conceptos legales y conceptos aplicables a empleados.</p></div>
        <a class="btn secondary" href="{{ route('admin.payroll.index') }}">Empleados y nóminas</a>
    </div>

    @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="card" style="margin-bottom:18px">
        <div class="card-section-head"><h3>Conceptos legales integrados</h3></div>
        <div style="overflow-x:auto">
            <table class="nom-table">
                <thead><tr><th>Concepto</th><th>Tipo</th><th>Fórmula aplicada</th><th>Cuenta contable</th><th>Contrapartida</th></tr></thead>
                <tbody>
                    @forelse($legalConcepts as $concept)
                        <tr>
                            <td><strong>{{ $concept->name }}</strong></td>
                            <td>{{ ucfirst($concept->type) }}</td>
                            <td><code>{{ $concept->formula }}</code></td>
                            <td colspan="2">
                                <form method="post" action="{{ route('admin.payroll.concepts.accounts.update', $concept) }}" class="row-actions">
                                    @csrf
                                    @method('PUT')
                                    <select name="account_id" required aria-label="Cuenta contable de {{ $concept->name }}">
                                        @foreach($accounts->where('type', in_array($concept->system_key, ['salario', 'isss_patronal', 'afp_patronal'], true) ? 'gasto' : 'pasivo') as $account)
                                            <option value="{{ $account->id }}" @selected($concept->account_id === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    @if(in_array($concept->system_key, ['isss_patronal', 'afp_patronal'], true))
                                        <select name="payable_account_id" required aria-label="Cuenta por pagar de {{ $concept->name }}">
                                            @foreach($accounts->where('type', 'pasivo') as $account)
                                                <option value="{{ $account->id }}" @selected($concept->payable_account_id === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button class="btn secondary sm" type="submit">Guardar cuentas</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Los conceptos legales se cargarán con el seeder de nómina después de aplicar la migración.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="padding:0 16px 14px;margin:0;color:var(--muted);font-size:12px">
            ISSS y AFP usan las tasas y el tope de Configuración. ISR aplica la tabla mensual del Decreto Ejecutivo 10/2025 sobre el salario gravado neto de ISSS y AFP laborales.
        </p>
    </div>

    <div class="card" style="padding:20px 24px;margin-bottom:18px">
        <h3 style="margin:0 0 16px;font-size:16px">Nuevo concepto de nómina</h3>
        <form method="post" action="{{ route('admin.payroll.concepts.store') }}">
            @csrf
            <div class="form-grid">
                <label>Nombre<input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Comisión por venta"></label>
                <label>Tipo<select name="type" required><option value="beneficio">Beneficio</option><option value="deduccion">Deducción</option></select></label>
                <label>Forma de cálculo<select name="calculation_method" required><option value="fijo">Monto fijo</option><option value="porcentaje">Porcentaje del salario</option><option value="formula">Fórmula</option><option value="editable">Monto editable por período</option></select></label>
                <label>Monto fijo ($)<input type="number" name="amount" min="0" step="0.01" value="{{ old('amount', 0) }}"></label>
                <label>Porcentaje (%)<input type="number" name="rate" min="0" max="100" step="0.0001" value="{{ old('rate', 0) }}"></label>
                <label>Fórmula<input name="formula" value="{{ old('formula') }}" maxlength="255" placeholder="salary * 0.10"></label>
                <label>Cuenta contable<select name="account_id" required><option value="">— Seleccionar —</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                <label>Empleados específicos<select name="employee_ids[]" multiple>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->position ?: 'Sin cargo' }})</option>@endforeach</select></label>
                <label class="span-full" style="display:flex;align-items:center;gap:9px"><input type="checkbox" name="applies_to_all" value="1" style="width:auto">Aplicar a todos los empleados activos</label>
            </div>
            <p style="font-size:12px;color:var(--muted);margin:10px 0">Variables de fórmula: salary, gross y overtime. Los montos editables se ingresan en cada período.</p>
            <button class="btn" type="submit">Crear concepto</button>
        </form>
    </div>

    <div class="card">
        <div class="card-section-head"><h3>Conceptos personalizados ({{ $concepts->count() }})</h3></div>
        <div style="overflow-x:auto">
            <table class="nom-table">
                <thead><tr><th>Concepto</th><th>Tipo</th><th>Cálculo</th><th>Cuenta contable</th><th>Aplicación</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse($concepts as $concept)
                        <tr>
                            <td><strong>{{ $concept->name }}</strong></td>
                            <td>{{ ucfirst($concept->type) }}</td>
                            <td>{{ ucfirst($concept->calculation_method) }}</td>
                            <td>{{ $concept->account?->code }} — {{ $concept->account?->name }}</td>
                            <td>{{ $concept->applies_to_all ? 'Todos los empleados' : ($concept->employees->pluck('name')->join(', ') ?: 'Sin asignar') }}</td>
                            <td>
                                <div class="row-actions">
                                    <details class="overlay-modal">
                                        <summary class="btn secondary sm">Editar</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                            <div class="card overlay-panel">
                                                <div class="overlay-header"><h3>Editar concepto: {{ $concept->name }}</h3><button class="btn secondary sm" type="button" data-overlay-close>Cerrar</button></div>
                                                <form class="custom-concept-form" method="post" action="{{ route('admin.payroll.concepts.update', $concept) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="form-grid">
                                                        <label>Nombre<input name="name" value="{{ $concept->name }}" required maxlength="255"></label>
                                                        <label>Tipo<select name="type"><option value="beneficio" @selected($concept->type==='beneficio')>Beneficio</option><option value="deduccion" @selected($concept->type==='deduccion')>Deducción</option></select></label>
                                                        <label>Forma de cálculo<select name="calculation_method"><option value="fijo" @selected($concept->calculation_method==='fijo')>Monto fijo</option><option value="porcentaje" @selected($concept->calculation_method==='porcentaje')>Porcentaje del salario</option><option value="formula" @selected($concept->calculation_method==='formula')>Fórmula</option><option value="editable" @selected($concept->calculation_method==='editable')>Monto editable por período</option></select></label>
                                                        <label>Monto fijo ($)<input type="number" name="amount" value="{{ $concept->amount }}" min="0" step="0.01"></label>
                                                        <label>Porcentaje (%)<input type="number" name="rate" value="{{ $concept->rate }}" min="0" max="100" step="0.0001"></label>
                                                        <label>Fórmula<input name="formula" value="{{ $concept->formula }}" maxlength="255" placeholder="salary * 0.10"></label>
                                                        <label>Cuenta contable<select name="account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}" data-account-type="{{ $account->type }}" @selected($concept->account_id===$account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                                                        <label>Empleados específicos<select name="employee_ids[]" multiple>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($concept->employees->contains('id',$employee->id))>{{ $employee->name }} ({{ $employee->position ?: 'Sin cargo' }})</option>@endforeach</select></label>
                                                        <label class="span-full" style="display:flex;align-items:center;gap:9px"><input type="checkbox" name="applies_to_all" value="1" @checked($concept->applies_to_all) style="width:auto">Aplicar a todos los empleados activos</label>
                                                    </div>
                                                    <p style="font-size:12px;color:var(--muted)">Variables: salary, gross y overtime.</p>
                                                    <button class="btn" type="submit">Guardar concepto</button>
                                                </form>
                                            </div>
                                        </div>
                                    </details>
                                    <form method="post" action="{{ route('admin.payroll.concepts.destroy', $concept) }}" data-confirm-delete="Eliminar concepto">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger sm" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Aún no hay conceptos personalizados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.querySelectorAll('form[action*="/conceptos"]').forEach(function(form) {
            var typeSelect = form.querySelector('select[name="type"]');
            var accountSelect = form.querySelector('select[name="account_id"]');
            if (!typeSelect || !accountSelect) return;

            function filterAccounts() {
                var accountType = typeSelect.value === 'beneficio' ? 'gasto' : 'pasivo';
                var selectedIsValid = false;
                Array.from(accountSelect.options).forEach(function(option) {
                    if (!option.value) return;
                    var matches = option.dataset.accountType === accountType;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (matches && option.selected) selectedIsValid = true;
                });
                if (!selectedIsValid) accountSelect.value = '';
            }

            typeSelect.addEventListener('change', filterAccounts);
            filterAccounts();
        });

        document.addEventListener('click', function(event) {
            if (event.target.hasAttribute('data-overlay-close')) {
                event.target.closest('details.overlay-modal')?.removeAttribute('open');
            }
        });
        document.querySelectorAll('[data-confirm-delete]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!confirm(form.dataset.confirmDelete + '. ¿Desea continuar?')) event.preventDefault();
            });
        });
    </script>
</x-layouts.app>