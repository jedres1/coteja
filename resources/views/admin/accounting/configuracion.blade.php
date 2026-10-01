<x-layouts.app title="Configuración Contable | Coteja">
@php
    $typeColors = ['automatico' => ['bg'=>'#dbeafe','color'=>'#1e40af'], 'manual' => ['bg'=>'#f3e8ff','color'=>'#6b21a8']];
    $typeLabels = ['automatico' => 'Automático', 'manual' => 'Manual'];
    $accountsJson = $accounts->toJson();
    $packagesJson = $packages->map(fn($p) => [
        'id'                          => $p->id,
        'code'                        => $p->code,
        'name'                        => $p->name,
        'description'                 => $p->description,
        'type'                        => $p->type,
        'debit_account_id'            => $p->debit_account_id,
        'credit_account_id'           => $p->credit_account_id,
        'secondary_debit_account_id'  => $p->secondary_debit_account_id,
        'secondary_credit_account_id' => $p->secondary_credit_account_id,
        'is_active'                   => $p->is_active,
        'last_correlative'            => $p->last_correlative,
    ])->toJson();
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Configuración Contable</h1>
        <p style="color:#6b7280;margin:.25rem 0 0;font-size:.875rem">Gestión de paquetes contables y sus cuentas asociadas para generación automática de asientos.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal()">+ Nuevo paquete</button>
    </div>
</div>

{{-- Descripción paquetes --}}
<div class="card" style="padding:1rem 1.25rem;margin-bottom:1.25rem;background:#f0f9ff;border:1px solid #bae6fd">
    <strong style="color:#0369a1">¿Cómo funcionan los paquetes?</strong>
    <p style="margin:.4rem 0 0;font-size:.875rem;color:#0c4a6e">
        Cada transacción del sistema genera una partida contable usando el paquete correspondiente.
        El número de asiento tiene el formato <code>CÓDIGO + 9 dígitos</code> (ej: <code>FA000000001</code>).
        Configure las cuentas de <strong>Debe / Haber</strong> primarias para los montos totales,
        y las cuentas secundarias para líneas adicionales de inventario (COGS / Inventario).
        El paquete <strong>CG</strong> se usa para asientos creados directamente desde contabilidad.
    </p>
</div>

{{-- Tabla de paquetes --}}
<div class="card" style="margin-bottom:1.5rem;overflow:hidden">
    <div style="padding:.875rem 1.25rem;border-bottom:1px solid #e5e7eb">
        <h2 style="margin:0;font-size:1rem;font-weight:700">Paquetes Contables</h2>
    </div>
    <div style="overflow-x:auto">
        <table class="table" style="min-width:900px">
            <thead>
                <tr>
                    <th style="width:70px">Código</th>
                    <th style="width:110px">Nombre</th>
                    <th style="width:80px">Tipo</th>
                    <th>Cuenta Debe (primaria)</th>
                    <th>Cuenta Haber (primaria)</th>
                    <th>Debe secundario</th>
                    <th>Haber secundario</th>
                    <th style="width:70px;text-align:right">Correlat.</th>
                    <th style="width:70px;text-align:center">Estado</th>
                    <th style="width:80px">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($packages as $pkg)
                @php $tc = $typeColors[$pkg->type] ?? $typeColors['manual']; @endphp
                <tr>
                    <td>
                        <code style="font-weight:700;color:#1e40af;background:#dbeafe;padding:.2rem .5rem;border-radius:.25rem;font-size:.875rem">{{ $pkg->code }}</code>
                    </td>
                    <td style="font-weight:500;font-size:.875rem">{{ $pkg->name }}</td>
                    <td>
                        <span class="badge" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }};font-size:.75rem;padding:.2rem .5rem">
                            {{ $typeLabels[$pkg->type] ?? $pkg->type }}
                        </span>
                    </td>
                    <td class="text-sm text-muted">
                        @if($pkg->debitAccount)
                            <code style="color:#1d4ed8">{{ $pkg->debitAccount->code }}</code> {{ $pkg->debitAccount->name }}
                        @else
                            <span style="color:#d1d5db">— Sin configurar —</span>
                        @endif
                    </td>
                    <td class="text-sm text-muted">
                        @if($pkg->creditAccount)
                            <code style="color:#15803d">{{ $pkg->creditAccount->code }}</code> {{ $pkg->creditAccount->name }}
                        @else
                            <span style="color:#d1d5db">— Sin configurar —</span>
                        @endif
                    </td>
                    <td class="text-sm text-muted">
                        @if($pkg->secondaryDebitAccount)
                            <code style="color:#b45309">{{ $pkg->secondaryDebitAccount->code }}</code> {{ $pkg->secondaryDebitAccount->name }}
                        @else
                            <span style="color:#d1d5db">—</span>
                        @endif
                    </td>
                    <td class="text-sm text-muted">
                        @if($pkg->secondaryCreditAccount)
                            <code style="color:#7c3aed">{{ $pkg->secondaryCreditAccount->code }}</code> {{ $pkg->secondaryCreditAccount->name }}
                        @else
                            <span style="color:#d1d5db">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-size:.875rem;color:#374151">
                        {{ number_format($pkg->last_correlative) }}
                    </td>
                    <td style="text-align:center">
                        @if($pkg->is_active)
                            <span class="badge" style="background:#dcfce7;color:#166534;font-size:.7rem">Activo</span>
                        @else
                            <span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:.7rem">Inactivo</span>
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            <button class="btn-icon" title="Editar" onclick="editPackage({{ $pkg->id }})">✎</button>
                            @if($pkg->last_correlative === 0)
                                <button class="btn-icon btn-icon-danger" title="Eliminar"
                                        onclick="deletePackage('{{ route('admin.accounting.paquetes.destroy', $pkg) }}', '{{ $pkg->code }}')">✕</button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding:2rem">No hay paquetes configurados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal crear/editar paquete --}}
<details class="overlay-modal" id="modal-package">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="closeModal()"></div>
    <div class="overlay-panel" style="max-width:600px">
        <div class="overlay-header">
            <h2 id="modal-title">Nuevo paquete contable</h2>
            <button class="overlay-close" onclick="closeModal()" type="button">✕</button>
        </div>
        <div style="padding:1.25rem;display:grid;gap:1rem">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Código <span class="required">*</span></label>
                    <input id="p-code" type="text" class="input" maxlength="10" placeholder="ej: FA" style="text-transform:uppercase"
                           oninput="this.value=this.value.toUpperCase()">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo <span class="required">*</span></label>
                    <select id="p-type" class="input">
                        <option value="automatico">Automático</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <input id="p-name" type="text" class="input" maxlength="100" placeholder="Nombre descriptivo del paquete">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <input id="p-description" type="text" class="input" maxlength="500" placeholder="Uso o descripción del paquete (opcional)">
            </div>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:.25rem 0">
            <p style="font-size:.8125rem;color:#6b7280;margin:0">
                <strong>Cuentas primarias</strong> — usadas para el monto total de la transacción.
            </p>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Cuenta Debe</label>
                    <select id="p-debit" class="input"><option value="">— Ninguna —</option></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cuenta Haber</label>
                    <select id="p-credit" class="input"><option value="">— Ninguna —</option></select>
                </div>
            </div>

            <p style="font-size:.8125rem;color:#6b7280;margin:0">
                <strong>Cuentas secundarias</strong> — opcionales, para líneas de inventario (COGS / Activo de inventario).
            </p>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Debe secundario</label>
                    <select id="p-sec-debit" class="input"><option value="">— Ninguna —</option></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Haber secundario</label>
                    <select id="p-sec-credit" class="input"><option value="">— Ninguna —</option></select>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
                    <input type="checkbox" id="p-active" checked style="width:1rem;height:1rem">
                    <span class="form-label" style="margin:0">Paquete activo</span>
                </label>
            </div>
        </div>
        <div class="overlay-footer" style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-save-package" onclick="savePackage()">Guardar paquete</button>
        </div>
    </div>
</details>

{{-- Delete confirm modal --}}
<details class="overlay-modal" id="modal-delete">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-delete').removeAttribute('open')"></div>
    <div class="overlay-panel" style="max-width:400px">
        <div class="overlay-header">
            <h2>Eliminar paquete</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-delete').removeAttribute('open')" type="button">✕</button>
        </div>
        <p style="padding:1rem 1.25rem">¿Eliminar el paquete <strong id="del-code"></strong>? Esta acción no se puede deshacer.</p>
        <div class="overlay-footer" style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.75rem">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-delete').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-danger" id="btn-confirm-delete" onclick="confirmDelete()">Eliminar</button>
        </div>
    </div>
</details>

<div id="cfg-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1f2937;color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.3)"></div>

<style>
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:540px){.form-grid-2{grid-template-columns:1fr}}
.text-sm{font-size:.8125rem}
.text-muted{color:#6b7280}
.row-actions{display:flex;gap:.25rem}
.btn-icon{cursor:pointer;padding:.25rem .5rem;border-radius:.375rem;background:transparent;border:1px solid #e5e7eb;font-size:.85rem;text-decoration:none;color:inherit;line-height:1}
.btn-icon:hover{background:#f3f4f6}
.btn-icon-danger:hover{background:#fef2f2;border-color:#fca5a5;color:#dc2626}
.overlay-footer{display:flex;justify-content:flex-end;gap:.75rem;padding:1rem 1.25rem;border-top:1px solid #e5e7eb}
</style>

<script>
const ACCOUNTS = {!! $accountsJson !!};
const PACKAGES = {!! $packagesJson !!};
const CSRF     = document.querySelector('meta[name=csrf-token]')?.content || '';

let editingId     = null;
let deleteUrl     = null;

function buildAccountOptions(selectedId) {
    const groups = {};
    ACCOUNTS.forEach(a => {
        if (!groups[a.type]) groups[a.type] = [];
        groups[a.type].push(a);
    });
    const typeLabels = {activo:'Activos',pasivo:'Pasivos',patrimonio:'Patrimonio',gasto:'Gastos',ingreso:'Ingresos'};
    let html = '<option value="">— Ninguna —</option>';
    Object.entries(groups).forEach(([type, accs]) => {
        html += `<optgroup label="${typeLabels[type]||type}">`;
        accs.forEach(a => {
            const sel = selectedId && String(a.id) === String(selectedId) ? ' selected' : '';
            html += `<option value="${a.id}"${sel}>${a.label}</option>`;
        });
        html += '</optgroup>';
    });
    return html;
}

function populateSelects(pkg = null) {
    ['p-debit','p-credit','p-sec-debit','p-sec-credit'].forEach((id, i) => {
        const keys = ['debit_account_id','credit_account_id','secondary_debit_account_id','secondary_credit_account_id'];
        document.getElementById(id).innerHTML = buildAccountOptions(pkg?.[keys[i]]);
    });
}

function openModal(pkg = null) {
    editingId = pkg ? pkg.id : null;
    document.getElementById('modal-title').textContent = pkg ? 'Editar paquete — ' + pkg.code : 'Nuevo paquete contable';
    document.getElementById('p-code').value       = pkg?.code        || '';
    document.getElementById('p-code').disabled    = !!pkg;
    document.getElementById('p-name').value       = pkg?.name        || '';
    document.getElementById('p-description').value= pkg?.description || '';
    document.getElementById('p-type').value       = pkg?.type        || 'automatico';
    document.getElementById('p-active').checked   = pkg ? pkg.is_active : true;
    populateSelects(pkg);
    document.getElementById('modal-package').setAttribute('open', '');
}

function closeModal() {
    document.getElementById('modal-package').removeAttribute('open');
    editingId = null;
}

function editPackage(id) {
    const pkg = PACKAGES.find(p => p.id === id);
    if (pkg) openModal(pkg);
}

async function savePackage() {
    const code  = document.getElementById('p-code').value.trim().toUpperCase();
    const name  = document.getElementById('p-name').value.trim();
    const type  = document.getElementById('p-type').value;

    if (!name) { showToast('Ingrese el nombre del paquete.', false); return; }
    if (!editingId && !code) { showToast('Ingrese el código del paquete.', false); return; }

    const payload = {
        _token:                        CSRF,
        name,
        description:                   document.getElementById('p-description').value.trim(),
        type,
        debit_account_id:              document.getElementById('p-debit').value     || null,
        credit_account_id:             document.getElementById('p-credit').value    || null,
        secondary_debit_account_id:    document.getElementById('p-sec-debit').value || null,
        secondary_credit_account_id:   document.getElementById('p-sec-credit').value|| null,
        is_active:                     document.getElementById('p-active').checked,
    };

    let url, method;
    if (editingId) {
        url     = '{{ url("admin/contabilidad/paquetes") }}/' + editingId;
        method  = 'PUT';
        payload._method = 'PUT';
    } else {
        url    = '{{ route("admin.accounting.paquetes.store") }}';
        method = 'POST';
        payload.code = code;
    }

    const btn = document.getElementById('btn-save-package');
    btn.disabled = true;
    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 700);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

function deletePackage(url, code) {
    deleteUrl = url;
    document.getElementById('del-code').textContent = code;
    document.getElementById('modal-delete').setAttribute('open', '');
}

async function confirmDelete() {
    const btn = document.getElementById('btn-confirm-delete');
    btn.disabled = true;
    try {
        const res  = await fetch(deleteUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({_method:'DELETE'}),
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 700);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

function showToast(msg, ok) {
    const t = document.getElementById('cfg-toast');
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 3500);
}
</script>
</x-layouts.app>
