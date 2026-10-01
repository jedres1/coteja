<x-layouts.app title="Conciliación bancaria | Coteja">
    @php
        $money = fn($v) => '$'.number_format((float)$v, 2);
        $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $statusLabels = ['borrador'=>'Borrador','completado'=>'Completado'];
        $statusColors = ['borrador'=>'background:#fef3c7;color:#92400e','completado'=>'background:#dcfce7;color:#166534'];
    @endphp
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
        .btn.secondary{background:#4b5563}.btn.sm{min-height:30px;padding:5px 10px;font-size:12px}
        .actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
        .alert-info{padding:10px 12px;border-radius:8px;background:#eff6ff;color:#1e40af;margin-bottom:14px;font-size:13px}
    </style>

    <div class="page-header">
        <h1>Conciliación bancaria</h1>
        <p class="muted">Confronte las transacciones registradas contra los estados de cuenta bancarios mensuales.</p>
    </div>

    @if($bankAccounts->isEmpty())
        <div class="card">
            <div class="empty-state">
                Debe <a href="{{ route('admin.bank-transactions.accounts') }}" style="color:var(--brand)">registrar al menos una cuenta bancaria</a> antes de crear una conciliación.
            </div>
        </div>
    @else
        <div class="card" style="margin-bottom:24px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <h2 class="section-title" style="margin:0">Conciliaciones registradas</h2>
                <button class="btn" type="button" onclick="document.getElementById('new-recon-overlay').style.display='flex'">Nueva conciliación</button>
            </div>

            @if($reconciliations->isEmpty())
                <div class="empty-state">No hay conciliaciones registradas.</div>
            @else
                <table class="bt-table">
                    <thead>
                        <tr><th>Período</th><th>Cuenta</th><th>Saldo estado de cuenta</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        @foreach($reconciliations as $recon)
                            <tr>
                                <td><strong>{{ $months[$recon->period_month] }} {{ $recon->period_year }}</strong></td>
                                <td>{{ $recon->bankAccount->name }}<br><span class="muted" style="font-size:11px">{{ $recon->bankAccount->bank_name }}</span></td>
                                <td>{{ $recon->statement_balance !== null ? $money($recon->statement_balance) : '—' }}</td>
                                <td><span class="badge" style="{{ $statusColors[$recon->status] ?? '' }}">{{ $statusLabels[$recon->status] ?? $recon->status }}</span></td>
                                <td>
                                    <a class="btn secondary sm" href="{{ route('admin.bank-transactions.reconciliations.show', $recon) }}">Ver / Conciliar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- New reconciliation overlay --}}
        <div id="new-recon-overlay" class="overlay-layer" style="display:none">
            <button class="overlay-backdrop" type="button" onclick="document.getElementById('new-recon-overlay').style.display='none'"></button>
            <div class="card overlay-panel">
                <div class="overlay-header">
                    <div><h3>Nueva conciliación</h3><span class="muted" style="font-size:12px">Seleccione la cuenta y el mes a conciliar.</span></div>
                    <button class="btn secondary sm" type="button" onclick="document.getElementById('new-recon-overlay').style.display='none'">Cerrar</button>
                </div>
                <div class="alert-info">La conciliación se hace por mes. Necesitará el estado de cuenta PDF de su banco para ese período.</div>
                <form method="post" action="{{ route('admin.bank-transactions.reconciliations.store') }}">
                    @csrf
                    <div class="form-grid">
                        <label class="span-full">Cuenta bancaria <span style="color:var(--bad)">*</span>
                            <select name="bank_account_id" required>
                                <option value="">Seleccionar cuenta...</option>
                                @foreach($bankAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} — {{ $account->bank_name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Año <span style="color:var(--bad)">*</span><input type="number" name="period_year" value="{{ now()->year }}" min="2020" max="2100" required></label>
                        <label>Mes <span style="color:var(--bad)">*</span>
                            <select name="period_month" required>
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>{{ $months[$m] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Saldo según estado de cuenta<input type="number" name="statement_balance" step="0.01" min="0" placeholder="0.00"></label>
                        <label class="span-full">Notas<textarea name="notes" rows="2" maxlength="1000"></textarea></label>
                    </div>
                    <div class="actions-row">
                        <button class="btn" type="submit">Crear conciliación</button>
                        <button class="btn secondary" type="button" onclick="document.getElementById('new-recon-overlay').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.app>
