<x-layouts.app title="Nómina: {{ $period->name }} | Coteja">
    @php
        $money = fn($v) => '$'.number_format((float) $v, 2);
        $lines = $period->lines;
    @endphp

    <style>
        .nom-breadcrumb { font-size:13px; color:var(--muted); margin-bottom:12px; }
        .nom-breadcrumb a { color:var(--brand); text-decoration:none; }
        .nom-breadcrumb a:hover { text-decoration:underline; }
        .nom-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .nom-header h1 { margin:0; font-size:24px; }
        .header-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .alert.ok  { background:#ecfdf5; color:#166534; border-left:4px solid var(--ok); }
        .alert.bad { background:#fef2f2; color:#991b1b; border-left:4px solid var(--bad); }
        .badge { display:inline-flex; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .badge-draft { background:#fef3c7; color:#92400e; }
        .badge-applied { background:#dcfce7; color:#166534; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .btn.secondary { background:#4b5563; }
        .btn.sm { min-height:28px; padding:5px 10px; font-size:12px; }
        .btn.danger { background:var(--bad); }
        /* table */
        .period-wrap { overflow-x:auto; }
        .period-table { width:100%; border-collapse:collapse; font-size:12px; white-space:nowrap; }
        .period-table th, .period-table td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:right; vertical-align:middle; }
        .period-table th { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); background:#f9fafb; }
        .period-table th.left, .period-table td.left { text-align:left; }
        .period-table tr:last-child td { border-bottom:0; }
        .period-table tfoot td { font-weight:700; background:#f9fafb; border-top:2px solid var(--line); }
        .empty { padding:40px; text-align:center; color:var(--muted); }
        /* overlay */
        .overlay-modal { position:relative; display:inline-block; }
        .overlay-modal > summary { list-style:none; cursor:pointer; }
        .overlay-modal > summary::-webkit-details-marker { display:none; }
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; min-width:320px; max-width:500px; width:100%; max-height:92vh; overflow-y:auto; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; position:sticky; top:0; background:var(--panel); padding-bottom:12px; border-bottom:1px solid var(--line); }
        .overlay-header h3 { margin:0; font-size:16px; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
        .form-grid label { display:grid; gap:5px; font-size:13px; font-weight:600; color:#374151; }
        .form-grid input, .form-grid textarea { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; box-sizing:border-box; }
        .form-grid textarea { min-height:60px; }
        .span-full { grid-column:1/-1; }
        .form-actions { display:flex; gap:10px; margin-top:14px; }
        .info-row { display:flex; gap:24px; flex-wrap:wrap; margin-bottom:20px; }
        .info-item { }
        .info-item .lbl { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); letter-spacing:.04em; }
        .info-item .val { font-size:15px; font-weight:700; margin-top:2px; }
    </style>

    <div class="nom-breadcrumb">
        <a href="{{ route('admin.payroll.index') }}">Control de Nómina</a>
        &rsaquo; {{ $period->name }}
    </div>

    <div class="nom-header">
        <div>
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                <h1>{{ $period->name }}</h1>
                <span class="badge {{ $period->isApplied() ? 'badge-applied' : 'badge-draft' }}">
                    {{ $period->isApplied() ? 'APLICADA' : 'BORRADOR' }}
                </span>
                @if($period->isApplied() && $period->journalEntry)
                    <a href="{{ route('admin.accounting.diario.show', $period->journalEntry) }}"
                       style="font-size:12px;color:var(--brand)">
                        Partida: {{ $period->journalEntry->entry_number }}
                    </a>
                @endif
            </div>
            <span style="color:var(--muted);font-size:13px">
                {{ $period->period_start?->format('d/m/Y') }} — {{ $period->period_end?->format('d/m/Y') }}
                @if($period->applied_at)
                    &nbsp;·&nbsp; Aplicada el {{ $period->applied_at->format('d/m/Y H:i') }}
                @endif
            </span>
        </div>
        <div class="header-actions">
            @if(!$period->isApplied())
                <form method="post" action="{{ route('admin.payroll.periods.apply', $period) }}"
                      data-confirm-apply="Aplicar nómina y generar partida contable">
                    @csrf
                    <button class="btn" type="submit">Aplicar Nómina</button>
                </form>
                <form method="post" action="{{ route('admin.payroll.periods.destroy', $period) }}"
                      data-confirm-delete="Eliminar período de nómina">
                    @csrf
                    @method('DELETE')
                    <button class="btn danger" type="submit">Eliminar Período</button>
                </form>
            @endif
            <a href="{{ route('admin.payroll.index') }}" class="btn secondary">Volver</a>
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

    {{-- Summary --}}
    <div class="info-row" style="margin-bottom:20px">
        <div class="info-item">
            <div class="lbl">Empleados</div>
            <div class="val">{{ $lines->count() }}</div>
        </div>
        <div class="info-item">
            <div class="lbl">Total Bruto</div>
            <div class="val">{{ $money($lines->sum('gross_salary')) }}</div>
        </div>
        <div class="info-item">
            <div class="lbl">Total Deducciones</div>
            <div class="val">{{ $money($lines->sum('total_deductions')) }}</div>
        </div>
        <div class="info-item">
            <div class="lbl">Total Neto</div>
            <div class="val" style="color:var(--brand)">{{ $money($lines->sum('net_salary')) }}</div>
        </div>
        <div class="info-item">
            <div class="lbl">Costo Total Patronal</div>
            <div class="val">{{ $money($lines->sum('total_employer_cost')) }}</div>
        </div>
    </div>

    {{-- Lines table --}}
    <div class="card">
        <div class="period-wrap">
            <table class="period-table">
                <thead>
                    <tr>
                        <th class="left">Empleado</th>
                        <th>Salario Base</th>
                        <th>H.Extra</th>
                        <th>Extra ($)</th>
                        <th>Bonos</th>
                        <th>Bruto</th>
                        <th>ISSS Lab.</th>
                        <th>AFP Lab.</th>
                        <th>ISR</th>
                        <th>Deduc.</th>
                        <th>Neto</th>
                        <th>ISSS Pat.</th>
                        <th>AFP Pat.</th>
                        <th>Costo Total</th>
                        @if(!$period->isApplied())
                            <th class="left">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        <tr>
                            <td class="left">
                                <strong>{{ $line->employee->name }}</strong>
                                <br><span style="color:var(--muted);font-size:11px">{{ $line->employee->code }}</span>
                                @foreach($line->concept_details ?? [] as $concept)
                                    <br><span style="color:var(--muted);font-size:11px">{{ $concept['name'] }}: {{ $money($concept['amount']) }}</span>
                                @endforeach
                                @if($line->notes)
                                    <br><span style="color:var(--muted);font-size:11px;font-style:italic">{{ $line->notes }}</span>
                                @endif
                            </td>
                            <td>{{ $money($line->salary) }}</td>
                            <td>{{ number_format((float) $line->overtime_hours, 2) }}</td>
                            <td>{{ $money($line->overtime_amount) }}</td>
                            <td>{{ $money($line->bonuses) }}</td>
                            <td style="font-weight:700">{{ $money($line->gross_salary) }}</td>
                            <td>{{ $money($line->isss_employee) }}</td>
                            <td>{{ $money($line->afp_employee) }}</td>
                            <td>{{ $money($line->isr) }}</td>
                            <td>{{ $money($line->total_deductions) }}</td>
                            <td style="font-weight:700;color:var(--brand)">{{ $money($line->net_salary) }}</td>
                            <td>{{ $money($line->isss_employer) }}</td>
                            <td>{{ $money($line->afp_employer) }}</td>
                            <td style="font-weight:700">{{ $money($line->total_employer_cost) }}</td>
                            @if(!$period->isApplied())
                                <td class="left">
                                    <details class="overlay-modal">
                                        <summary class="btn secondary sm">Editar</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                            <div class="card overlay-panel">
                                                <div class="overlay-header">
                                                    <div>
                                                        <h3>Editar línea</h3>
                                                        <span style="color:var(--muted);font-size:13px">{{ $line->employee->name }}</span>
                                                    </div>
                                                    <button class="btn secondary sm" type="button" data-overlay-close>Cerrar</button>
                                                </div>
                                                <form method="post" action="{{ route('admin.payroll.lines.update', $line) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="form-grid">
                                                        <label>Horas extra
                                                            <input type="number" name="overtime_hours" value="{{ $line->overtime_hours }}" min="0" step="0.01">
                                                        </label>
                                                        <label>Monto horas extra ($)
                                                            <input type="number" name="overtime_amount" value="{{ $line->overtime_amount }}" min="0" step="0.01">
                                                        </label>
                                                        <label>Bonificaciones ($)
                                                            <input type="number" name="bonuses" value="{{ $line->bonuses }}" min="0" step="0.01">
                                                        </label>
                                                        @foreach($line->concept_details ?? [] as $concept)
                                                            @if($concept['calculation_method'] === 'editable')
                                                                <label>{{ $concept['name'] }} ($)
                                                                    <input type="number" name="concept_inputs[{{ $concept['concept_id'] }}]" value="{{ $concept['amount'] }}" min="0" step="0.01">
                                                                </label>
                                                            @endif
                                                        @endforeach
                                                        <label class="span-full">Notas
                                                            <textarea name="notes" rows="2">{{ $line->notes }}</textarea>
                                                        </label>
                                                    </div>
                                                    <div class="form-actions">
                                                        <button class="btn" type="submit">Guardar</button>
                                                        <button class="btn secondary" type="button" data-overlay-close>Cancelar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $period->isApplied() ? 14 : 15 }}" class="empty">
                                No hay líneas en este período.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($lines->isNotEmpty())
                <tfoot>
                    <tr>
                        <td class="left">TOTALES</td>
                        <td>{{ $money($lines->sum('salary')) }}</td>
                        <td>{{ number_format((float) $lines->sum('overtime_hours'), 2) }}</td>
                        <td>{{ $money($lines->sum('overtime_amount')) }}</td>
                        <td>{{ $money($lines->sum('bonuses')) }}</td>
                        <td>{{ $money($lines->sum('gross_salary')) }}</td>
                        <td>{{ $money($lines->sum('isss_employee')) }}</td>
                        <td>{{ $money($lines->sum('afp_employee')) }}</td>
                        <td>{{ $money($lines->sum('isr')) }}</td>
                        <td>{{ $money($lines->sum('total_deductions')) }}</td>
                        <td>{{ $money($lines->sum('net_salary')) }}</td>
                        <td>{{ $money($lines->sum('isss_employer')) }}</td>
                        <td>{{ $money($lines->sum('afp_employer')) }}</td>
                        <td>{{ $money($lines->sum('total_employer_cost')) }}</td>
                        @if(!$period->isApplied())
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <script>
        // Overlay close
        document.addEventListener('click', function(e) {
            if (e.target.hasAttribute('data-overlay-close')) {
                var details = e.target.closest('details.overlay-modal');
                if (details) details.removeAttribute('open');
            }
        });

        // Confirm apply
        document.querySelectorAll('[data-confirm-apply]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!confirm(form.dataset.confirmApply + '. ¿Desea continuar?')) {
                    e.preventDefault();
                }
            });
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
