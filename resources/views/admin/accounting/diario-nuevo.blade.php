<x-layouts.app :title="($entry ? 'Editar asiento '.$entry->entry_number : 'Nuevo asiento').' | Coteja'">
@php
    $typeGroups = [
        'activo'     => ['label'=>'Activos (1)',     'color'=>'#3b82f6'],
        'pasivo'     => ['label'=>'Pasivos (2)',     'color'=>'#f59e0b'],
        'patrimonio' => ['label'=>'Patrimonio (3)', 'color'=>'#8b5cf6'],
        'gasto'      => ['label'=>'Gastos (4)',      'color'=>'#ef4444'],
        'ingreso'    => ['label'=>'Ingresos (5)',    'color'=>'#22c55e'],
    ];
    $accountsJson = $accounts->map(fn($a) => [
        'id'     => $a->id,
        'code'   => $a->code,
        'name'   => $a->name,
        'type'   => $a->type,
        'nature' => $a->nature,
        'label'  => $a->code . ' — ' . $a->name,
    ])->values()->toJson();
    $costCentersJson = $costCenters->map(fn($c) => [
        'id' => $c->id,
        'label' => $c->code . ' — ' . $c->name,
    ])->values()->toJson();
    $existingLines = $entry ? $entry->lines->map(fn($l) => [
        'account_id'  => $l->account_id,
        'cost_center_id' => $l->cost_center_id,
        'description' => $l->description ?? '',
        'debit'       => $l->debit  > 0 ? number_format($l->debit,  2, '.', '') : '',
        'credit'      => $l->credit > 0 ? number_format($l->credit, 2, '.', '') : '',
    ])->values()->toJson() : '[]';
@endphp

<div class="page-header" style="margin-bottom:1.5rem">
    <div>
        <a href="{{ route('admin.accounting.diario.index') }}" class="back-link">← Diario contable</a>
        <h1 class="page-title" style="margin-top:.25rem">
            {{ $entry ? 'Editar asiento '.$entry->entry_number : 'Nuevo asiento contable' }}
        </h1>
    </div>
</div>

@if($cgPackage)
<div style="display:flex;align-items:center;gap:.75rem;background:#f0f9ff;border:1px solid #bae6fd;border-radius:.5rem;padding:.65rem 1rem;margin-bottom:1.25rem">
    <span style="font-size:.8125rem;color:#0369a1">
        Este asiento se registrará en el paquete
        <strong style="font-family:monospace;background:#dbeafe;color:#1d4ed8;padding:.1rem .4rem;border-radius:.25rem">{{ $cgPackage->code }}</strong>
        — {{ $cgPackage->name }}.
        El número de asiento se genera automáticamente al guardar.
    </span>
</div>
@endif

<div class="journal-form-layout">
    {{-- Header fields --}}
    <div class="card" style="padding:1.25rem;margin-bottom:1.25rem">
        <div class="form-grid form-grid-4">
            <div class="form-group">
                <label class="form-label">Fecha <span class="required">*</span></label>
                <input type="date" id="f-date" class="input" value="{{ $entry ? $entry->entry_date->toDateString() : $today }}" required>
            </div>
            <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Descripción / Concepto <span class="required">*</span></label>
                <input type="text" id="f-description" class="input" maxlength="300"
                       value="{{ $entry?->description ?? '' }}"
                       placeholder="Ej: Pago de alquiler mes de septiembre 2026">
            </div>
            <div class="form-group">
                <label class="form-label">Referencia</label>
                <input type="text" id="f-reference" class="input" maxlength="100"
                       value="{{ $entry?->reference ?? '' }}"
                       placeholder="N.° factura, cheque, etc.">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Notas internas</label>
                <textarea id="f-notes" class="input" rows="2" maxlength="2000"
                          placeholder="Observaciones opcionales…">{{ $entry?->notes ?? '' }}</textarea>
            </div>
        </div>
    </div>

    {{-- Lines --}}
    <div class="card" style="margin-bottom:1.25rem;overflow:hidden">
        <div style="padding:.875rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
            <h2 style="margin:0;font-size:1rem;font-weight:700">Partidas del asiento</h2>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addLine()">+ Agregar línea</button>
        </div>

        <div style="overflow-x:auto">
            <table class="table journal-lines-table" id="lines-table">
                <thead>
                    <tr>
                        <th style="width:36px">#</th>
                        <th>Cuenta contable</th>
                        <th style="min-width:150px">Centro de costo</th>
                        <th style="min-width:160px">Descripción de la partida</th>
                        <th style="width:130px;text-align:right">Debe ($)</th>
                        <th style="width:130px;text-align:right">Haber ($)</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="lines-body">
                    {{-- filled by JS --}}
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;font-weight:700">
                        <td colspan="4" style="text-align:right;color:#6b7280;padding:.75rem 1rem">TOTALES</td>
                        <td style="text-align:right;padding:.75rem 1rem;font-variant-numeric:tabular-nums" id="total-debit">$0.00</td>
                        <td style="text-align:right;padding:.75rem 1rem;font-variant-numeric:tabular-nums" id="total-credit">$0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Balance indicator --}}
        <div id="balance-bar" style="padding:.75rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;gap:1rem;align-items:center">
            <span id="balance-label" style="font-size:.875rem;font-weight:600"></span>
            <span id="balance-diff" style="font-size:.875rem"></span>
        </div>
    </div>

    {{-- Submit --}}
    <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <a href="{{ route('admin.accounting.diario.index') }}" class="btn btn-ghost">Cancelar</a>
        <button type="button" class="btn btn-secondary" onclick="submitForm('borrador')" id="btn-draft">
            Guardar borrador
        </button>
        <button type="button" class="btn btn-primary" onclick="submitForm('aprobado')" id="btn-approve">
            Aprobar y publicar
        </button>
    </div>
</div>


<style>
.back-link{font-size:.875rem;color:#2563eb;text-decoration:none}
.back-link:hover{text-decoration:underline}
.form-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
@media(max-width:700px){.form-grid-4{grid-template-columns:1fr 1fr}}
.journal-lines-table{width:100%;border-collapse:collapse;min-width:680px}
.journal-lines-table th,.journal-lines-table td{padding:.6rem .875rem;border-bottom:1px solid #e5e7eb;font-size:.875rem;vertical-align:middle}
.journal-lines-table th{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;background:#f9fafb}
.journal-lines-table tbody tr:hover{background:#fafafa}
.line-num{color:#9ca3af;font-size:.8rem;text-align:center}
.amount-input{text-align:right;font-variant-numeric:tabular-nums;width:100%;border:1px solid #e5e7eb;border-radius:.375rem;padding:.35rem .5rem;font-size:.875rem;background:transparent}
.amount-input:focus{outline:none;border-color:#2563eb}
.account-select,.cost-center-select{width:100%;border:1px solid #e5e7eb;border-radius:.375rem;padding:.35rem .5rem;font-size:.8125rem;background:transparent}
.account-select:focus,.cost-center-select:focus{outline:none;border-color:#2563eb}
.line-desc-input{width:100%;border:1px solid #e5e7eb;border-radius:.375rem;padding:.35rem .5rem;font-size:.8125rem;background:transparent}
.line-desc-input:focus{outline:none;border-color:#2563eb}
.remove-line-btn{background:none;border:none;cursor:pointer;color:#9ca3af;font-size:1rem;padding:.25rem;border-radius:.25rem}
.remove-line-btn:hover{color:#dc2626;background:#fef2f2}
.balanced{color:#16a34a}
.unbalanced{color:#dc2626}
.btn-secondary{background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe}
.btn-secondary:hover{background:#c7d2fe}
</style>

<script>
const ACCOUNTS = {!! $accountsJson !!};
const COST_CENTERS = {!! $costCentersJson !!};
const EXISTING = {!! $existingLines !!};
const ACTION_URL = '{{ $entry ? route("admin.accounting.diario.update", $entry) : route("admin.accounting.diario.store") }}';
const IS_EDIT = {{ $entry ? 'true' : 'false' }};

let lineCount = 0;

function accountOptions(selectedId = null) {
    const groups = {};
    ACCOUNTS.forEach(a => {
        if (!groups[a.type]) groups[a.type] = [];
        groups[a.type].push(a);
    });
    const typeLabels = {
        activo:'Activos (1)', pasivo:'Pasivos (2)', patrimonio:'Patrimonio (3)',
        gasto:'Gastos (4)', ingreso:'Ingresos (5)'
    };
    let html = '<option value="">— Seleccione cuenta —</option>';
    Object.entries(groups).forEach(([type, accs]) => {
        html += `<optgroup label="${typeLabels[type] || type}">`;
        accs.forEach(a => {
            const sel = selectedId && String(a.id) === String(selectedId) ? ' selected' : '';
            html += `<option value="${a.id}"${sel}>${a.code} — ${a.name}</option>`;
        });
        html += '</optgroup>';
    });
    return html;
}

function costCenterOptions(selectedId = null) {
    let html = '<option value="">— Sin centro —</option>';
    COST_CENTERS.forEach(c => {
        const sel = selectedId && String(c.id) === String(selectedId) ? ' selected' : '';
        html += `<option value="${c.id}"${sel}>${c.label}</option>`;
    });
    return html;
}

function addLine(data = {}) {
    lineCount++;
    const idx = lineCount;
    const tr = document.createElement('tr');
    tr.id = 'line-' + idx;
    tr.innerHTML = `
        <td class="line-num">${document.querySelectorAll('#lines-body tr').length + 1}</td>
        <td>
            <select class="account-select" data-line="${idx}" onchange="updateTotals()">
                ${accountOptions(data.account_id)}
            </select>
        </td>
        <td>
            <select class="cost-center-select" data-line="${idx}">
                ${costCenterOptions(data.cost_center_id)}
            </select>
        </td>
        <td>
            <input type="text" class="line-desc-input" data-line="${idx}" maxlength="255"
                   value="${escHtml(data.description || '')}" placeholder="Detalle opcional">
        </td>
        <td>
            <input type="number" class="amount-input debit-input" data-line="${idx}"
                   value="${data.debit || ''}" min="0" step="0.01" placeholder="0.00"
                   oninput="handleAmountInput(this,'credit',${idx})">
        </td>
        <td>
            <input type="number" class="amount-input credit-input" data-line="${idx}"
                   value="${data.credit || ''}" min="0" step="0.01" placeholder="0.00"
                   oninput="handleAmountInput(this,'debit',${idx})">
        </td>
        <td>
            <button type="button" class="remove-line-btn" onclick="removeLine(${idx})" title="Quitar línea">✕</button>
        </td>`;
    document.getElementById('lines-body').appendChild(tr);
    renumberLines();
    updateTotals();
}

function removeLine(idx) {
    const rows = document.querySelectorAll('#lines-body tr');
    if (rows.length <= 2) { coteja.showToast('El asiento debe tener al menos 2 líneas.', false); return; }
    document.getElementById('line-' + idx)?.remove();
    renumberLines();
    updateTotals();
}

function renumberLines() {
    document.querySelectorAll('#lines-body tr').forEach((tr, i) => {
        const n = tr.querySelector('.line-num');
        if (n) n.textContent = i + 1;
    });
}

function handleAmountInput(el, oppositeClass, idx) {
    const v = parseFloat(el.value);
    if (!isNaN(v) && v > 0) {
        const opp = document.querySelector('.' + oppositeClass + '-input[data-line="' + idx + '"]');
        if (opp) { opp.value = ''; }
    }
    updateTotals();
}

function updateTotals() {
    let totalD = 0, totalC = 0;
    document.querySelectorAll('.debit-input').forEach(i => totalD += parseFloat(i.value) || 0);
    document.querySelectorAll('.credit-input').forEach(i => totalC += parseFloat(i.value) || 0);
    document.getElementById('total-debit').textContent  = '$' + totalD.toFixed(2);
    document.getElementById('total-credit').textContent = '$' + totalC.toFixed(2);

    const diff = Math.abs(totalD - totalC);
    const bar  = document.getElementById('balance-bar');
    const lbl  = document.getElementById('balance-label');
    const dif  = document.getElementById('balance-diff');

    if (totalD === 0 && totalC === 0) {
        lbl.textContent = '';
        dif.textContent = '';
        bar.style.background = '';
        return;
    }
    if (diff < 0.005) {
        lbl.className = 'balanced';
        lbl.textContent = '✓ Asiento cuadrado';
        dif.textContent = '';
        bar.style.background = '#f0fdf4';
    } else {
        lbl.className = 'unbalanced';
        lbl.textContent = '✗ Asiento descuadrado';
        dif.textContent = 'Diferencia: $' + diff.toFixed(2);
        bar.style.background = '#fff5f5';
    }
}

function collectLines() {
    const lines = [];
    document.querySelectorAll('#lines-body tr').forEach(tr => {
        const idx = tr.id.replace('line-', '');
        lines.push({
            account_id:  tr.querySelector('.account-select')?.value  || '',
            cost_center_id: tr.querySelector('.cost-center-select')?.value || '',
            description: tr.querySelector('.line-desc-input')?.value || '',
            debit:       parseFloat(tr.querySelector('.debit-input')?.value)  || 0,
            credit:      parseFloat(tr.querySelector('.credit-input')?.value) || 0,
        });
    });
    return lines;
}

async function submitForm(action) {
    const description = document.getElementById('f-description').value.trim();
    const date        = document.getElementById('f-date').value;
    if (!description) { coteja.showToast('Ingrese la descripción del asiento.', false); return; }
    if (!date)        { coteja.showToast('Seleccione la fecha.', false); return; }

    const payload = {
        entry_date:  date,
        description,
        reference:   document.getElementById('f-reference').value.trim(),
        notes:       document.getElementById('f-notes').value.trim(),
        action,
        lines: collectLines(),
    };

    const btn = action === 'aprobado' ? document.getElementById('btn-approve') : document.getElementById('btn-draft');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    try {
        const res = IS_EDIT
            ? await window.axios.put(ACTION_URL, payload)
            : await window.axios.post(ACTION_URL, payload);
        if (res.data.redirect) {
            coteja.showToast(res.data.message, true);
            setTimeout(() => window.location.href = res.data.redirect, 700);
        } else {
            coteja.showToast(res.data.message || res.data.error || 'Error al guardar.', false);
        }
    } catch (e) {
        coteja.showToast(e.response?.data?.message || 'Error de red.', false);
    } finally {
        btn.disabled = false;
        btn.textContent = action === 'aprobado' ? 'Aprobar y publicar' : 'Guardar borrador';
    }
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init: load existing lines or add 2 blank lines
document.addEventListener('DOMContentLoaded', function () {
    if (EXISTING.length >= 2) {
        EXISTING.forEach(l => addLine(l));
    } else {
        addLine(); addLine();
    }
});
</script>
</x-layouts.app>
