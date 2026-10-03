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
        .section-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin:20px 0 10px; }
    </style>

    <div class="nom-top">
        <div>
            <h1>Control de Nómina</h1>
            <p>Empleados, períodos y configuración</p>
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
        <button class="tab-btn active" data-tab="empleados">Empleados</button>
        <button class="tab-btn" data-tab="nominas">Nóminas</button>
        <button class="tab-btn" data-tab="configuracion">Configuración</button>
    </div>

    {{-- ═══════════════════════════════════════════════
         TAB: EMPLEADOS
    ═══════════════════════════════════════════════ --}}
    <div class="tab-section active" data-section="empleados">
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
    <div class="tab-section" data-section="nominas">
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
                            <form method="post" action="{{ route('admin.payroll.periods.store') }}">
                                @csrf
                                <div class="form-grid">
                                    <label class="span-full">Nombre del período <span style="color:var(--bad)">*</span>
                                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej: Nómina Octubre 2026">
                                    </label>
                                    <label>Fecha inicio <span style="color:var(--bad)">*</span>
                                        <input type="date" name="period_start" value="{{ old('period_start') }}" required>
                                    </label>
                                    <label>Fecha fin <span style="color:var(--bad)">*</span>
                                        <input type="date" name="period_end" value="{{ old('period_end') }}" required>
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

    <script>
        // Tabs
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tab = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
                document.querySelectorAll('.tab-section').forEach(function(s) { s.classList.remove('active'); });
                btn.classList.add('active');
                document.querySelector('[data-section="' + tab + '"]').classList.add('active');
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
