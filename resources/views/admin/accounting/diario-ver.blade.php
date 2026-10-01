@extends('layouts.app')

@section('title', 'Asiento '.$entry->entry_number)

@section('content')
@php
    $statusColors = [
        'borrador' => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Borrador'],
        'aprobado' => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Aprobado'],
        'anulado'  => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Anulado'],
    ];
    $sc = $statusColors[$entry->status] ?? $statusColors['borrador'];
    $money = fn($v) => '$'.number_format((float)$v, 2);
    $totalDebit  = $entry->lines->sum('debit');
    $totalCredit = $entry->lines->sum('credit');
    $typeColors = [
        'activo'=>'#3b82f6','pasivo'=>'#f59e0b','patrimonio'=>'#8b5cf6','gasto'=>'#ef4444','ingreso'=>'#22c55e',
    ];
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <div>
        <a href="{{ route('admin.accounting.diario') }}" style="font-size:.875rem;color:#2563eb;text-decoration:none">← Diario contable</a>
        <h1 style="margin:.25rem 0 .25rem;font-size:1.5rem;font-weight:700">{{ $entry->entry_number }}</h1>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <span class="badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['color'] }}40;font-size:.8125rem;padding:.3rem .75rem">
                {{ $sc['label'] }}
            </span>
            <span style="font-size:.875rem;color:#6b7280">{{ $entry->entry_date->format('d/m/Y') }}</span>
            @if($entry->reference)
                <span style="font-size:.875rem;color:#6b7280">Ref: <strong>{{ $entry->reference }}</strong></span>
            @endif
        </div>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        @if($entry->isEditable())
            <a href="{{ route('admin.accounting.diario.edit', $entry) }}" class="btn btn-ghost">✎ Editar</a>
            <button class="btn btn-primary" onclick="document.getElementById('modal-approve').setAttribute('open','')">
                ✓ Aprobar
            </button>
        @endif
        @if($entry->status === 'aprobado')
            <button class="btn btn-ghost" style="color:#dc2626;border-color:#fca5a5"
                    onclick="document.getElementById('modal-annul').setAttribute('open','')">
                Anular asiento
            </button>
        @endif
    </div>
</div>

{{-- Description --}}
<div class="card" style="padding:1.25rem;margin-bottom:1.25rem">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
        <div>
            <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;font-weight:600;letter-spacing:.04em">Descripción</div>
            <div style="margin-top:.25rem;font-weight:500">{{ $entry->description }}</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;font-weight:600;letter-spacing:.04em">Creado por</div>
            <div style="margin-top:.25rem">{{ $entry->creator?->name ?? '—' }}</div>
        </div>
        @if($entry->approver)
        <div>
            <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;font-weight:600;letter-spacing:.04em">Aprobado por</div>
            <div style="margin-top:.25rem">{{ $entry->approver->name }}
                <span style="font-size:.8rem;color:#6b7280">{{ $entry->approved_at?->format('d/m/Y H:i') }}</span>
            </div>
        </div>
        @endif
        @if($entry->notes)
        <div style="grid-column:1/-1">
            <div style="font-size:.75rem;color:#6b7280;text-transform:uppercase;font-weight:600;letter-spacing:.04em">Notas</div>
            <div style="margin-top:.25rem;white-space:pre-line;font-size:.875rem;color:#374151">{{ $entry->notes }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Lines table --}}
<div class="card" style="margin-bottom:1.25rem;overflow:hidden">
    <div style="padding:.875rem 1.25rem;border-bottom:1px solid #e5e7eb">
        <h2 style="margin:0;font-size:1rem;font-weight:700">Partidas del asiento</h2>
    </div>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:600px">
            <thead>
                <tr style="background:#f9fafb">
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:left;width:36px">#</th>
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:left">Código</th>
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:left">Cuenta</th>
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:left">Detalle</th>
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:right;width:120px">Debe</th>
                    <th style="padding:.6rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;text-align:right;width:120px">Haber</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entry->lines as $i => $line)
                @php $color = $typeColors[$line->account?->type] ?? '#6b7280'; @endphp
                <tr style="border-bottom:1px solid #f3f4f6">
                    <td style="padding:.65rem 1rem;color:#9ca3af;font-size:.8rem;text-align:center">{{ $i + 1 }}</td>
                    <td style="padding:.65rem 1rem">
                        <code style="color:{{ $color }};font-weight:600;font-size:.875rem">{{ $line->account?->code }}</code>
                    </td>
                    <td style="padding:.65rem 1rem;font-size:.875rem">{{ $line->account?->name ?? '—' }}</td>
                    <td style="padding:.65rem 1rem;font-size:.8125rem;color:#6b7280">{{ $line->description ?? '' }}</td>
                    <td style="padding:.65rem 1rem;text-align:right;font-variant-numeric:tabular-nums;font-size:.875rem">
                        @if($line->debit > 0)
                            <strong>{{ $money($line->debit) }}</strong>
                        @else
                            <span style="color:#d1d5db">—</span>
                        @endif
                    </td>
                    <td style="padding:.65rem 1rem;text-align:right;font-variant-numeric:tabular-nums;font-size:.875rem">
                        @if($line->credit > 0)
                            <strong>{{ $money($line->credit) }}</strong>
                        @else
                            <span style="color:#d1d5db">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700;border-top:2px solid #e5e7eb">
                    <td colspan="4" style="padding:.85rem 1rem;text-align:right;color:#6b7280;font-size:.875rem">TOTALES</td>
                    <td style="padding:.85rem 1rem;text-align:right;font-variant-numeric:tabular-nums;font-size:.9375rem">{{ $money($totalDebit) }}</td>
                    <td style="padding:.85rem 1rem;text-align:right;font-variant-numeric:tabular-nums;font-size:.9375rem">{{ $money($totalCredit) }}</td>
                </tr>
                @if(abs($totalDebit - $totalCredit) < 0.01)
                <tr>
                    <td colspan="6" style="padding:.5rem 1rem;text-align:center;font-size:.8125rem;color:#16a34a;background:#f0fdf4">
                        ✓ Asiento cuadrado — Debe = Haber
                    </td>
                </tr>
                @else
                <tr>
                    <td colspan="6" style="padding:.5rem 1rem;text-align:center;font-size:.8125rem;color:#dc2626;background:#fff5f5">
                        ✗ Asiento descuadrado — Diferencia: {{ $money(abs($totalDebit - $totalCredit)) }}
                    </td>
                </tr>
                @endif
            </tfoot>
        </table>
    </div>
</div>

{{-- Approve modal --}}
@if($entry->isEditable())
<details class="overlay-modal" id="modal-approve">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-approve').removeAttribute('open')"></div>
    <div class="overlay-panel overlay-panel-sm">
        <div class="overlay-header">
            <h2>Aprobar asiento</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-approve').removeAttribute('open')" type="button">✕</button>
        </div>
        <p>Al aprobar el asiento <strong>{{ $entry->entry_number }}</strong> quedará publicado en el diario y ya no podrá editarse.</p>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-approve').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="doApprove()">Aprobar asiento</button>
        </div>
    </div>
</details>
@endif

{{-- Annul modal --}}
@if($entry->status === 'aprobado')
<details class="overlay-modal" id="modal-annul">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-annul').removeAttribute('open')"></div>
    <div class="overlay-panel overlay-panel-sm">
        <div class="overlay-header">
            <h2>Anular asiento</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-annul').removeAttribute('open')" type="button">✕</button>
        </div>
        <p>Se generará un asiento de <strong>reversión automática</strong> para dejar el diario en cero. Ingrese el motivo:</p>
        <div class="form-group" style="margin-top:.75rem">
            <label class="form-label">Motivo de anulación <span class="required">*</span></label>
            <textarea id="annul-motivo" class="input" rows="3" maxlength="300" placeholder="Describa el motivo…"></textarea>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-annul').removeAttribute('open')">Cancelar</button>
            <button type="button" class="btn btn-danger" onclick="doAnnul()">Anular y revertir</button>
        </div>
    </div>
</details>
@endif

<div id="journal-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1f2937;color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.3)"></div>

<style>
.overlay-panel-sm{max-width:480px}
</style>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

async function doApprove() {
    const btn = event.target;
    btn.disabled = true;
    try {
        const res = await fetch('{{ route("admin.accounting.diario.approve", $entry) }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({})
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 800);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

async function doAnnul() {
    const motivo = document.getElementById('annul-motivo')?.value.trim();
    if (!motivo || motivo.length < 5) { showToast('Ingrese el motivo de anulación.', false); return; }
    const btn = event.target;
    btn.disabled = true;
    try {
        const res = await fetch('{{ route("admin.accounting.diario.annul", $entry) }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({motivo})
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 800);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
}

function showToast(msg, ok) {
    const t = document.getElementById('journal-toast');
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 4000);
}
</script>
@endsection
