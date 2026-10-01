<x-layouts.app title="Balanza de Comprobación | Coteja">
@php
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $typeLabels = [
        'activo'     => 'Activo',
        'pasivo'     => 'Pasivo',
        'patrimonio' => 'Patrimonio',
        'ingreso'    => 'Ingreso',
        'gasto'      => 'Gasto',
        'costo'      => 'Costo',
        'orden'      => 'Cuentas de Orden',
    ];

    $periodLabel = $acumulado
        ? 'Ene–'.($months[$month] ?? $month).' '.$year
        : ($months[$month] ?? $month).' '.$year;

    $cuadradoSumas   = abs($totalSumaDebe - $totalSumaHaber) < 0.01;
    $cuadradoSaldos  = abs($totalSaldoDeudor - $totalSaldoAcreedor) < 0.01;

    $groupedRows = $rows->groupBy('type');
    $typeOrder   = ['activo','pasivo','patrimonio','ingreso','gasto','costo','orden'];
    $orderedTypes = collect($typeOrder)->filter(fn($t) => $groupedRows->has($t));
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Balanza de Comprobación</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Sumas y saldos de todas las cuentas con movimiento — solo partidas aprobadas.
        </p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-ghost btn-sm">Imprimir</button>
    </div>
</div>

{{-- Filtros --}}
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem">
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div style="display:flex;flex-direction:column;gap:.25rem">
            <label style="font-size:.75rem;font-weight:600;color:#374151">Año</label>
            <select name="year" class="input input-sm">
                @foreach($availableYears as $y)
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
            <label style="font-size:.75rem;font-weight:600;color:#374151">Tipo de cuenta</label>
            <select name="tipo" class="input input-sm" style="min-width:150px">
                <option value="">— Todos —</option>
                @foreach($typeLabels as $key => $label)
                    <option value="{{ $key }}" {{ $tipo === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;align-items:center;gap:.4rem;padding-bottom:.15rem">
            <input type="checkbox" id="acumulado" name="acumulado" value="1" {{ $acumulado ? 'checked' : '' }}
                   style="width:1rem;height:1rem;cursor:pointer">
            <label for="acumulado" style="font-size:.875rem;cursor:pointer;user-select:none">
                Acumulado (Ene–{{ $months[$month] ?? $month }})
            </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
    </form>
</div>

{{-- KPI: estado de cuadre --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="flex:1;min-width:160px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Cuentas con movimiento</div>
        <div style="font-size:1.5rem;font-weight:700;color:#111827">{{ $rows->count() }}</div>
    </div>
    <div style="flex:2;min-width:200px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Sumas</div>
        <div style="display:flex;gap:1.5rem;margin-top:.2rem">
            <div>
                <div style="font-size:.7rem;color:#6b7280">Debe</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#374151">{{ number_format($totalSumaDebe, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#6b7280">Haber</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#374151">{{ number_format($totalSumaHaber, 2) }}</div>
            </div>
            <div style="border-left:1px solid #e5e7eb;padding-left:1.5rem">
                <div style="font-size:.7rem;color:{{ $cuadradoSumas ? '#166534' : '#991b1b' }}">{{ $cuadradoSumas ? '✓ Cuadrado' : '✗ No cuadra' }}</div>
                <div style="font-family:monospace;font-weight:700;font-size:.875rem;color:{{ $cuadradoSumas ? '#16a34a' : '#dc2626' }}">
                    Dif: {{ number_format(abs($totalSumaDebe - $totalSumaHaber), 2) }}
                </div>
            </div>
        </div>
    </div>
    <div style="flex:2;min-width:200px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Saldos</div>
        <div style="display:flex;gap:1.5rem;margin-top:.2rem">
            <div>
                <div style="font-size:.7rem;color:#6b7280">Deudor</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#1d4ed8">{{ number_format($totalSaldoDeudor, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#6b7280">Acreedor</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#9d174d">{{ number_format($totalSaldoAcreedor, 2) }}</div>
            </div>
            <div style="border-left:1px solid #e5e7eb;padding-left:1.5rem">
                <div style="font-size:.7rem;color:{{ $cuadradoSaldos ? '#166534' : '#991b1b' }}">{{ $cuadradoSaldos ? '✓ Cuadrado' : '✗ No cuadra' }}</div>
                <div style="font-family:monospace;font-weight:700;font-size:.875rem;color:{{ $cuadradoSaldos ? '#16a34a' : '#dc2626' }}">
                    Dif: {{ number_format(abs($totalSaldoDeudor - $totalSaldoAcreedor), 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

@if($rows->isEmpty())
    <div class="card" style="padding:2.5rem;text-align:center;color:#6b7280">
        No hay partidas aprobadas para el período <strong>{{ $periodLabel }}</strong>
        @if($tipo) con tipo de cuenta <strong>{{ $typeLabels[$tipo] ?? $tipo }}</strong>@endif.
    </div>
@else

<div class="card" style="overflow:hidden">
    {{-- Encabezado del reporte --}}
    <div style="padding:.75rem 1.25rem;background:#111827;color:#fff;display:flex;justify-content:space-between;align-items:center">
        <div>
            <div style="font-weight:700;font-size:.9375rem">Balanza de Comprobación de Saldos</div>
            <div style="font-size:.75rem;opacity:.7;margin-top:.1rem">
                {{ $periodLabel }}
                @if($period)
                    &nbsp;·&nbsp; Estado: {{ $period->status === 'abierto' ? 'Abierto' : 'Cerrado' }}
                @endif
                @if($tipo) &nbsp;·&nbsp; {{ $typeLabels[$tipo] ?? $tipo }} @endif
            </div>
        </div>
        <div style="display:flex;gap:1rem;text-align:right">
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6">Sumas D=H</div>
                <div style="font-size:.875rem;font-weight:700;color:{{ $cuadradoSumas ? '#4ade80' : '#f87171' }}">
                    {{ $cuadradoSumas ? '✓' : '✗' }}
                </div>
            </div>
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6">Saldos D=H</div>
                <div style="font-size:.875rem;font-weight:700;color:{{ $cuadradoSaldos ? '#4ade80' : '#f87171' }}">
                    {{ $cuadradoSaldos ? '✓' : '✗' }}
                </div>
            </div>
        </div>
    </div>

    <table class="table balanza-table">
        <thead>
            <tr>
                <th style="width:90px">Código</th>
                <th>Nombre de la cuenta</th>
                <th style="width:80px;text-align:center">Tipo</th>
                <th style="width:120px;text-align:right;background:#fafaf9">Suma Debe</th>
                <th style="width:120px;text-align:right;background:#fafaf9">Suma Haber</th>
                <th style="width:120px;text-align:right;background:#eff6ff">Saldo Deudor</th>
                <th style="width:120px;text-align:right;background:#fdf2f8">Saldo Acreedor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orderedTypes as $type)
                {{-- Separador de grupo --}}
                <tr>
                    <td colspan="7" style="padding:.4rem .75rem;background:#f3f4f6;border-bottom:1px solid #d1d5db">
                        <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151">
                            {{ $typeLabels[$type] ?? ucfirst($type) }}
                        </span>
                    </td>
                </tr>
                @php $group = $groupedRows[$type]; @endphp
                @foreach($group as $row)
                <tr>
                    <td style="font-family:monospace;font-size:.8125rem;font-weight:600;color:#374151">{{ $row->code }}</td>
                    <td style="font-size:.8125rem">
                        {{ $row->name }}
                        <span style="font-size:.7rem;color:#9ca3af;margin-left:.25rem">· {{ $row->nature === 'deudora' ? 'D' : 'A' }}</span>
                    </td>
                    <td style="text-align:center">
                        <span style="font-size:.7rem;padding:.1rem .4rem;border-radius:.2rem;background:#f3f4f6;color:#374151">
                            {{ $typeLabels[$row->type] ?? $row->type }}
                        </span>
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151;background:#fafaf9">
                        {{ $row->suma_debe > 0 ? number_format($row->suma_debe, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151;background:#fafaf9">
                        {{ $row->suma_haber > 0 ? number_format($row->suma_haber, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:{{ $row->saldo_deudor > 0 ? '600' : '400' }};color:{{ $row->saldo_deudor > 0 ? '#1d4ed8' : '#9ca3af' }};background:#eff6ff">
                        {{ $row->saldo_deudor > 0 ? number_format($row->saldo_deudor, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:{{ $row->saldo_acreedor > 0 ? '600' : '400' }};color:{{ $row->saldo_acreedor > 0 ? '#9d174d' : '#9ca3af' }};background:#fdf2f8">
                        {{ $row->saldo_acreedor > 0 ? number_format($row->saldo_acreedor, 2) : '—' }}
                    </td>
                </tr>
                @endforeach
                {{-- Subtotal del grupo --}}
                @php
                    $gSD  = $group->sum('suma_debe');
                    $gSH  = $group->sum('suma_haber');
                    $gSaD = $group->sum('saldo_deudor');
                    $gSaA = $group->sum('saldo_acreedor');
                @endphp
                <tr style="border-top:1px solid #d1d5db">
                    <td colspan="3" style="padding:.4rem .75rem;font-size:.75rem;font-weight:600;color:#6b7280;text-align:right">
                        Subtotal {{ $typeLabels[$type] ?? ucfirst($type) }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#374151;background:#f3f4f6;padding:.4rem .75rem">
                        {{ number_format($gSD, 2) }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#374151;background:#f3f4f6;padding:.4rem .75rem">
                        {{ number_format($gSH, 2) }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#1d4ed8;background:#dbeafe;padding:.4rem .75rem">
                        {{ $gSaD > 0 ? number_format($gSaD, 2) : '—' }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#9d174d;background:#fce7f3;padding:.4rem .75rem">
                        {{ $gSaA > 0 ? number_format($gSaA, 2) : '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:3px solid #111827">
                <td colspan="3" style="padding:.65rem .75rem;font-weight:800;font-size:.875rem;color:#111827;text-transform:uppercase;letter-spacing:.03em">
                    TOTALES
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#111827;background:#e5e7eb;padding:.65rem .75rem">
                    {{ number_format($totalSumaDebe, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#111827;background:#e5e7eb;padding:.65rem .75rem">
                    {{ number_format($totalSumaHaber, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;background:#bfdbfe;padding:.65rem .75rem;color:#1e3a8a">
                    {{ number_format($totalSaldoDeudor, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;background:#fce7f3;padding:.65rem .75rem;color:#831843">
                    {{ number_format($totalSaldoAcreedor, 2) }}
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding:.3rem .75rem;font-size:.75rem;color:#6b7280">Verificación de cuadre</td>
                <td colspan="2" style="text-align:center;padding:.3rem .75rem;font-size:.8125rem;font-weight:600;
                    color:{{ $cuadradoSumas ? '#166534' : '#dc2626' }};background:{{ $cuadradoSumas ? '#dcfce7' : '#fef2f2' }}">
                    Sumas: {{ $cuadradoSumas ? '✓ Cuadra' : '✗ Dif '.number_format(abs($totalSumaDebe-$totalSumaHaber),2) }}
                </td>
                <td colspan="2" style="text-align:center;padding:.3rem .75rem;font-size:.8125rem;font-weight:600;
                    color:{{ $cuadradoSaldos ? '#166534' : '#dc2626' }};background:{{ $cuadradoSaldos ? '#dcfce7' : '#fef2f2' }}">
                    Saldos: {{ $cuadradoSaldos ? '✓ Cuadra' : '✗ Dif '.number_format(abs($totalSaldoDeudor-$totalSaldoAcreedor),2) }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>

@endif

<style>
.input-sm  { padding:.3rem .6rem; font-size:.875rem }
.btn-sm    { padding:.3rem .6rem; font-size:.8125rem }
.balanza-table th { font-size:.75rem; }
@media print {
    .page-header .page-actions, form { display: none !important; }
    .card { break-inside: avoid; }
    body { background: #fff !important; }
    .balanza-table td, .balanza-table th { font-size: .7rem !important; }
}
</style>
</x-layouts.app>
