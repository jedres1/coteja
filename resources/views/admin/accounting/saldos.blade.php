<x-layouts.app title="Saldos por Período | Coteja">
@php
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $typeLabels = [
        'activo'     => 'Activo',
        'pasivo'     => 'Pasivo',
        'patrimonio' => 'Patrimonio',
        'ingreso'    => 'Ingresos',
        'gasto'      => 'Gastos',
        'costo'      => 'Costos',
        'orden'      => 'Cuentas de Orden',
    ];

    $typeOrder = ['activo','pasivo','patrimonio','ingreso','gasto','costo','orden'];

    $totalDebe  = $balances->sum('total_debit');
    $totalHaber = $balances->sum('total_credit');
    $totalSaldo = $balances->sum('saldo');

    $periodStatus = $period?->status ?? null;
    $periodLabel  = $period?->full_name ?? ($months[$month] ?? $month).' '.$year;
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Saldos por Período</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Consulte los saldos de cuentas contables basados en partidas aprobadas.
        </p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.accounting.diario.index') }}" class="btn btn-ghost btn-sm">← Diario</a>
    </div>
</div>

{{-- Filtros --}}
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div style="display:flex;flex-direction:column;gap:.25rem">
            <label style="font-size:.75rem;font-weight:600;color:#374151">Año</label>
            <select name="year" class="input input-sm" style="min-width:90px">
                @foreach(range(now()->year + 1, 2020) as $y)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:.25rem">
            <label style="font-size:.75rem;font-weight:600;color:#374151">Mes</label>
            <select name="month" class="input input-sm" style="min-width:130px">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $months[$m] }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:.25rem">
            <label style="font-size:.75rem;font-weight:600;color:#374151">Cuenta (opcional)</label>
            <select name="account_id" class="input input-sm" style="min-width:220px">
                <option value="">— Todas las cuentas —</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ $acc->id == $accountId ? 'selected' : '' }}>
                        {{ $acc->code }} — {{ $acc->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:.4rem;padding-bottom:.15rem">
            <input type="checkbox" id="acumulado" name="acumulado" value="1" {{ $acumulado ? 'checked' : '' }}
                   style="width:1rem;height:1rem;cursor:pointer">
            <label for="acumulado" style="font-size:.875rem;cursor:pointer;user-select:none">
                Acumulado (Enero–{{ $months[$month] ?? $month }})
            </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
    </form>
</div>

{{-- Info del período --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:stretch">
    <div style="flex:1;min-width:180px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Período consultado</div>
        <div style="font-size:1rem;font-weight:700;color:#111827;margin-top:.25rem">
            {{ $acumulado ? 'Ene – '.$months[$month] : $periodLabel }} {{ $year }}
        </div>
        @if($acumulado)
            <div style="font-size:.75rem;color:#6b7280">Saldo acumulado</div>
        @endif
    </div>
    <div style="flex:1;min-width:180px;border-radius:.5rem;padding:.75rem 1rem;
        {{ $periodStatus === 'abierto' ? 'background:#f0fdf4;border:1px solid #86efac' : ($periodStatus === 'cerrado' ? 'background:#f9fafb;border:1px solid #e5e7eb' : 'background:#fffbeb;border:1px solid #fde68a') }}">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:{{ $periodStatus === 'abierto' ? '#166534' : ($periodStatus === 'cerrado' ? '#6b7280' : '#92400e') }}">
            Estado del período {{ $months[$month] ?? $month }}
        </div>
        <div style="font-size:1rem;font-weight:700;margin-top:.25rem;color:{{ $periodStatus === 'abierto' ? '#16a34a' : ($periodStatus === 'cerrado' ? '#374151' : '#92400e') }}">
            @if($periodStatus === 'abierto') Abierto
            @elseif($periodStatus === 'cerrado') Cerrado
            @else Sin período registrado
            @endif
        </div>
        @if($period?->closed_at)
            <div style="font-size:.75rem;color:#6b7280">Cerrado {{ $period->closed_at->format('d/m/Y') }}</div>
        @endif
    </div>
    <div style="flex:1;min-width:140px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Cuentas con movimiento</div>
        <div style="font-size:1.5rem;font-weight:700;color:#111827">{{ $balances->count() }}</div>
    </div>
    <div style="flex:1;min-width:140px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Total Debe</div>
        <div style="font-size:1rem;font-weight:700;color:#374151">{{ number_format($totalDebe, 2) }}</div>
    </div>
    <div style="flex:1;min-width:140px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Total Haber</div>
        <div style="font-size:1rem;font-weight:700;color:#374151">{{ number_format($totalHaber, 2) }}</div>
    </div>
</div>

@if($balances->isEmpty())
    <div class="card" style="padding:2.5rem;text-align:center;color:#6b7280">
        No hay partidas aprobadas para el período
        <strong>{{ $acumulado ? 'Ene – '.$months[$month] : $periodLabel }} {{ $year }}</strong>
        @if($accountId) con la cuenta seleccionada @endif.
    </div>
@else

{{-- Tabla de saldos --}}
<div class="card" style="overflow:hidden">
    @php
        $groupedBalances = $balances->groupBy('type');
        $orderedTypes = collect($typeOrder)->filter(fn($t) => $groupedBalances->has($t));
    @endphp

    @foreach($orderedTypes as $type)
        @php $group = $groupedBalances[$type]; $summary = $summaryByType[$type] ?? null; @endphp
        <div style="padding:.625rem 1.25rem;background:#f9fafb;border-bottom:2px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:.8125rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151">
                {{ $typeLabels[$type] ?? ucfirst($type) }}
            </span>
            @if($summary)
            <span style="font-size:.8125rem;color:#6b7280">
                Debe: <strong>{{ number_format($summary['total_debit'], 2) }}</strong> &nbsp;|&nbsp;
                Haber: <strong>{{ number_format($summary['total_credit'], 2) }}</strong> &nbsp;|&nbsp;
                Saldo: <strong style="color:{{ $summary['saldo'] >= 0 ? '#166534' : '#dc2626' }}">
                    {{ number_format(abs($summary['saldo']), 2) }}
                    {{ $summary['saldo'] < 0 ? '(—)' : '' }}
                </strong>
            </span>
            @endif
        </div>
        <table class="table" style="margin-bottom:0">
            <thead>
                <tr>
                    <th style="width:110px">Código</th>
                    <th>Nombre de la cuenta</th>
                    <th style="width:80px;text-align:center">Naturaleza</th>
                    <th style="width:130px;text-align:right">Debe</th>
                    <th style="width:130px;text-align:right">Haber</th>
                    <th style="width:130px;text-align:right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group as $row)
                <tr>
                    <td style="font-family:monospace;font-size:.875rem;font-weight:600;color:#374151">{{ $row->code }}</td>
                    <td style="font-size:.875rem">{{ $row->name }}</td>
                    <td style="text-align:center">
                        <span style="font-size:.75rem;padding:.15rem .5rem;border-radius:.25rem;
                            {{ $row->nature === 'deudora' ? 'background:#dbeafe;color:#1d4ed8' : 'background:#fce7f3;color:#9d174d' }}">
                            {{ ucfirst($row->nature) }}
                        </span>
                    </td>
                    <td style="text-align:right;font-size:.875rem;font-family:monospace;color:#374151">
                        {{ $row->total_debit > 0 ? number_format($row->total_debit, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-size:.875rem;font-family:monospace;color:#374151">
                        {{ $row->total_credit > 0 ? number_format($row->total_credit, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-size:.875rem;font-family:monospace;font-weight:600;
                        color:{{ $row->saldo >= 0 ? '#166534' : '#dc2626' }}">
                        {{ number_format(abs($row->saldo), 2) }}
                        @if($row->saldo < 0)
                            <span style="font-size:.7rem;font-weight:400">(—)</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    {{-- Totales generales --}}
    <div style="padding:.875rem 1.25rem;border-top:2px solid #374151;background:#f3f4f6;display:flex;justify-content:flex-end;gap:2.5rem">
        <div style="text-align:right">
            <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Total Debe</div>
            <div style="font-size:1rem;font-weight:700;font-family:monospace;color:#111827">{{ number_format($totalDebe, 2) }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Total Haber</div>
            <div style="font-size:1rem;font-weight:700;font-family:monospace;color:#111827">{{ number_format($totalHaber, 2) }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Diferencia</div>
            @php $diff = $totalDebe - $totalHaber; @endphp
            <div style="font-size:1rem;font-weight:700;font-family:monospace;color:{{ abs($diff) < 0.01 ? '#166534' : '#dc2626' }}">
                {{ number_format(abs($diff), 2) }}
                @if(abs($diff) < 0.01)
                    <span style="font-size:.75rem;font-weight:400">✓ Cuadrado</span>
                @endif
            </div>
        </div>
    </div>
</div>

@endif

<style>
.input-sm { padding:.3rem .6rem; font-size:.875rem }
.btn-sm   { padding:.3rem .6rem; font-size:.8125rem }
</style>
</x-layouts.app>
