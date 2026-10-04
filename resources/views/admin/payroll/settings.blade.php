<x-layouts.app title="Configuración de Nómina | Coteja">
    <style>
        .page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .page-head h1 { margin:0; font-size:24px; }
        .page-head p { margin:4px 0 0; color:var(--muted); font-size:14px; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .alert.ok { background:#ecfdf5; color:#166534; border-left:4px solid var(--ok); }
        .alert.bad { background:#fef2f2; color:#991b1b; border-left:4px solid var(--bad); }
        .settings-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
        .settings-grid label { display:grid; gap:5px; font-size:13px; font-weight:600; color:#374151; }
        .settings-grid input,.settings-grid select { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; box-sizing:border-box; }
        .section-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--muted); margin:20px 0 10px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font:inherit; font-weight:700; cursor:pointer; font-size:13px; text-decoration:none; }
        .btn.secondary { background:#4b5563; }
        @media(max-width:640px) { .settings-grid { grid-template-columns:1fr; } }
    </style>

    <div class="page-head">
        <div><h1>Configuración de Nómina</h1><p>Tasas legales, límites y cuenta puente del asiento.</p></div>
        <a class="btn secondary" href="{{ route('admin.payroll.index') }}">Empleados y nóminas</a>
    </div>

    @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="card" style="padding:20px 24px">
        <form method="post" action="{{ route('admin.payroll.settings.save') }}">
            @csrf
            <p class="section-label">Tasas y límites</p>
            <div class="settings-grid">
                <label>Tope salarial ISSS ($)<input type="number" name="isss_salary_cap" value="{{ $settings->isss_salary_cap ?? 1000 }}" step="0.01" min="0"></label>
                <label>Tasa ISSS empleado<input type="number" name="isss_employee_rate" value="{{ $settings->isss_employee_rate ?? 0.0300 }}" step="0.0001" min="0" max="1"></label>
                <label>Tasa ISSS patronal<input type="number" name="isss_employer_rate" value="{{ $settings->isss_employer_rate ?? 0.0750 }}" step="0.0001" min="0" max="1"></label>
                <label>Tasa AFP empleado<input type="number" name="afp_employee_rate" value="{{ $settings->afp_employee_rate ?? 0.0725 }}" step="0.0001" min="0" max="1"></label>
                <label>Tasa AFP patronal<input type="number" name="afp_employer_rate" value="{{ $settings->afp_employer_rate ?? 0.0875 }}" step="0.0001" min="0" max="1"></label>
            </div>

            <p class="section-label">Cuenta pendiente para cuadrar la partida (HABER)</p>
            <div class="settings-grid">
                <label>Salarios por pagar
                    <select name="account_salaries_payable_id" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($accounts->where('type', 'pasivo') as $account)
                            <option value="{{ $account->id }}" @selected($settings->account_salaries_payable_id == $account->id)>{{ $account->code }} {{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div style="margin-top:24px"><button class="btn" type="submit">Guardar configuración</button></div>
        </form>
    </div>
</x-layouts.app>