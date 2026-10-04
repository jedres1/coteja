<x-layouts.app title="Control de Nómina | Coteja">
    <style>
        .nom-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
        .nom-top h1 { margin:0; font-size:26px; }
        .nom-top p { margin:4px 0 0; color:var(--muted); font-size:14px; }
        /* tabs */
        .tab-bar { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--line); }
        .tab-btn { padding:10px 18px; font:700 13px/1 inherit; border:0; background:transparent; cursor:pointer; color:var(--muted); border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; }
        .tab-btn.active { color:var(--brand); border-bottom-color:var(--brand); }
        .tab-section { display:none; }
        .tab-section.active { display:block; }
        /* alerts */
        .alert { padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .alert.ok  { background:#ecfdf5; color:#166534; border-left:4px solid var(--ok); }
        .alert.bad { background:#fef2f2; color:#991b1b; border-left:4px solid var(--bad); }
        /* overlay */
        .overlay-modal { position:relative; display:inline-block; }
        .overlay-modal > summary { list-style:none; cursor:pointer; }
        .overlay-modal > summary::-webkit-details-marker { display:none; }
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; min-width:340px; max-width:600px; width:100%; max-height:92vh; overflow-y:auto; }
        .overlay-panel.wide { max-width:720px; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; position:sticky; top:0; background:var(--panel); padding-bottom:12px; border-bottom:1px solid var(--line); }
        .overlay-header h3 { margin:0; font-size:16px; }
        /* forms */
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
        .form-grid label { display:grid; gap:5px; font-size:13px; font-weight:600; color:#374151; }
        .form-grid input,.form-grid select,.form-grid textarea { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; box-sizing:border-box; }
        .form-grid textarea { min-height:70px; }
        .span-full { grid-column:1/-1; }
        .form-actions { display:flex; gap:10px; margin-top:6px; }
        /* table */
        .nom-table { width:100%; border-collapse:collapse; font-size:13px; }
        .nom-table th,.nom-table td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
        .nom-table th { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); background:#f9fafb; white-space:nowrap; }
        .nom-table tr:last-child td { border-bottom:0; }
        .nom-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
        .empty { padding:40px; text-align:center; color:var(--muted); }
        /* badges */
        .badge { display:inline-flex; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
        .badge-draft { background:#fef3c7; color:#92400e; }
        .badge-applied { background:#dcfce7; color:#166534; }
        .badge-inactive { background:#f1f5f9; color:#475569; }
        .badge-active { background:#dcfce7; color:#166534; }
        /* misc */
        .row-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .btn.secondary { background:#4b5563; }
        .btn.sm { min-height:28px; padding:5px 10px; font-size:12px; }
        .btn.danger { background:var(--bad); }
        .card-section-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 16px; border-bottom:1px solid var(--line); margin-bottom:0; }
        .card-section-head h3 { margin:0; font-size:15px; font-weight:700; }
        .settings-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; }
        .settings-grid label { display:grid; gap:5px; font-size:13px; font-weight:600; color:#374151; }
        .settings-grid input,.settings-grid select { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; box-sizing:border-box; }
        .form-grid select[multiple] { min-height:96px; }
        .section-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin:20px 0 10px; }
    </style>

    <div class="nom-top">
        <div>
            <h1>Control de Nómina</h1>
            <p>Empleados y períodos de nómina</p>
        </div>
    </div>

    @if(session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert bad">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Tab bar --}}
    <div class="tab-bar">
        <button class="tab-btn {{ $activeTab === 'empleados' ? 'active' : '' }}" data-tab="empleados">Empleados</button>
        <button class="tab-btn {{ $activeTab === 'nominas' ? 'active' : '' }}" data-tab="nominas">Nóminas</button>
    </div>

    {{-- ═══════════════════════════════════════════════
         TAB: EMPLEADOS
    ═══════════════════════════════════════════════ --}}
    <div class="tab-section {{ $activeTab === 'empleados' ? 'active' : '' }}" data-section="empleados">
        <div class="card">
            <div class="card-section-head">
                <h3>Empleados ({{ $employees->count() }})</h3>
                <details class="overlay-modal" @if($errors->any() && !old('_tab')) open @endif>
                    <summary class="btn">Nuevo Empleado</summary>
                    <div class="overlay-layer">
                        <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                        <div class="card overlay-panel">
                            <div class="overlay-header">
                                <div>
                                    <h3>Crear empleado</h3>
                                    <span style="color:var(--muted);font-size:13px">Complete los datos del nuevo empleado.</span>
                                </div>
                                <button class="btn secondary sm overlay-close" type="button" data-overlay-close>Cerrar</button>
                            </div>
                            <form method="post" action="{{ route('admin.payroll.employees.store') }}">
                                @csrf
                                <div class="form-grid">
                                    <label>Código <span style="color:var(--bad)">*</span>
                                        <input type="text" name="code" value="{{ old('code') }}" required maxlength="20" placeholder="EMP-001">
                                    </label>
                                    <label>Nombre completo <span style="color:var(--bad)">*</span>
                                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
                                    </label>
                                    <label>DUI
                                        <input type="text" name="dui" value="{{ old('dui') }}" maxlength="10" placeholder="00000000-0">
                                    </label>
                                    <label>N.° ISSS
                                        <input type="text" name="isss_number" value="{{ old('isss_number') }}" maxlength="50">
                                    </label>
                                    <label>NUP (AFP)
                                        <input type="text" name="nup" value="{{ old('nup') }}" maxlength="50">
                                    </label>
                                    <label>AFP
                                        <select name="afp">
                                            <option value="crecer" @selected(old('afp','crecer')==='crecer')>Crecer</option>
                                            <option value="confia" @selected(old('afp')==='confia')>Confía</option>
                                        </select>
                                    </label>
                                    <label>Cargo
                                        <input type="text" name="position" value="{{ old('position') }}" maxlength="255">
                                    </label>
                                    <label>Departamento
                                        <input type="text" name="department" value="{{ old('department') }}" maxlength="255">
                                    </label>
                                    <label>Fecha de ingreso <span style="color:var(--bad)">*</span>
                                        <input type="date" name="hire_date" value="{{ old('hire_date') }}" required>
                                    </label>
                                    <label>Salario base ($) <span style="color:var(--bad)">*</span>
                                        <input type="number" name="salary" value="{{ old('salary') }}" required min="0" step="0.01">
                                    </label>
                                    <label>Banco
                                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="255">
                                    </label>
                                    <label>Cuenta bancaria
                                        <input type="text" name="bank_account" value="{{ old('bank_account') }}" maxlength="255">
                                    </label>
                                    <label class="span-full" style="flex-direction:row;align-items:center;gap:10px;display:flex;">
                                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active',1)) style="width:auto">
                                        Activo
                                    </label>
                                </div>
                                <div class="form-actions" style="margin-top:16px">
                                    <button class="btn" type="submit">Crear empleado</button>
                                    <button class="btn secondary" type="button" data-overlay-close>Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </details>
            </div>

            <div style="overflow-x:auto">
                <table class="nom-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>DUI</th>
                            <th>Cargo</th>
                            <th class="num">Salario Base</th>
                            <th>AFP</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            <tr>
                                <td><code>{{ $employee->code }}</code></td>
                                <td><strong>{{ $employee->name }}</strong></td>
                                <td>{{ $employee->dui ?? '—' }}</td>
                                <td>{{ $employee->position ?? '—' }}</td>
                                <td class="num">${{ number_format((float) $employee->salary, 2) }}</td>
                                <td>{{ ucfirst($employee->afp) }}</td>
                                <td>
                                    <span class="badge {{ $employee->is_active ? 'badge-active' : 'badge-inactive' }}">
                                        {{ $employee->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <details class="overlay-modal">
                                            <summary class="btn secondary sm">Editar</summary>
                                            <div class="overlay-layer">
                                                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                                <div class="card overlay-panel">
                                                    <div class="overlay-header">
                                                        <div>
                                                            <h3>Editar empleado</h3>
                                                            <span style="color:var(--muted);font-size:13px">{{ $employee->name }}</span>
                                                        </div>
                                                        <button class="btn secondary sm" type="button" data-overlay-close>Cerrar</button>
                                                    </div>
                                                    <form method="post" action="{{ route('admin.payroll.employees.update', $employee) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="form-grid">
                                                            <label>Código <span style="color:var(--bad)">*</span>
                                                                <input type="text" name="code" value="{{ $employee->code }}" required maxlength="20">
                                                            </label>
                                                            <label>Nombre completo <span style="color:var(--bad)">*</span>
                                                                <input type="text" name="name" value="{{ $employee->name }}" required maxlength="255">
                                                            </label>
                                                            <label>DUI
                                                                <input type="text" name="dui" value="{{ $employee->dui }}" maxlength="10">
                                                            </label>
                                                            <label>N.° ISSS
                                                                <input type="text" name="isss_number" value="{{ $employee->isss_number }}" maxlength="50">
                                                            </label>
                                                            <label>NUP (AFP)
                                                                <input type="text" name="nup" value="{{ $employee->nup }}" maxlength="50">
                                                            </label>
                                                            <label>AFP
                                                                <select name="afp">
                                                                    <option value="crecer" @selected($employee->afp==='crecer')>Crecer</option>
                                                                    <option value="confia" @selected($employee->afp==='confia')>Confía</option>
                                                                </select>
                                                            </label>
                                                            <label>Cargo
                                                                <input type="text" name="position" value="{{ $employee->position }}" maxlength="255">
                                                            </label>
                                                            <label>Departamento
                                                                <input type="text" name="department" value="{{ $employee->department }}" maxlength="255">
                                                            </label>
                                                            <label>Fecha de ingreso <span style="color:var(--bad)">*</span>
                                                                <input type="date" name="hire_date" value="{{ $employee->hire_date?->format('Y-m-d') }}" required>
                                                            </label>
                                                            <label>Salario base ($) <span style="color:var(--bad)">*</span>
                                                                <input type="number" name="salary" value="{{ $employee->salary }}" required min="0" step="0.01">
                                                            </label>
                                                            <label>Banco
                                                                <input type="text" name="bank_name" value="{{ $employee->bank_name }}" maxlength="255">
                                                            </label>
                                                            <label>Cuenta bancaria
                                                                <input type="text" name="bank_account" value="{{ $employee->bank_account }}" maxlength="255">
                                                            </label>
                                                            <label class="span-full" style="flex-direction:row;align-items:center;gap:10px;display:flex;">
                                                                <input type="checkbox" name="is_active" value="1" @checked($employee->is_active) style="width:auto">
                                                                Activo
                                                            </label>
                                                        </div>
                                                        <div class="form-actions" style="margin-top:16px">
                                                            <button class="btn" type="submit">Guardar cambios</button>
                                                            <button class="btn secondary" type="button" data-overlay-close>Cancelar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </details>
                                        <form method="post" action="{{ route('admin.payroll.employees.destroy', $employee) }}"
                                              data-confirm-delete="Eliminar/desactivar empleado">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn danger sm" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="empty">No hay empleados registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         TAB: NÓMINAS
    ═══════════════════════════════════════════════ --}}
    <div class="tab-section {{ $activeTab === 'nominas' ? 'active' : '' }}" data-section="nominas">
        <div class="card">
            <div class="card-section-head">
                <h3>Períodos de Nómina</h3>
                <details class="overlay-modal">
                    <summary class="btn">Nueva Nómina</summary>
                    <div class="overlay-layer">
                        <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                        <div class="card overlay-panel">
                            <div class="overlay-header">
                                <div>
                                    <h3>Crear período de nómina</h3>
                                    <span style="color:var(--muted);font-size:13px">Se calcularán automáticamente las líneas de todos los empleados activos.</span>
                                </div>
                                <button class="btn secondary sm" type="button" data-overlay-close>Cerrar</button>
                            </div>
                            <form id="payroll-period-form" method="post" action="{{ route('admin.payroll.periods.store') }}">
                                @csrf
                                <div class="form-grid">
                                    <label class="span-full">Nombre del período <span style="color:var(--bad)">*</span>
                                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej: Nómina Octubre 2026">
                                    </label>
                                    <label class="span-full">Seleccionar mes
                                        <input type="month" id="period-month" value="{{ old('period_month', now()->format('Y-m')) }}">
                                    </label>
                                    <label>Fecha inicio <span style="color:var(--bad)">*</span>
                                        <input type="date" id="period-start" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required>
                                    </label>
                                    <label>Fecha fin <span style="color:var(--bad)">*</span>
                                        <input type="date" id="period-end" name="period_end" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" required>
                                    </label>
                                    <label class="span-full">Notas
                                        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                                    </label>
                                </div>
                                <div class="form-actions" style="margin-top:16px">
                                    <button class="btn" type="submit">Crear y calcular</button>
                                    <button class="btn secondary" type="button" data-overlay-close>Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </details>
            </div>

            <div style="overflow-x:auto">
                <table class="nom-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Período</th>
                            <th class="num"># Empleados</th>
                            <th class="num">Total Neto</th>
                            <th>Estado</th>
                            <th>Fecha aplicación</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.payroll.periods.show', $period) }}" style="font-weight:700;color:var(--brand)">
                                        {{ $period->name }}
                                    </a>
                                </td>
                                <td style="white-space:nowrap">
                                    {{ $period->period_start?->format('d/m/Y') }} — {{ $period->period_end?->format('d/m/Y') }}
                                </td>
                                <td class="num">{{ $period->lines_count }}</td>
                                <td class="num">${{ number_format((float) $period->lines_sum_net_salary, 2) }}</td>
                                <td>
                                    <span class="badge {{ $period->isApplied() ? 'badge-applied' : 'badge-draft' }}">
                                        {{ $period->isApplied() ? 'Aplicado' : 'Borrador' }}
                                    </span>
                                </td>
                                <td>{{ $period->applied_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.payroll.periods.show', $period) }}" class="btn secondary sm">Ver</a>
                                        @if(!$period->isApplied())
                                            <form method="post" action="{{ route('admin.payroll.periods.destroy', $period) }}"
                                                  data-confirm-delete="Eliminar período de nómina">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn danger sm" type="submit">Eliminar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty">No hay períodos de nómina registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        @if(false)
        {{-- ═══════════════════════════════════════════════
            Secciones trasladadas a vistas independientes.
    ═══════════════════════════════════════════════ --}}
    <div class="tab-section {{ old('_tab') === 'conceptos' ? 'active' : '' }}" data-section="conceptos" id="conceptos">
        <div class="card" style="margin-bottom:18px">
            <div class="card-section-head"><h3>Conceptos legales integrados</h3></div>
            <div style="overflow-x:auto">
                <table class="nom-table">
                    <thead><tr><th>Concepto</th><th>Tipo</th><th>Fórmula aplicada</th><th>Cuentas contables</th></tr></thead>
                    <tbody>
                        @foreach($legalConcepts as $concept)
                            <tr>
                                <td><strong>{{ $concept['name'] }}</strong></td>
                                <td>{{ $concept['type'] }}</td>
                                <td><code>{{ $concept['formula'] }}</code></td>
                                <td>{{ $concept['account'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="padding:0 16px 14px;margin:0;color:var(--muted);font-size:12px">
                ISSS y AFP usan las tasas y el tope de Configuración. ISR usa la tabla progresiva existente en el cálculo de nómina.
            </p>
        </div>

        <div class="card" style="padding:20px 24px;margin-bottom:18px">
            <h3 style="margin:0 0 16px;font-size:16px">Nuevo concepto de nómina</h3>
            <form method="post" action="{{ route('admin.payroll.concepts.store') }}">
                @csrf
                <input type="hidden" name="_tab" value="conceptos">
                <div class="form-grid">
                    <label>Nombre
                        <input name="name" required maxlength="255" placeholder="Comisión por venta">
                    </label>
                    <label>Tipo
                        <select name="type" required>
                            <option value="beneficio">Beneficio</option>
                            <option value="deduccion">Deducción</option>
                        </select>
                    </label>
                    <label>Forma de cálculo
                        <select name="calculation_method" required>
                            <option value="fijo">Monto fijo</option>
                            <option value="porcentaje">Porcentaje del salario</option>
                            <option value="formula">Fórmula</option>
                            <option value="editable">Monto editable por período</option>
                        </select>
                    </label>
                    <label>Monto fijo ($)
                        <input type="number" name="amount" min="0" step="0.01" value="0">
                    </label>
                    <label>Porcentaje (%)
                        <input type="number" name="rate" min="0" max="100" step="0.0001" value="0">
                    </label>
                    <label>Fórmula
                        <input name="formula" maxlength="255" placeholder="salary * 0.10">
                    </label>
                    <label>Cuenta contable
                        <select name="account_id" required>
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Empleados específicos
                        <select name="employee_ids[]" multiple>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->position ?: 'Sin cargo' }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="span-full" style="display:flex;align-items:center;gap:9px">
                        <input type="checkbox" name="applies_to_all" value="1" style="width:auto">
                        Aplicar a todos los empleados activos
                    </label>
                </div>
                <p style="font-size:12px;color:var(--muted);margin:10px 0">En fórmulas use `salary`, `gross` y `overtime`, además de +, -, *, / y paréntesis. Los montos editables se ingresan en cada período.</p>
                <button class="btn" type="submit">Crear concepto</button>
            </form>
        </div>

        <div class="card">
            <div class="card-section-head"><h3>Gestión de conceptos ({{ $concepts->count() }})</h3></div>
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
                                                <div class="card overlay-panel wide" style="padding:20px">
                                                    <div class="overlay-header"><h3>Editar concepto: {{ $concept->name }}</h3><button class="btn secondary sm" type="button" data-overlay-close>Cerrar</button></div>
                                                    <form method="post" action="{{ route('admin.payroll.concepts.update', $concept) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="_tab" value="conceptos">
                                                        <div class="form-grid">
                                                            <label>Nombre<input name="name" value="{{ $concept->name }}" required maxlength="255"></label>
                                                            <label>Tipo<select name="type"><option value="beneficio" @selected($concept->type==='beneficio')>Beneficio</option><option value="deduccion" @selected($concept->type==='deduccion')>Deducción</option></select></label>
                                                            <label>Forma de cálculo<select name="calculation_method"><option value="fijo" @selected($concept->calculation_method==='fijo')>Monto fijo</option><option value="porcentaje" @selected($concept->calculation_method==='porcentaje')>Porcentaje del salario</option><option value="formula" @selected($concept->calculation_method==='formula')>Fórmula</option><option value="editable" @selected($concept->calculation_method==='editable')>Monto editable por período</option></select></label>
                                                            <label>Monto fijo ($)<input type="number" name="amount" value="{{ $concept->amount }}" min="0" step="0.01"></label>
                                                            <label>Porcentaje (%)<input type="number" name="rate" value="{{ $concept->rate }}" min="0" max="100" step="0.0001"></label>
                                                            <label>Fórmula<input name="formula" value="{{ $concept->formula }}" maxlength="255" placeholder="salary * 0.10"></label>
                                                            <label>Cuenta contable<select name="account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($concept->account_id===$account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                                                            <label>Empleados específicos<select name="employee_ids[]" multiple>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($concept->employees->contains('id',$employee->id))>{{ $employee->name }} ({{ $employee->position ?: 'Sin cargo' }})</option>@endforeach</select></label>
                                                            <label class="span-full" style="display:flex;align-items:center;gap:9px"><input type="checkbox" name="applies_to_all" value="1" @checked($concept->applies_to_all) style="width:auto">Aplicar a todos los empleados activos</label>
                                                        </div>
                                                        <p style="font-size:12px;color:var(--muted)">Variables: salary, gross, overtime. Operadores: +, -, *, / y paréntesis.</p>
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
                            <tr><td colspan="6" class="empty">Aún no hay conceptos configurados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         TAB: CONFIGURACIÓN
    ═══════════════════════════════════════════════ --}}
    <div class="tab-section" data-section="configuracion">
        <div class="card" style="padding:20px 24px">
            <form method="post" action="{{ route('admin.payroll.settings.save') }}">
                @csrf

                <h3 style="margin:0 0 16px;font-size:16px">Configuración de Nómina</h3>

                <p class="section-label">Tasas y límites</p>
                <div class="settings-grid">
                    <label>Tope salarial ISSS ($)
                        <input type="number" name="isss_salary_cap" value="{{ $settings->isss_salary_cap ?? 1000 }}" step="0.01" min="0">
                    </label>
                    <label>Tasa ISSS empleado
                        <input type="number" name="isss_employee_rate" value="{{ $settings->isss_employee_rate ?? 0.0300 }}" step="0.0001" min="0" max="1">
                    </label>
                    <label>Tasa ISSS patronal
                        <input type="number" name="isss_employer_rate" value="{{ $settings->isss_employer_rate ?? 0.0750 }}" step="0.0001" min="0" max="1">
                    </label>
                    <label>Tasa AFP empleado
                        <input type="number" name="afp_employee_rate" value="{{ $settings->afp_employee_rate ?? 0.0725 }}" step="0.0001" min="0" max="1">
                    </label>
                    <label>Tasa AFP patronal
                        <input type="number" name="afp_employer_rate" value="{{ $settings->afp_employer_rate ?? 0.0875 }}" step="0.0001" min="0" max="1">
                    </label>
                </div>

                <p class="section-label">Paquete contable</p>
                <div class="settings-grid">
                    <label>Paquete de nómina (NOM)
                        <select name="accounting_package_id">
                            <option value="">— Sin paquete —</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}" @selected($settings->accounting_package_id == $pkg->id)>
                                    {{ $pkg->code }} — {{ $pkg->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <p class="section-label">Cuentas de gasto (DEBE)</p>
                <div class="settings-grid">
                    <label>Sueldos y Salarios
                        <select name="account_salaries_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_salaries_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cuota Patronal ISSS (gasto)
                        <select name="account_isss_employer_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_isss_employer_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cuota Patronal AFP (gasto)
                        <select name="account_afp_employer_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_afp_employer_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <p class="section-label">Cuentas por pagar (HABER)</p>
                <div class="settings-grid">
                    <label>Cuota Laboral ISSS por Pagar
                        <select name="account_isss_employee_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_isss_employee_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cuota Patronal ISSS por Pagar
                        <select name="account_isss_employer_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_isss_employer_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cuota Laboral AFP por Pagar
                        <select name="account_afp_employee_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_afp_employee_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cuota Patronal AFP por Pagar
                        <select name="account_afp_employer_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_afp_employer_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>ISR Empleados Retenido por Pagar
                        <select name="account_isr_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_isr_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Salarios por Pagar
                        <select name="account_salaries_payable_id">
                            <option value="">— Seleccionar —</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected($settings->account_salaries_payable_id == $acc->id)>
                                    {{ $acc->code }} {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div style="margin-top:24px">
                    <button class="btn" type="submit">Guardar configuración</button>
                </div>
            </form>
        </div>
    </div>

    @endif

    <script>
        var periodMonth = document.getElementById('period-month');
        if (periodMonth) {
            var periodForm = document.getElementById('payroll-period-form');
            var periodName = periodForm.querySelector('input[name="name"]');
            var periodStart = document.getElementById('period-start');
            var periodEnd = document.getElementById('period-end');
            var periodNameEdited = periodName.value !== '';

            function applySelectedMonth() {
                if (!periodMonth.value) return;
                var parts = periodMonth.value.split('-');
                var year = Number(parts[0]);
                var month = Number(parts[1]);
                var monthText = new Intl.DateTimeFormat('es-SV', {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC'
                }).format(new Date(Date.UTC(year, month - 1, 1)));

                periodStart.value = year + '-' + parts[1] + '-01';
                periodEnd.value = year + '-' + parts[1] + '-' + String(new Date(year, month, 0).getDate()).padStart(2, '0');
                if (!periodNameEdited) periodName.value = 'Nómina ' + monthText;
            }

            periodName.addEventListener('input', function() {
                periodNameEdited = periodName.value !== '';
            });
            periodMonth.addEventListener('change', applySelectedMonth);
            if (!periodNameEdited) applySelectedMonth();
        }

        function activateTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(function(button) {
                button.classList.toggle('active', button.dataset.tab === tab);
            });
            document.querySelectorAll('.tab-section').forEach(function(section) {
                section.classList.toggle('active', section.dataset.section === tab);
            });
        }

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                activateTab(btn.dataset.tab);
                document.querySelectorAll('[data-payroll-tab]').forEach(function(link) {
                    link.classList.toggle('active-link', link.dataset.payrollTab === btn.dataset.tab);
                });
                var url = new URL(window.location.href);
                if (btn.dataset.tab === 'nominas') url.searchParams.set('tab', 'nominas');
                else url.searchParams.delete('tab');
                window.history.replaceState(null, '', url);
            });
        });

        // Overlay close
        document.addEventListener('click', function(e) {
            if (e.target.hasAttribute('data-overlay-close')) {
                var details = e.target.closest('details.overlay-modal');
                if (details) details.removeAttribute('open');
            }
        });

        // Confirm delete
        document.querySelectorAll('[data-confirm-delete]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!confirm(form.dataset.confirmDelete + '. ¿Desea continuar?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</x-layouts.app>
