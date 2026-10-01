<x-layouts.app title="Períodos Contables | Coteja">
@php
    $statusStyle = [
        'abierto' => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Abierto'],
        'cerrado' => ['bg'=>'#f3f4f6','color'=>'#6b7280','label'=>'Cerrado'],
    ];
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $openCount   = $periods->where('status','abierto')->count();
    $closedCount = $periods->where('status','cerrado')->count();
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Períodos Contables</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Administre los períodos mensuales que delimitan las fechas en que se pueden registrar transacciones contables.
        </p>
    </div>
    <div class="page-actions">
        <form method="GET" style="display:flex;gap:.5rem;align-items:center">
            <select name="year" class="input input-sm" onchange="this.form.submit()">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
                <option value="{{ now()->year + 1 }}" {{ (now()->year + 1) == $year ? 'selected' : '' }}>{{ now()->year + 1 }}</option>
            </select>
        </form>
        <button class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-gen').setAttribute('open','')">
            + Crear períodos del año
        </button>
    </div>
</div>

{{-- Info banner --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div style="flex:1;min-width:160px;background:#f0fdf4;border:1px solid #86efac;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:#166534;font-weight:600;text-transform:uppercase">Períodos abiertos</div>
        <div style="font-size:1.5rem;font-weight:700;color:#16a34a">{{ $openCount }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase">Períodos cerrados</div>
        <div style="font-size:1.5rem;font-weight:700;color:#374151">{{ $closedCount }}</div>
    </div>
    <div style="flex:3;min-width:220px;background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.8125rem;color:#92400e">
            <strong>Importante:</strong> Solo se pueden registrar transacciones (facturas, compras, movimientos de inventario, asientos) en períodos con estado <strong>Abierto</strong>.
            Los módulos auxiliares quedan bloqueados si el período correspondiente está cerrado.
        </div>
    </div>
</div>

{{-- Tabla de períodos --}}
<div class="card" style="overflow:hidden">
    <div style="padding:.875rem 1.25rem;border-bottom:1px solid #e5e7eb">
        <h2 style="margin:0;font-size:1rem;font-weight:700">Año {{ $year }}</h2>
    </div>
    @if($periods->isEmpty())
        <div style="padding:2rem;text-align:center;color:#6b7280">
            No hay períodos creados para el año {{ $year }}.
            <br>
            <button class="btn btn-primary" style="margin-top:.75rem"
                    onclick="document.getElementById('modal-gen').setAttribute('open','')">
                Crear los 12 períodos de {{ $year }}
            </button>
        </div>
    @else
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">Mes</th>
                <th>Período</th>
                <th style="width:100px;text-align:center">Estado</th>
                <th style="width:160px">Apertura</th>
                <th style="width:160px">Cierre</th>
                <th style="width:90px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($periods as $period)
            @php $ss = $statusStyle[$period->status] ?? $statusStyle['cerrado']; @endphp
            <tr style="{{ $period->month == now()->month && $period->year == now()->year ? 'background:#f0f9ff' : '' }}">
                <td style="font-weight:700;color:#6b7280;text-align:center">{{ str_pad($period->month, 2, '0', STR_PAD_LEFT) }}</td>
                <td>
                    <span style="font-weight:500">{{ $period->full_name }}</span>
                    @if($period->month == now()->month && $period->year == now()->year)
                        <span style="font-size:.7rem;background:#dbeafe;color:#1d4ed8;padding:.1rem .4rem;border-radius:.25rem;margin-left:.5rem;font-weight:600">MES ACTUAL</span>
                    @endif
                    @if($period->notes)
                        <div style="font-size:.75rem;color:#6b7280;margin-top:.15rem">{{ $period->notes }}</div>
                    @endif
                </td>
                <td style="text-align:center">
                    <span class="badge" style="background:{{ $ss['bg'] }};color:{{ $ss['color'] }};border:1px solid {{ $ss['color'] }}30;font-size:.8125rem;padding:.3rem .7rem">
                        {{ $ss['label'] }}
                    </span>
                </td>
                <td style="font-size:.8125rem;color:#374151">
                    {{ $period->opened_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
                <td style="font-size:.8125rem;color:#374151">
                    @if($period->closed_at)
                        {{ $period->closed_at->format('d/m/Y H:i') }}
                        @if($period->closedByUser)
                            <br><span style="color:#9ca3af;font-size:.75rem">{{ $period->closedByUser->name }}</span>
                        @endif
                    @else
                        <span style="color:#d1d5db">—</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex;gap:.35rem">
                        @if($period->status === 'cerrado')
                            <button class="btn btn-ghost btn-sm" style="color:#16a34a;border-color:#86efac;font-size:.75rem"
                                    onclick="togglePeriod({{ $period->id }}, 'abrir', '{{ $period->full_name }}')">
                                Abrir
                            </button>
                        @else
                            <button class="btn btn-ghost btn-sm" style="color:#dc2626;border-color:#fca5a5;font-size:.75rem"
                                    onclick="openCloseModal({{ $period->id }}, '{{ $period->full_name }}')">
                                Cerrar
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

{{-- Modal: generar períodos --}}
<details class="overlay-modal" id="modal-gen">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-gen').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:400px">
        <div class="overlay-header">
            <h2>Crear períodos del año</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-gen').removeAttribute('open')" type="button">✕</button>
        </div>
        <div style="padding:1.25rem">
            <div class="form-group">
                <label class="form-label">Año</label>
                <input id="gen-year" type="number" class="input" value="{{ now()->year }}" min="2020" max="2099">
            </div>
            <p style="font-size:.8125rem;color:#6b7280;margin:.5rem 0 0">
                Se crearán los 12 períodos del año seleccionado en estado <strong>Cerrado</strong>. Los que ya existan no se modificarán.
            </p>
        </div>
        <div style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-gen').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-gen" onclick="generatePeriods()">Crear períodos</button>
        </div>
    </div>
</details>

{{-- Modal: cerrar período --}}
<details class="overlay-modal" id="modal-close">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-close').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:440px">
        <div class="overlay-header">
            <h2>Cerrar período</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-close').removeAttribute('open')" type="button">✕</button>
        </div>
        <div style="padding:1.25rem">
            <p>¿Cerrar el período <strong id="close-period-name"></strong>?</p>
            <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:.375rem;padding:.75rem;margin:.75rem 0;font-size:.8125rem;color:#991b1b">
                Al cerrar el período, <strong>ningún módulo</strong> podrá registrar transacciones con fechas dentro de este período. Esta acción se puede revertir reabriendo el período.
            </div>
            <div class="form-group">
                <label class="form-label">Notas de cierre (opcional)</label>
                <textarea id="close-notes" class="input" rows="2" maxlength="500" placeholder="Motivo del cierre, observaciones…"></textarea>
            </div>
        </div>
        <div style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-close').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btn-confirm-close" onclick="confirmClose()">Cerrar período</button>
        </div>
    </div>
</details>

<div id="period-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1f2937;color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.3)"></div>

<style>
.input-sm{padding:.3rem .6rem;font-size:.875rem}
.btn-sm{padding:.3rem .6rem;font-size:.8125rem}
</style>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';
let closingPeriodId = null;

async function togglePeriod(id, action, name) {
    const url  = `{{ url('admin/contabilidad/periodos') }}/${id}/${action}`;
    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({}),
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 700);
    } catch { showToast('Error de red.', false); }
}

function openCloseModal(id, name) {
    closingPeriodId = id;
    document.getElementById('close-period-name').textContent = name;
    document.getElementById('close-notes').value = '';
    document.getElementById('modal-close').setAttribute('open', '');
}

async function confirmClose() {
    if (!closingPeriodId) return;
    const notes = document.getElementById('close-notes').value.trim();
    const btn   = document.getElementById('btn-confirm-close');
    btn.disabled = true;
    try {
        const res  = await fetch(`{{ url('admin/contabilidad/periodos') }}/${closingPeriodId}/cerrar`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({notes}),
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 700);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

async function generatePeriods() {
    const year = parseInt(document.getElementById('gen-year').value);
    if (!year || year < 2020 || year > 2099) { showToast('Año inválido.', false); return; }
    const btn = document.getElementById('btn-gen');
    btn.disabled = true;
    try {
        const res  = await fetch('{{ route("admin.accounting.periodos.generar") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({year}),
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.href = `?year=${year}`, 700);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

function showToast(msg, ok) {
    const t = document.getElementById('period-toast');
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 4000);
}
</script>
</x-layouts.app>
