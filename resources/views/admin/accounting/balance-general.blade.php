<x-layouts.app title="Balance General | Coteja">
@php
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $activos   = $rows->where('type', 'activo');
    $pasivos   = $rows->where('type', 'pasivo');
    $patrimonio = $rows->where('type', 'patrimonio');

    $totalActivos    = $activos->sum('saldo');
    $totalPasivos    = $pasivos->sum('saldo');
    $totalPatrimonio = $patrimonio->sum('saldo');
    $totalPasPat     = $totalPasivos + $totalPatrimonio;

    $diff = abs($totalActivos - $totalPasPat);
    $cuadrado = $diff < 0.01;

    $periodLabel = ($months[$month] ?? $month) . ' ' . $year;
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Balance General</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Posición financiera acumulada al cierre del período seleccionado.
        </p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.accounting.estado-resultados', ['year'=>$year,'month'=>$month]) }}" class="btn btn-ghost btn-sm">Estado de Resultados →</a>
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
            <label style="font-size:.75rem;font-weight:600;color:#374151">Hasta el mes</label>
            <select name="month" class="input input-sm" style="min-width:130px">
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ $months[$m] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
        <div style="display:flex;align-items:center;gap:.5rem;padding-bottom:.1rem;font-size:.8125rem;color:#6b7280">
            * Saldos acumulados desde Enero {{ $year }}
        </div>
    </form>
</div>

{{-- Resumen superior --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="flex:1;min-width:160px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#1d4ed8">Total Activos</div>
        <div style="font-size:1.25rem;font-weight:700;color:#1e3a8a;font-family:monospace">{{ number_format($totalActivos, 2) }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#fff7ed;border:1px solid #fed7aa;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#c2410c">Total Pasivos</div>
        <div style="font-size:1.25rem;font-weight:700;color:#7c2d12;font-family:monospace">{{ number_format($totalPasivos, 2) }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#f0fdf4;border:1px solid #86efac;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:#166534">Total Patrimonio</div>
        <div style="font-size:1.25rem;font-weight:700;color:#14532d;font-family:monospace">{{ number_format($totalPatrimonio, 2) }}</div>
    </div>
    <div style="flex:1;min-width:160px;border-radius:.5rem;padding:.75rem 1rem;
        {{ $cuadrado ? 'background:#f0fdf4;border:1px solid #86efac' : 'background:#fff5f5;border:1px solid #fca5a5' }}">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;color:{{ $cuadrado ? '#166534' : '#991b1b' }}">Pasivo + Patrimonio</div>
        <div style="font-size:1.25rem;font-weight:700;font-family:monospace;color:{{ $cuadrado ? '#14532d' : '#7f1d1d' }}">
            {{ number_format($totalPasPat, 2) }}
            @if($cuadrado) <span style="font-size:.75rem;font-weight:400">✓</span>
            @else <span style="font-size:.7rem;font-weight:400;color:#dc2626"> Dif: {{ number_format($diff,2) }}</span>
            @endif
        </div>
    </div>
</div>

@if($rows->isEmpty())
    <div class="card" style="padding:2.5rem;text-align:center;color:#6b7280">
        No hay partidas aprobadas acumuladas hasta <strong>{{ $periodLabel }}</strong>.
    </div>
@else

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem" class="balance-grid">

    {{-- COLUMNA IZQUIERDA: ACTIVOS --}}
    <div class="card" style="overflow:hidden">
        <div style="padding:.75rem 1.25rem;background:#1e3a8a;color:#fff">
            <h2 style="margin:0;font-size:.9375rem;font-weight:700">ACTIVOS</h2>
            <div style="font-size:.75rem;opacity:.8">Al {{ $periodLabel }}</div>
        </div>

        @php
            $activoSections = $sections->where('type','activo');
        @endphp

        @foreach($activoSections as $sec)
            @php
                $group = $bySection->get($sec->code, collect());
                $subtotal = $group->sum('saldo');
            @endphp
            @if($group->isNotEmpty())
            <div style="padding:.5rem 1.25rem;background:#eff6ff;border-bottom:1px solid #bfdbfe">
                <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#1d4ed8">{{ $sec->name }}</span>
            </div>
            @foreach($group as $row)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 1.25rem .45rem 2rem;border-bottom:1px solid #f3f4f6">
                <div style="font-size:.8125rem;color:#374151">
                    <span style="color:#9ca3af;font-family:monospace;font-size:.75rem">{{ $row->code }}</span>
                    {{ $row->name }}
                </div>
                <div style="font-family:monospace;font-size:.8125rem;font-weight:600;color:{{ $row->saldo >= 0 ? '#1e3a8a' : '#dc2626' }};white-space:nowrap;margin-left:.5rem">
                    {{ number_format($row->saldo, 2) }}
                </div>
            </div>
            @endforeach
            <div style="display:flex;justify-content:space-between;padding:.5rem 1.25rem;background:#dbeafe;border-bottom:2px solid #93c5fd">
                <span style="font-size:.8rem;font-weight:700;color:#1d4ed8">Total {{ $sec->name }}</span>
                <span style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#1e3a8a">{{ number_format($subtotal, 2) }}</span>
            </div>
            @endif
        @endforeach

        <div style="display:flex;justify-content:space-between;padding:.75rem 1.25rem;background:#1e3a8a">
            <span style="font-weight:700;color:#fff;font-size:.875rem">TOTAL ACTIVOS</span>
            <span style="font-family:monospace;font-weight:700;color:#fff;font-size:.9375rem">{{ number_format($totalActivos, 2) }}</span>
        </div>
    </div>

    {{-- COLUMNA DERECHA: PASIVOS + PATRIMONIO --}}
    <div class="card" style="overflow:hidden">
        <div style="padding:.75rem 1.25rem;background:#7c2d12;color:#fff">
            <h2 style="margin:0;font-size:.9375rem;font-weight:700">PASIVOS Y PATRIMONIO</h2>
            <div style="font-size:.75rem;opacity:.8">Al {{ $periodLabel }}</div>
        </div>

        {{-- PASIVOS --}}
        @php $pasivoSections = $sections->where('type','pasivo'); @endphp
        <div style="padding:.5rem 1.25rem;background:#431407;border-bottom:1px solid #7c2d12">
            <span style="font-size:.75rem;font-weight:800;text-transform:uppercase;color:#fed7aa;letter-spacing:.06em">PASIVOS</span>
        </div>

        @foreach($pasivoSections as $sec)
            @php
                $group = $bySection->get($sec->code, collect());
                $subtotal = $group->sum('saldo');
            @endphp
            @if($group->isNotEmpty())
            <div style="padding:.5rem 1.25rem;background:#fff7ed;border-bottom:1px solid #fed7aa">
                <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#c2410c">{{ $sec->name }}</span>
            </div>
            @foreach($group as $row)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 1.25rem .45rem 2rem;border-bottom:1px solid #f3f4f6">
                <div style="font-size:.8125rem;color:#374151">
                    <span style="color:#9ca3af;font-family:monospace;font-size:.75rem">{{ $row->code }}</span>
                    {{ $row->name }}
                </div>
                <div style="font-family:monospace;font-size:.8125rem;font-weight:600;color:{{ $row->saldo >= 0 ? '#7c2d12' : '#dc2626' }};white-space:nowrap;margin-left:.5rem">
                    {{ number_format($row->saldo, 2) }}
                </div>
            </div>
            @endforeach
            <div style="display:flex;justify-content:space-between;padding:.5rem 1.25rem;background:#ffedd5;border-bottom:2px solid #fdba74">
                <span style="font-size:.8rem;font-weight:700;color:#c2410c">Total {{ $sec->name }}</span>
                <span style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#7c2d12">{{ number_format($subtotal, 2) }}</span>
            </div>
            @endif
        @endforeach

        <div style="display:flex;justify-content:space-between;padding:.6rem 1.25rem;background:#fed7aa;border-bottom:3px solid #f97316">
            <span style="font-weight:700;color:#7c2d12;font-size:.875rem">Total Pasivos</span>
            <span style="font-family:monospace;font-weight:700;color:#7c2d12;font-size:.9375rem">{{ number_format($totalPasivos, 2) }}</span>
        </div>

        {{-- PATRIMONIO --}}
        @php $patrimonioSections = $sections->where('type','patrimonio'); @endphp
        <div style="padding:.5rem 1.25rem;background:#14532d;border-bottom:1px solid #166534">
            <span style="font-size:.75rem;font-weight:800;text-transform:uppercase;color:#bbf7d0;letter-spacing:.06em">PATRIMONIO</span>
        </div>

        @foreach($patrimonioSections as $sec)
            @php
                $group = $bySection->get($sec->code, collect());
                $subtotal = $group->sum('saldo');
            @endphp
            @if($group->isNotEmpty())
            <div style="padding:.5rem 1.25rem;background:#f0fdf4;border-bottom:1px solid #86efac">
                <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#166534">{{ $sec->name }}</span>
            </div>
            @foreach($group as $row)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 1.25rem .45rem 2rem;border-bottom:1px solid #f3f4f6">
                <div style="font-size:.8125rem;color:#374151">
                    <span style="color:#9ca3af;font-family:monospace;font-size:.75rem">{{ $row->code }}</span>
                    {{ $row->name }}
                </div>
                <div style="font-family:monospace;font-size:.8125rem;font-weight:600;color:{{ $row->saldo >= 0 ? '#14532d' : '#dc2626' }};white-space:nowrap;margin-left:.5rem">
                    {{ number_format($row->saldo, 2) }}
                </div>
            </div>
            @endforeach
            <div style="display:flex;justify-content:space-between;padding:.5rem 1.25rem;background:#dcfce7;border-bottom:2px solid #4ade80">
                <span style="font-size:.8rem;font-weight:700;color:#166534">Total {{ $sec->name }}</span>
                <span style="font-family:monospace;font-size:.8125rem;font-weight:700;color:#14532d">{{ number_format($subtotal, 2) }}</span>
            </div>
            @endif
        @endforeach

        <div style="display:flex;justify-content:space-between;padding:.6rem 1.25rem;background:#bbf7d0;border-bottom:3px solid #16a34a">
            <span style="font-weight:700;color:#14532d;font-size:.875rem">Total Patrimonio</span>
            <span style="font-family:monospace;font-weight:700;color:#14532d;font-size:.9375rem">{{ number_format($totalPatrimonio, 2) }}</span>
        </div>

        {{-- Gran total --}}
        <div style="display:flex;justify-content:space-between;padding:.75rem 1.25rem;background:#431407">
            <span style="font-weight:700;color:#fff;font-size:.875rem">TOTAL PASIVOS + PATRIMONIO</span>
            <span style="font-family:monospace;font-weight:700;font-size:.9375rem;color:{{ $cuadrado ? '#86efac' : '#fca5a5' }}">
                {{ number_format($totalPasPat, 2) }}
                @if($cuadrado) ✓ @endif
            </span>
        </div>
    </div>

</div>{{-- end balance-grid --}}
@endif

<style>
.input-sm { padding:.3rem .6rem; font-size:.875rem }
.btn-sm   { padding:.3rem .6rem; font-size:.8125rem }
@media print {
    .page-header .page-actions, form { display: none !important; }
    .balance-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; }
    .card { break-inside: avoid; }
    body { background: #fff !important; }
}
@media (max-width: 900px) {
    .balance-grid { grid-template-columns: 1fr !important; }
}
</style>
</x-layouts.app>
