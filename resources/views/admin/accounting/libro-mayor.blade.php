<x-layouts.app title="Libro Mayor | Coteja">
@php
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    $periodLabel = $acumulado
        ? 'Ene–'.($months[$month] ?? $month).' '.$year
        : ($months[$month] ?? $month).' '.$year;

    $packageColors = [
        'FA' => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
        'CP' => ['bg'=>'#fef3c7','color'=>'#92400e'],
        'IN' => ['bg'=>'#dcfce7','color'=>'#166534'],
        'CB' => ['bg'=>'#ede9fe','color'=>'#5b21b6'],
        'CG' => ['bg'=>'#f3f4f6','color'=>'#374151'],
    ];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Libro Mayor</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Movimientos cronológicos por cuenta con saldo corrido — solo partidas aprobadas.
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
            <label style="font-size:.75rem;font-weight:600;color:#374151">Cuenta (dejar vacío = todas)</label>
            <select name="account_id" class="input input-sm" style="min-width:280px">
                <option value="">— Todas las cuentas con movimiento —</option>
                @foreach($allAccounts as $acc)
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
                Acumulado (Ene–{{ $months[$month] ?? $month }})
            </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
    </form>
</div>

@if($ledger->isEmpty())
    <div class="card" style="padding:2.5rem;text-align:center;color:#6b7280">
        No hay partidas aprobadas en el período <strong>{{ $periodLabel }}</strong>
        @if($accountId) para la cuenta seleccionada @endif.
    </div>
@else

{{-- Resumen --}}
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.65rem 1rem;font-size:.8125rem;color:#374151">
        <strong>{{ $ledger->count() }}</strong> cuenta(s) &nbsp;·&nbsp;
        <strong>{{ $ledger->sum(fn($l) => $l->lines->count()) }}</strong> movimiento(s) &nbsp;·&nbsp;
        {{ $periodLabel }}
        @if($period)
            &nbsp;·&nbsp; <span style="color:{{ $period->status==='abierto'?'#16a34a':'#6b7280' }}">
                {{ $period->status==='abierto' ? 'Período abierto' : 'Período cerrado' }}
            </span>
        @endif
    </div>
</div>

{{-- Una sección por cuenta --}}
@foreach($ledger as $entry)
@php
    $acc = $entry->account;
    $saldoFinalPositivo = $entry->saldo_final >= 0;
@endphp

<div class="card mayor-account" style="overflow:hidden;margin-bottom:1.25rem">

    {{-- Encabezado de la cuenta --}}
    <div style="padding:.75rem 1.25rem;background:#1f2937;color:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <div style="display:flex;align-items:center;gap:.75rem">
            <span style="font-family:monospace;font-size:1rem;font-weight:700;opacity:.7">{{ $acc->code }}</span>
            <div>
                <div style="font-weight:700;font-size:.9375rem">{{ $acc->name }}</div>
                <div style="font-size:.75rem;opacity:.6;margin-top:.1rem">
                    {{ ucfirst($acc->type) }} &nbsp;·&nbsp;
                    Naturaleza {{ $acc->nature === 'deudora' ? 'Deudora (D)' : 'Acreedora (A)' }}
                </div>
            </div>
        </div>
        <div style="display:flex;gap:1.5rem;text-align:right">
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6">Total Debe</div>
                <div style="font-family:monospace;font-weight:700">{{ number_format($entry->total_debe, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6">Total Haber</div>
                <div style="font-family:monospace;font-weight:700">{{ number_format($entry->total_haber, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.65rem;text-transform:uppercase;opacity:.6">Saldo Final</div>
                <div style="font-family:monospace;font-weight:800;font-size:1rem;
                    color:{{ $saldoFinalPositivo ? '#4ade80' : '#f87171' }}">
                    {{ $saldoFinalPositivo ? '' : '(' }}{{ number_format(abs($entry->saldo_final), 2) }}{{ $saldoFinalPositivo ? '' : ')' }}
                </div>
            </div>
        </div>
    </div>

    <table class="table mayor-table" style="table-layout:fixed">
        <colgroup>
            <col style="width:82px">
            <col style="width:120px">
            <col style="width:60px">
            <col>
            <col style="width:110px">
            <col style="width:110px">
            <col style="width:115px">
        </colgroup>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>N° Partida</th>
                <th style="text-align:center">Paq.</th>
                <th>Descripción / Documento</th>
                <th style="text-align:right">Debe</th>
                <th style="text-align:right">Haber</th>
                <th style="text-align:right;background:#f0f9ff">Saldo</th>
            </tr>
        </thead>
        <tbody>
            {{-- Saldo inicial --}}
            <tr style="background:#f9fafb">
                <td colspan="4" style="font-size:.8rem;font-weight:600;color:#6b7280;font-style:italic">
                    Saldo inicial al {{ \Carbon\Carbon::parse($startDate)->subDay()->format('d/m/Y') }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#6b7280">—</td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#6b7280">—</td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:600;
                    color:{{ $entry->saldo_inicial >= 0 ? '#374151' : '#dc2626' }};background:#f0f9ff">
                    {{ $entry->saldo_inicial >= 0 ? '' : '(' }}{{ number_format(abs($entry->saldo_inicial), 2) }}{{ $entry->saldo_inicial >= 0 ? '' : ')' }}
                </td>
            </tr>

            {{-- Movimientos --}}
            @foreach($entry->lines as $line)
            @php $pkgStyle = $packageColors[$line->package_code] ?? $packageColors['CG']; @endphp
            <tr>
                <td style="font-size:.8125rem;white-space:nowrap;color:#374151">
                    {{ \Carbon\Carbon::parse($line->entry_date)->format('d/m/Y') }}
                </td>
                <td style="font-family:monospace;font-size:.75rem;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <a href="{{ route('admin.accounting.diario.show', $line->entry_id) }}"
                       style="color:#2563eb;text-decoration:none" title="{{ $line->entry_number }}">
                        {{ $line->entry_number }}
                    </a>
                </td>
                <td style="text-align:center">
                    @if($line->package_code)
                    <span style="font-size:.7rem;font-weight:700;padding:.1rem .3rem;border-radius:.2rem;
                        background:{{ $pkgStyle['bg'] }};color:{{ $pkgStyle['color'] }}">
                        {{ $line->package_code }}
                    </span>
                    @endif
                </td>
                <td style="font-size:.8125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="{{ $line->description }}{{ $line->source_document ? ' · '.$line->source_document : '' }}">
                    {{ $line->description }}
                    @if($line->source_document)
                        <span style="color:#9ca3af;font-size:.75rem"> · {{ $line->source_document }}</span>
                    @endif
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151">
                    {{ $line->debe > 0 ? number_format($line->debe, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;color:#374151">
                    {{ $line->haber > 0 ? number_format($line->haber, 2) : '—' }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.8125rem;font-weight:600;background:#f0f9ff;
                    color:{{ $line->saldo >= 0 ? '#1e3a8a' : '#dc2626' }}">
                    {{ $line->saldo >= 0 ? '' : '(' }}{{ number_format(abs($line->saldo), 2) }}{{ $line->saldo >= 0 ? '' : ')' }}
                </td>
            </tr>
            @endforeach

            {{-- Totales y saldo final --}}
            <tr style="border-top:2px solid #374151;background:#f3f4f6">
                <td colspan="4" style="padding:.5rem .75rem;font-size:.8rem;font-weight:700;color:#374151;text-align:right">
                    Totales del período &nbsp;|&nbsp; Saldo final
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.875rem;font-weight:700;color:#374151;padding:.5rem .75rem">
                    {{ number_format($entry->total_debe, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.875rem;font-weight:700;color:#374151;padding:.5rem .75rem">
                    {{ number_format($entry->total_haber, 2) }}
                </td>
                <td style="text-align:right;font-family:monospace;font-size:.9375rem;font-weight:800;padding:.5rem .75rem;
                    background:#dbeafe;color:{{ $saldoFinalPositivo ? '#1e3a8a' : '#dc2626' }}">
                    {{ $saldoFinalPositivo ? '' : '(' }}{{ number_format(abs($entry->saldo_final), 2) }}{{ $saldoFinalPositivo ? '' : ')' }}
                </td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach

@endif

<style>
.input-sm   { padding:.3rem .6rem; font-size:.875rem }
.btn-sm     { padding:.3rem .6rem; font-size:.8125rem }
.mayor-table th { font-size:.75rem; padding:.5rem .75rem; }
.mayor-table td { vertical-align:middle; }
@media print {
    .page-header .page-actions, form { display: none !important; }
    .mayor-account { break-inside: avoid; margin-bottom: .75rem !important; }
    body { background: #fff !important; }
    .mayor-table td, .mayor-table th { font-size: .68rem !important; padding: .25rem .4rem !important; }
}
</style>
</x-layouts.app>
