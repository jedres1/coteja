<x-layouts.app title="Estado de Resultados | Coteja">
@php
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $periodLabel = $acumulado
        ? 'Ene–' . ($months[$month] ?? $month) . ' ' . $year
        : ($months[$month] ?? $month) . ' ' . $year;

    $esUtilidad = $utilidad >= 0;
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Estado de Resultados</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Ingresos y gastos del período — solo partidas aprobadas.
        </p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.accounting.balance-general', ['year'=>$year,'month'=>$month]) }}" class="btn btn-ghost btn-sm">← Balance General</a>
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

{{-- KPI strip --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="flex:1;min-width:160px;background:#f0fdf4;border:1px solid #86efac;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#166534">Total Ingresos</div>
        <div style="font-size:1.25rem;font-weight:700;color:#14532d;font-family:monospace">{{ number_format($totalIngresos, 2) }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#fff7ed;border:1px solid #fed7aa;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#c2410c">Total Gastos</div>
        <div style="font-size:1.25rem;font-weight:700;color:#7c2d12;font-family:monospace">{{ number_format($totalGastos, 2) }}</div>
    </div>
    <div style="flex:2;min-width:200px;border-radius:.5rem;padding:.75rem 1rem;
        {{ $esUtilidad ? 'background:#f0fdf4;border:2px solid #16a34a' : 'background:#fff5f5;border:2px solid #dc2626' }}">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:{{ $esUtilidad ? '#166534' : '#991b1b' }}">
            {{ $esUtilidad ? 'Utilidad del Período' : 'Pérdida del Período' }}
        </div>
        <div style="font-size:1.5rem;font-weight:700;font-family:monospace;color:{{ $esUtilidad ? '#14532d' : '#7f1d1d' }}">
            {{ number_format(abs($utilidad), 2) }}
        </div>
        <div style="font-size:.75rem;color:{{ $esUtilidad ? '#166534' : '#991b1b' }};margin-top:.1rem">
            {{ $periodLabel }} &nbsp;|&nbsp; Margen: {{ $totalIngresos > 0 ? number_format(($utilidad/$totalIngresos)*100, 1).'%' : '—' }}
        </div>
    </div>
</div>

@if($rows->isEmpty())
    <div class="card" style="padding:2.5rem;text-align:center;color:#6b7280">
        No hay partidas aprobadas para el período <strong>{{ $periodLabel }}</strong>.
    </div>
@else

<div class="card" style="overflow:hidden;max-width:800px;margin:0 auto">
    {{-- Encabezado --}}
    <div style="padding:1rem 1.5rem;background:#111827;color:#fff;text-align:center">
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;opacity:.7">Estado de Resultados</div>
        <div style="font-weight:700;font-size:1rem;margin-top:.2rem">{{ $periodLabel }}</div>
        @if($period)
        <div style="font-size:.75rem;opacity:.6">
            Período {{ $period->full_name }}
            @if($period->status === 'cerrado') · Cerrado @endif
        </div>
        @endif
    </div>

    {{-- INGRESOS --}}
    @php
        $ingresoSections = $sections->where('type','ingreso');
    @endphp
    <div style="padding:.6rem 1.5rem;background:#14532d;border-bottom:1px solid #166534">
        <span style="font-size:.75rem;font-weight:800;text-transform:uppercase;color:#bbf7d0;letter-spacing:.06em">INGRESOS</span>
    </div>

    @foreach($ingresoSections as $sec)
        @php
            $group = $bySection->get($sec->code, collect());
            $subtotal = $group->sum('saldo');
        @endphp
        @if($group->isNotEmpty())
        <div style="padding:.4rem 1.5rem;background:#f0fdf4;border-bottom:1px solid #d1fae5">
            <span style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#166534">{{ $sec->name }}</span>
        </div>
        @foreach($group as $row)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 1.5rem .4rem 2.25rem;border-bottom:1px solid #f3f4f6">
            <div style="font-size:.8125rem;color:#374151">
                <span style="color:#9ca3af;font-family:monospace;font-size:.75rem;margin-right:.35rem">{{ $row->code }}</span>{{ $row->name }}
            </div>
            <div style="font-family:monospace;font-size:.8125rem;font-weight:600;color:#166534;white-space:nowrap;margin-left:1rem">
                {{ number_format($row->saldo, 2) }}
            </div>
        </div>
        @endforeach
        <div style="display:flex;justify-content:space-between;padding:.45rem 1.5rem;background:#dcfce7;border-bottom:1px solid #86efac">
            <span style="font-size:.8rem;font-weight:600;color:#166534">Subtotal {{ $sec->name }}</span>
            <span style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#14532d">{{ number_format($subtotal, 2) }}</span>
        </div>
        @endif
    @endforeach

    <div style="display:flex;justify-content:space-between;padding:.65rem 1.5rem;background:#166534;border-bottom:3px solid #14532d">
        <span style="font-weight:700;color:#fff">TOTAL INGRESOS</span>
        <span style="font-family:monospace;font-weight:700;color:#fff;font-size:.9375rem">{{ number_format($totalIngresos, 2) }}</span>
    </div>

    {{-- GASTOS --}}
    @php
        $gastoSections = $sections->where('type','gasto');
    @endphp
    <div style="padding:.6rem 1.5rem;background:#7c2d12;border-bottom:1px solid #9a3412">
        <span style="font-size:.75rem;font-weight:800;text-transform:uppercase;color:#fed7aa;letter-spacing:.06em">GASTOS</span>
    </div>

    @foreach($gastoSections as $sec)
        @php
            $group = $bySection->get($sec->code, collect());
            $subtotal = $group->sum('saldo');
        @endphp
        @if($group->isNotEmpty())
        <div style="padding:.4rem 1.5rem;background:#fff7ed;border-bottom:1px solid #fde8d0">
            <span style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#c2410c">{{ $sec->name }}</span>
        </div>
        @foreach($group as $row)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 1.5rem .4rem 2.25rem;border-bottom:1px solid #f3f4f6">
            <div style="font-size:.8125rem;color:#374151">
                <span style="color:#9ca3af;font-family:monospace;font-size:.75rem;margin-right:.35rem">{{ $row->code }}</span>{{ $row->name }}
            </div>
            <div style="font-family:monospace;font-size:.8125rem;font-weight:600;color:#c2410c;white-space:nowrap;margin-left:1rem">
                ({{ number_format($row->saldo, 2) }})
            </div>
        </div>
        @endforeach
        <div style="display:flex;justify-content:space-between;padding:.45rem 1.5rem;background:#ffedd5;border-bottom:1px solid #fdba74">
            <span style="font-size:.8rem;font-weight:600;color:#c2410c">Subtotal {{ $sec->name }}</span>
            <span style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#7c2d12">({{ number_format($subtotal, 2) }})</span>
        </div>
        @endif
    @endforeach

    <div style="display:flex;justify-content:space-between;padding:.65rem 1.5rem;background:#9a3412;border-bottom:3px solid #7c2d12">
        <span style="font-weight:700;color:#fff">TOTAL GASTOS</span>
        <span style="font-family:monospace;font-weight:700;color:#fff;font-size:.9375rem">({{ number_format($totalGastos, 2) }})</span>
    </div>

    {{-- RESULTADO --}}
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;
        background:{{ $esUtilidad ? '#052e16' : '#450a0a' }}">
        <div>
            <div style="font-weight:800;font-size:1rem;color:#fff;text-transform:uppercase;letter-spacing:.03em">
                {{ $esUtilidad ? 'Utilidad del Período' : 'Pérdida del Período' }}
            </div>
            <div style="font-size:.75rem;color:{{ $esUtilidad ? '#86efac' : '#fca5a5' }};margin-top:.15rem">
                {{ $periodLabel }}
                @if($totalIngresos > 0)
                    &nbsp;· Margen {{ number_format(($utilidad/$totalIngresos)*100, 1) }}%
                @endif
            </div>
        </div>
        <div style="font-family:monospace;font-size:1.375rem;font-weight:800;
            color:{{ $esUtilidad ? '#4ade80' : '#f87171' }}">
            {{ $esUtilidad ? '' : '(' }}{{ number_format(abs($utilidad), 2) }}{{ $esUtilidad ? '' : ')' }}
        </div>
    </div>
</div>

@endif

<style>
.input-sm { padding:.3rem .6rem; font-size:.875rem }
.btn-sm   { padding:.3rem .6rem; font-size:.8125rem }
@media print {
    .page-header .page-actions, form { display: none !important; }
    .card { max-width: 100% !important; }
    body { background: #fff !important; }
}
</style>
</x-layouts.app>
