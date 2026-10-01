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

    $cuadradoSumas  = abs($totalSumaDebe - $totalSumaHaber) < 0.01;
    $cuadradoSaldos = abs($totalSaldoDeudor - $totalSaldoAcreedor) < 0.01;

    $groupedRows  = $rows->groupBy('type');
    $typeOrder    = ['activo','pasivo','patrimonio','ingreso','gasto','costo','orden'];
    $orderedTypes = collect($typeOrder)->filter(fn($t) => $groupedRows->has($t));

    $totalCuentas     = $rows->count();
    $cuentasConMov    = $rows->where('tiene_movimiento', true)->count();
    $cuentasSinMov    = $totalCuentas - $cuentasConMov;
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Balanza de Comprobación</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Sumas y saldos de cuentas de detalle — solo partidas aprobadas.
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
        <div style="display:flex;flex-direction:column;gap:.5rem;padding-bottom:.1rem">
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;user-select:none;font-size:.875rem">
                <input type="checkbox" name="acumulado" value="1" {{ $acumulado ? 'checked' : '' }}
                       style="width:1rem;height:1rem">
                Acumulado (Ene–{{ $months[$month] ?? $month }})
            </label>
            <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;user-select:none;font-size:.875rem">
                <input type="checkbox" name="solo_movimiento" value="1" {{ $soloConMovimiento ? 'checked' : '' }}
                       style="width:1rem;height:1rem">
                Solo cuentas con movimiento
            </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
    </form>
</div>

{{-- KPI strip --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="flex:1;min-width:140px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Cuentas de detalle</div>
        <div style="font-size:1.5rem;font-weight:700;color:#111827">{{ $totalCuentas }}</div>
        <div style="font-size:.75rem;color:#6b7280;margin-top:.1rem">
            <span style="color:#16a34a">{{ $cuentasConMov }} con movimiento</span>
            @if($cuentasSinMov > 0)
                &nbsp;· <span style="color:#9ca3af">{{ $cuentasSinMov }} en cero</span>
            @endif
        </div>
    </div>
    <div style="flex:2;min-width:200px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Sumas del período</div>
        <div style="display:flex;gap:1.5rem;margin-top:.25rem">
            <div>
                <div style="font-size:.7rem;color:#6b7280">Debe</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#374151">{{ number_format($totalSumaDebe, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#6b7280">Haber</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#374151">{{ number_format($totalSumaHaber, 2) }}</div>
            </div>
            <div style="border-left:1px solid #e5e7eb;padding-left:1.5rem">
                <div style="font-size:.7rem;color:{{ $cuadradoSumas ? '#166534' : '#991b1b' }}">
                    {{ $cuadradoSumas ? '✓ Cuadra' : '✗ No cuadra' }}
                </div>
                @if(!$cuadradoSumas)
                <div style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#dc2626">
                    Dif: {{ number_format(abs($totalSumaDebe - $totalSumaHaber), 2) }}
                </div>
                @endif
            </div>
        </div>
    </div>
    <div style="flex:2;min-width:200px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#6b7280">Saldos netos</div>
        <div style="display:flex;gap:1.5rem;margin-top:.25rem">
            <div>
                <div style="font-size:.7rem;color:#6b7280">Deudor</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#1d4ed8">{{ number_format($totalSaldoDeudor, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.7rem;color:#6b7280">Acreedor</div>
                <div style="font-family:monospace;font-weight:700;font-size:.9375rem;color:#9d174d">{{ number_format($totalSaldoAcreedor, 2) }}</div>
            </div>
            <div style="border-left:1px solid #e5e7eb;padding-left:1.5rem">
                <div style="font-size:.7rem;color:{{ $cuadradoSaldos ? '#166534' : '#991b1b' }}">
                    {{ $cuadradoSaldos ? '✓ Cuadra' : '✗ No cuadra' }}
                </div>
                @if(!$cuadradoSaldos)
                <div style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#dc2626">
                    Dif: {{ number_format(abs($totalSaldoDeudor - $totalSaldoAcreedor), 2) }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card" style="overflow:hidden">
    {{-- Encabezado --}}
    <div style="padding:.75rem 1.25rem;background:#111827;color:#fff;display:flex;justify-content:space-between;align-items:center">
        <div>
            <div style="font-weight:700;font-size:.9375rem">Balanza de Comprobación de Saldos</div>
            <div style="font-size:.75rem;opacity:.7;margin-top:.1rem">
                {{ $periodLabel }}
                @if($period) &nbsp;·&nbsp; {{ $period->status === 'abierto' ? 'Período abierto' : 'Período cerrado' }} @endif
                @if($tipo) &nbsp;·&nbsp; {{ $typeLabels[$tipo] ?? $tipo }} @endif
                @if($soloConMovimiento) &nbsp;·&nbsp; Solo con movimiento @endif
            </div>
        </div>
        <div style="display:flex;gap:1rem;text-align:right">
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6;margin-bottom:.15rem">Sumas D=H</div>
                <span style="font-size:1rem;font-weight:700;color:{{ $cuadradoSumas ? '#4ade80' : '#f87171' }}">
                    {{ $cuadradoSumas ? '✓' : '✗' }}
                </span>
            </div>
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6;margin-bottom:.15rem">Saldos D=A</div>
                <span style="font-size:1rem;font-weight:700;color:{{ $cuadradoSaldos ? '#4ade80' : '#f87171' }}">
                    {{ $cuadradoSaldos ? '✓' : '✗' }}
                </span>
            </div>
        </div>
    </div>

    <table class="table balanza-table" style="table-layout:fixed">
        <colgroup>
            <col style="width:90px">
            <col>
            <col style="width:70px">
            <col style="width:115px">
            <col style="width:115px">
            <col style="width:115px">
            <col style="width:115px">
        </colgroup>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre de la cuenta</th>
                <th style="text-align:center">Nat.</th>
                <th style="text-align:right;background:#fafaf9">Suma Debe</th>
                <th style="text-align:right;background:#fafaf9">Suma Haber</th>
                <th style="text-align:right;background:#eff6ff">Saldo Deudor</th>
                <th style="text-align:right;background:#fdf2f8">Saldo Acreedor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orderedTypes as $type)
            {{-- Cabecera de grupo --}}
            <tr>
                <td colspan="7" style="padding:.4rem .75rem;background:#f3f4f6;border-bottom:1px solid #d1d5db">
                    <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151">
                        {{ $typeLabels[$type] ?? ucfirst($type) }}
                        <span style="font-weight:400;color:#9ca3af;font-size:.7rem">
                            ({{ $groupedRows[$type]->count() }} cuentas
                            · {{ $groupedRows[$type]->where('tiene_movimiento',true)->count() }} con movimiento)
                        </span>
                    </span>
                </td>
            </tr>
            @foreach($groupedRows[$type] as $row)
            <tr style="{{ !$row->tiene_movimiento ? 'opacity:.45' : '' }}">
                <td style="font-family:monospace;font-size:.8125rem;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    {{ $row->code }}
                </td>
                <td style="font-size:.8125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $row->name }}">
                    {{ $row->name }}
                </td>
                <td style="text-align:center">
                    <span style="font-size:.7rem;font-weight:600;
                        {{ $row->nature === 'deudora' ? 'color:#1d4ed8' : 'color:#9d174d' }}">
                        {{ $row->nature === 'deudora' ? 'D' : 'A' }}
                    </span>
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151;background:#fafaf9">
                    {{ $row->suma_debe > 0 ? number_format($row->suma_debe, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151;background:#fafaf9">
                    {{ $row->suma_haber > 0 ? number_format($row->suma_haber, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:{{ $row->saldo_deudor > 0 ? '600' : '400' }};
                    color:{{ $row->saldo_deudor > 0 ? '#1d4ed8' : '#9ca3af' }};background:#eff6ff">
                    {{ $row->saldo_deudor > 0 ? number_format($row->saldo_deudor, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:{{ $row->saldo_acreedor > 0 ? '600' : '400' }};
                    color:{{ $row->saldo_acreedor > 0 ? '#9d174d' : '#9ca3af' }};background:#fdf2f8">
                    {{ $row->saldo_acreedor > 0 ? number_format($row->saldo_acreedor, 2) : '—' }}
                </td>
            </tr>
            @endforeach
            {{-- Subtotal del grupo --}}
            @php
                $g    = $groupedRows[$type];
                $gSD  = $g->sum('suma_debe');
                $gSH  = $g->sum('suma_haber');
                $gSaD = $g->sum('saldo_deudor');
                $gSaA = $g->sum('saldo_acreedor');
            @endphp
            <tr style="border-top:1px solid #d1d5db">
                <td colspan="3" style="padding:.4rem .75rem;font-size:.75rem;font-weight:600;color:#6b7280;text-align:right">
                    Subtotal {{ $typeLabels[$type] ?? ucfirst($type) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#374151;background:#f3f4f6;padding:.4rem .75rem">
                    {{ $gSD > 0 ? number_format($gSD, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:700;color:#374151;background:#f3f4f6;padding:.4rem .75rem">
                    {{ $gSH > 0 ? number_format($gSH, 2) : '—' }}
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
                <td colspan="3" style="padding:.65rem .75rem;font-weight:800;font-size:.875rem;color:#111827;text-transform:uppercase">
                    TOTALES
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#111827;background:#e5e7eb;padding:.65rem .75rem">
                    {{ number_format($totalSumaDebe, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#111827;background:#e5e7eb;padding:.65rem .75rem">
                    {{ number_format($totalSumaHaber, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#1e3a8a;background:#bfdbfe;padding:.65rem .75rem">
                    {{ number_format($totalSaldoDeudor, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:.9375rem;color:#831843;background:#fce7f3;padding:.65rem .75rem">
                    {{ number_format($totalSaldoAcreedor, 2) }}
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding:.3rem .75rem;font-size:.75rem;color:#9ca3af">
                    * Cuentas en cero se muestran atenuadas. Los totales excluyen cuentas sin movimiento.
                </td>
                <td colspan="2" style="text-align:center;padding:.35rem .75rem;font-size:.8125rem;font-weight:600;
                    color:{{ $cuadradoSumas ? '#166534' : '#dc2626' }};background:{{ $cuadradoSumas ? '#dcfce7' : '#fef2f2' }}">
                    Sumas: {{ $cuadradoSumas ? '✓ Cuadra' : '✗ Dif '.number_format(abs($totalSumaDebe-$totalSumaHaber),2) }}
                </td>
                <td colspan="2" style="text-align:center;padding:.35rem .75rem;font-size:.8125rem;font-weight:600;
                    color:{{ $cuadradoSaldos ? '#166534' : '#dc2626' }};background:{{ $cuadradoSaldos ? '#dcfce7' : '#fef2f2' }}">
                    Saldos: {{ $cuadradoSaldos ? '✓ Cuadra' : '✗ Dif '.number_format(abs($totalSaldoDeudor-$totalSaldoAcreedor),2) }}
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<style>
.input-sm  { padding:.3rem .6rem; font-size:.875rem }
.btn-sm    { padding:.3rem .6rem; font-size:.8125rem }
.balanza-table th { font-size:.75rem; padding:.5rem .75rem; }
@media print {
    .page-header .page-actions, form { display: none !important; }
    .card { break-inside: avoid; }
    body { background: #fff !important; }
    .balanza-table td, .balanza-table th { font-size: .68rem !important; padding: .3rem .4rem !important; }
    tr[style*="opacity"] { display: none !important; }
}
</style>
</x-layouts.app>
