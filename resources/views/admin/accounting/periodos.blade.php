<x-layouts.app title="Períodos Contables | Coteja">
@php
    $statusStyle = [
        'abierto' => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Abierto'],
        'cerrado' => ['bg'=>'#f3f4f6','color'=>'#6b7280','label'=>'Cerrado'],
    ];
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $periodByMonth = $periods->keyBy('month');
    $openCount = $periods->where('status', 'abierto')->count();
    $closedCount = $periods->where('status', 'cerrado')->count();
    $yearStatus = $yearPeriod?->status ?? 'cerrado';
    $ys = $statusStyle[$yearStatus] ?? $statusStyle['cerrado'];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Períodos Contables</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">
            Gestione el ejercicio anual y sus 12 meses contables. Para registrar transacciones, el año y el mes deben estar abiertos.
        </p>
    </div>
    <div class="page-actions">
        <form method="GET" style="display:flex;gap:.5rem;align-items:center">
            <select name="year" class="input input-sm" onchange="this.form.submit()">
                @foreach($availableYears as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
                <option value="{{ now()->year + 1 }}" @selected((now()->year + 1) == $year)>{{ now()->year + 1 }}</option>
            </select>
        </form>
        <button class="btn btn-ghost btn-sm" onclick="openGenerate({{ $year }})">+ Crear ejercicio</button>
    </div>
</div>

<div class="period-year-card">
    <div>
        <div class="eyebrow">Ejercicio contable</div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-top:.25rem">
            <h2 style="margin:0;font-size:1.35rem">Año {{ $year }}</h2>
            <span class="badge" style="background:{{ $ys['bg'] }};color:{{ $ys['color'] }};border:1px solid {{ $ys['color'] }}30">
                {{ $ys['label'] }}
            </span>
        </div>
        @if($yearPeriod)
            <p style="margin:.45rem 0 0;color:#6b7280;font-size:.875rem">
                {{ $yearPeriod->name }}
                @if($yearPeriod->closed_at)
                    · Cerrado {{ $yearPeriod->closed_at->format('d/m/Y H:i') }}
                @elseif($yearPeriod->opened_at)
                    · Abierto {{ $yearPeriod->opened_at->format('d/m/Y H:i') }}
                @endif
            </p>
            @if($yearPeriod->notes)
                <p style="margin:.35rem 0 0;color:#6b7280;font-size:.8125rem">{{ $yearPeriod->notes }}</p>
            @endif
        @else
            <p style="margin:.45rem 0 0;color:#92400e;font-size:.875rem">Este ejercicio aún no existe. Créelo para generar sus 12 meses.</p>
        @endif
    </div>
    <div class="year-actions">
        @if(!$yearPeriod)
            <button class="btn btn-primary" onclick="openGenerate({{ $year }})">Crear ejercicio {{ $year }}</button>
        @elseif($yearPeriod->isOpen())
            <button class="btn btn-ghost" style="color:#dc2626;border-color:#fca5a5" onclick="openCloseYearModal({{ $yearPeriod->id }}, '{{ $year }}')">Cerrar año</button>
        @else
            <button class="btn btn-ghost" style="color:#16a34a;border-color:#86efac" onclick="toggleYear({{ $yearPeriod->id }}, 'abrir')">Abrir año</button>
        @endif
    </div>
</div>

<div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <div class="period-stat">
        <div>Meses abiertos</div>
        <strong style="color:#16a34a">{{ $openCount }}</strong>
    </div>
    <div class="period-stat">
        <div>Meses cerrados</div>
        <strong>{{ $closedCount }}</strong>
    </div>
    <div style="flex:3;min-width:260px;background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.75rem 1rem">
        <div style="font-size:.8125rem;color:#92400e">
            <strong>Regla:</strong> cerrar el año cierra también sus meses abiertos. Al reabrir el año, los meses permanecen cerrados hasta que los abra individualmente.
        </div>
    </div>
</div>

<div class="card" style="overflow:hidden">
    <div style="padding:.875rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1rem;font-weight:700">Meses del ejercicio {{ $year }}</h2>
        <span style="font-size:.8125rem;color:#6b7280">{{ $periods->count() }} de 12 meses creados</span>
    </div>

    @if(!$yearPeriod || $periods->isEmpty())
        <div style="padding:2rem;text-align:center;color:#6b7280">
            No hay meses creados para el ejercicio {{ $year }}.
            <br>
            <button class="btn btn-primary" style="margin-top:.75rem" onclick="openGenerate({{ $year }})">Crear ejercicio y 12 meses</button>
        </div>
    @else
        <div class="months-grid">
            @for($month = 1; $month <= 12; $month++)
                @php
                    $period = $periodByMonth->get($month);
                    $status = $period?->status ?? 'cerrado';
                    $ss = $statusStyle[$status] ?? $statusStyle['cerrado'];
                    $isCurrent = $month == now()->month && $year == now()->year;
                @endphp
                <div class="month-card {{ $isCurrent ? 'current-month' : '' }}">
                    <div class="month-card-head">
                        <div>
                            <div class="month-num">{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}</div>
                            <strong>{{ $months[$month] }}</strong>
                        </div>
                        <span class="badge" style="background:{{ $ss['bg'] }};color:{{ $ss['color'] }};border:1px solid {{ $ss['color'] }}30">
                            {{ $period ? $ss['label'] : 'No creado' }}
                        </span>
                    </div>
                    <div class="month-meta">
                        @if($isCurrent)
                            <span style="color:#1d4ed8;font-weight:600">Mes actual</span>
                        @endif
                        @if($period?->opened_at)
                            <span>Abierto: {{ $period->opened_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($period?->closed_at)
                            <span>Cerrado: {{ $period->closed_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($period?->notes)
                            <span>{{ $period->notes }}</span>
                        @endif
                    </div>
                    <div class="month-actions">
                        @if(!$period)
                            <button class="btn btn-ghost btn-sm" onclick="openGenerate({{ $year }})">Crear meses</button>
                        @elseif($period->isOpen())
                            <button class="btn btn-ghost btn-sm" style="color:#dc2626;border-color:#fca5a5" onclick="openCloseMonthModal({{ $period->id }}, '{{ $period->full_name }}')">Cerrar mes</button>
                        @else
                            <button class="btn btn-ghost btn-sm" style="color:#16a34a;border-color:#86efac" onclick="toggleMonth({{ $period->id }}, 'abrir')" @disabled(!$yearPeriod->isOpen())>
                                Abrir mes
                            </button>
                        @endif
                    </div>
                </div>
            @endfor
        </div>
    @endif
</div>

<details class="overlay-modal" id="modal-gen">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-gen').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:420px">
        <div class="overlay-header">
            <h2>Crear ejercicio contable</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-gen').removeAttribute('open')" type="button">✕</button>
        </div>
        <div style="padding:1.25rem">
            <div class="form-group">
                <label class="form-label">Año</label>
                <input id="gen-year" type="number" class="input" value="{{ $year }}" min="2020" max="2099">
            </div>
            <p style="font-size:.8125rem;color:#6b7280;margin:.5rem 0 0">
                Se creará el ejercicio anual y sus 12 meses en estado <strong>Cerrado</strong>. Lo existente no se modifica.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-gen').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-gen" onclick="generatePeriods()">Crear ejercicio</button>
        </div>
    </div>
</details>

<details class="overlay-modal" id="modal-close-month">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-close-month').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:440px">
        <div class="overlay-header">
            <h2>Cerrar mes</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-close-month').removeAttribute('open')" type="button">✕</button>
        </div>
        <div style="padding:1.25rem">
            <p>¿Cerrar el mes <strong id="close-month-name"></strong>?</p>
            <div class="warning-box">No se podrán registrar transacciones dentro de ese mes hasta reabrirlo.</div>
            <div class="form-group">
                <label class="form-label">Notas de cierre</label>
                <textarea id="close-month-notes" class="input" rows="2" maxlength="500"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-close-month').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btn-confirm-close-month" onclick="confirmCloseMonth()">Cerrar mes</button>
        </div>
    </div>
</details>

<details class="overlay-modal" id="modal-close-year">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-close-year').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:460px">
        <div class="overlay-header">
            <h2>Cerrar año contable</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-close-year').removeAttribute('open')" type="button">✕</button>
        </div>
        <div style="padding:1.25rem">
            <p>¿Cerrar el ejercicio <strong id="close-year-name"></strong>?</p>
            <div class="warning-box">Esto cerrará también todos los meses abiertos de este año.</div>
            <div class="form-group">
                <label class="form-label">Notas de cierre</label>
                <textarea id="close-year-notes" class="input" rows="2" maxlength="500"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-close-year').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btn-confirm-close-year" onclick="confirmCloseYear()">Cerrar año</button>
        </div>
    </div>
</details>


<style>
.input-sm{padding:.3rem .6rem;font-size:.875rem}
.btn-sm{padding:.3rem .6rem;font-size:.8125rem}
.eyebrow{font-size:.72rem;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.period-year-card{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem;padding:1rem 1.25rem;border:1px solid #e5e7eb;background:#fff;border-radius:.5rem}
.year-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
.period-stat{flex:1;min-width:160px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:.5rem;padding:.75rem 1rem}
.period-stat div{font-size:.75rem;color:#6b7280;font-weight:600;text-transform:uppercase}
.period-stat strong{display:block;font-size:1.5rem;color:#374151}
.months-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;padding:1rem}
.month-card{border:1px solid #e5e7eb;border-radius:.5rem;padding:.875rem;background:#fff;display:grid;gap:.75rem;min-height:160px}
.month-card.current-month{border-color:#93c5fd;background:#eff6ff}
.month-card-head{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}
.month-num{font-size:.75rem;color:#6b7280;font-weight:700}
.month-meta{display:grid;gap:.25rem;min-height:42px;font-size:.78rem;color:#6b7280}
.month-actions{display:flex;justify-content:flex-end;margin-top:auto}
.modal-footer{padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem}
.warning-box{background:#fff5f5;border:1px solid #fca5a5;border-radius:.375rem;padding:.75rem;margin:.75rem 0;font-size:.8125rem;color:#991b1b}
button:disabled{opacity:.45;cursor:not-allowed}
@media(max-width:900px){.months-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.period-year-card{align-items:flex-start;flex-direction:column}}
@media(max-width:560px){.months-grid{grid-template-columns:1fr}}
</style>

<script>
let closingMonthId = null;
let closingYearId = null;

function openGenerate(year) {
    document.getElementById('gen-year').value = year;
    document.getElementById('modal-gen').setAttribute('open', '');
}

async function toggleMonth(id, action) {
    await coteja.postJson(`{{ url('admin/contabilidad/periodos') }}/${id}/${action}`, {});
}

async function toggleYear(id, action) {
    await coteja.postJson(`{{ url('admin/contabilidad/periodos/anios') }}/${id}/${action}`, {});
}

function openCloseMonthModal(id, name) {
    closingMonthId = id;
    document.getElementById('close-month-name').textContent = name;
    document.getElementById('close-month-notes').value = '';
    document.getElementById('modal-close-month').setAttribute('open', '');
}

async function confirmCloseMonth() {
    if (!closingMonthId) return;
    const btn = document.getElementById('btn-confirm-close-month');
    btn.disabled = true;
    await coteja.postJson(`{{ url('admin/contabilidad/periodos') }}/${closingMonthId}/cerrar`, {
        notes: document.getElementById('close-month-notes').value.trim(),
    });
    btn.disabled = false;
}

function openCloseYearModal(id, year) {
    closingYearId = id;
    document.getElementById('close-year-name').textContent = year;
    document.getElementById('close-year-notes').value = '';
    document.getElementById('modal-close-year').setAttribute('open', '');
}

async function confirmCloseYear() {
    if (!closingYearId) return;
    const btn = document.getElementById('btn-confirm-close-year');
    btn.disabled = true;
    await coteja.postJson(`{{ url('admin/contabilidad/periodos/anios') }}/${closingYearId}/cerrar`, {
        notes: document.getElementById('close-year-notes').value.trim(),
    });
    btn.disabled = false;
}

async function generatePeriods() {
    const year = parseInt(document.getElementById('gen-year').value);
    if (!year || year < 2020 || year > 2099) { coteja.showToast('Año inválido.', false); return; }
    const btn = document.getElementById('btn-gen');
    btn.disabled = true;
    await coteja.postJson('{{ route("admin.accounting.periodos.generar") }}', {year}, () => {
        window.location.href = `?year=${year}`;
    });
    btn.disabled = false;
}
</script>
</x-layouts.app>
