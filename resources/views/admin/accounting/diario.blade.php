@extends('layouts.app')

@section('title', 'Diario Contable')

@section('content')
@php
    $statusColors = [
        'borrador' => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Borrador'],
        'aprobado' => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Aprobado'],
        'anulado'  => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Anulado'],
    ];
    $money = fn($v) => '$'.number_format((float)$v, 2);
@endphp

<div class="page-header">
    <h1 class="page-title">Diario Contable</h1>
    <div class="page-actions">
        <a href="{{ route('admin.accounting.diario.create') }}" class="btn btn-primary">+ Nuevo asiento</a>
    </div>
</div>

{{-- Stats --}}
<div class="stats-bar" style="margin-bottom:1.25rem">
    <div class="stat-card">
        <span class="stat-label">Total asientos</span>
        <span class="stat-value">{{ $stats['total'] }}</span>
    </div>
    <div class="stat-card" style="--accent:#f59e0b">
        <span class="stat-label">Borradores</span>
        <span class="stat-value" style="color:#d97706">{{ $stats['borrador'] }}</span>
    </div>
    <div class="stat-card" style="--accent:#22c55e">
        <span class="stat-label">Aprobados</span>
        <span class="stat-value" style="color:#16a34a">{{ $stats['aprobado'] }}</span>
    </div>
    <div class="stat-card" style="--accent:#ef4444">
        <span class="stat-label">Anulados</span>
        <span class="stat-value" style="color:#dc2626">{{ $stats['anulado'] }}</span>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('admin.accounting.diario') }}" class="filter-bar" style="margin-bottom:1rem">
    <div class="filter-group">
        <label class="filter-label">Desde</label>
        <input type="date" name="from" value="{{ $from }}" class="input input-sm">
    </div>
    <div class="filter-group">
        <label class="filter-label">Hasta</label>
        <input type="date" name="to" value="{{ $to }}" class="input input-sm">
    </div>
    <div class="filter-group">
        <label class="filter-label">Estado</label>
        <select name="status" class="input input-sm">
            <option value="">Todos</option>
            <option value="borrador" {{ $status === 'borrador' ? 'selected' : '' }}>Borrador</option>
            <option value="aprobado" {{ $status === 'aprobado' ? 'selected' : '' }}>Aprobado</option>
            <option value="anulado"  {{ $status === 'anulado'  ? 'selected' : '' }}>Anulado</option>
        </select>
    </div>
    <div class="filter-group" style="flex:1;min-width:180px">
        <label class="filter-label">Buscar</label>
        <input type="search" name="search" value="{{ $search }}" placeholder="N.° asiento, descripción…" class="input input-sm">
    </div>
    <button type="submit" class="btn btn-ghost btn-sm">Filtrar</button>
    <a href="{{ route('admin.accounting.diario') }}" class="btn btn-ghost btn-sm">Limpiar</a>
</form>

{{-- Table --}}
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:130px">N.° Asiento</th>
                <th style="width:100px">Fecha</th>
                <th>Descripción</th>
                <th style="width:120px">Referencia</th>
                <th style="width:90px;text-align:right">Total</th>
                <th style="width:60px;text-align:center">Líneas</th>
                <th style="width:95px">Estado</th>
                <th style="width:90px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
            @php $sc = $statusColors[$entry->status] ?? $statusColors['borrador']; @endphp
            <tr>
                <td>
                    <a href="{{ route('admin.accounting.diario.show', $entry) }}" class="link-code">
                        {{ $entry->entry_number }}
                    </a>
                </td>
                <td class="text-muted">{{ $entry->entry_date->format('d/m/Y') }}</td>
                <td>
                    <span title="{{ $entry->description }}">
                        {{ Str::limit($entry->description, 60) }}
                    </span>
                    @if($entry->notes)
                        <span class="text-muted" style="font-size:.75rem;display:block">{{ Str::limit($entry->notes, 40) }}</span>
                    @endif
                </td>
                <td class="text-muted text-sm">{{ $entry->reference ?? '—' }}</td>
                <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">
                    {{ $money($entry->lines->sum('debit')) }}
                </td>
                <td style="text-align:center;color:#6b7280">{{ $entry->lines->count() }}</td>
                <td>
                    <span class="badge" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['color'] }}40">
                        {{ $sc['label'] }}
                    </span>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="{{ route('admin.accounting.diario.show', $entry) }}" class="btn-icon" title="Ver">👁</a>
                        @if($entry->isEditable())
                            <a href="{{ route('admin.accounting.diario.edit', $entry) }}" class="btn-icon" title="Editar">✎</a>
                            <button class="btn-icon btn-icon-danger" title="Eliminar"
                                    onclick="confirmDelete('{{ route('admin.accounting.diario.destroy', $entry) }}', '{{ $entry->entry_number }}')">✕</button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-muted" style="padding:2.5rem">
                    No hay asientos contables en el período seleccionado.
                    <br><a href="{{ route('admin.accounting.diario.create') }}" class="btn btn-primary" style="margin-top:.75rem;display:inline-block">Crear primer asiento</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($entries->hasPages())
        <div style="padding:.75rem 1rem;border-top:1px solid #e5e7eb">
            {{ $entries->links() }}
        </div>
    @endif
</div>

{{-- Delete confirm modal --}}
<details class="overlay-modal" id="modal-delete">
    <summary style="display:none"></summary>
    <div class="overlay-backdrop" onclick="document.getElementById('modal-delete').removeAttribute('open')"></div>
    <div class="overlay-panel overlay-panel-sm">
        <div class="overlay-header">
            <h2>Eliminar borrador</h2>
            <button class="overlay-close" onclick="document.getElementById('modal-delete').removeAttribute('open')" type="button">✕</button>
        </div>
        <p>¿Eliminar el asiento borrador <strong id="delete-entry-number"></strong>?<br>Esta acción no se puede deshacer.</p>
        <form id="form-delete" method="POST">
            @csrf @method('DELETE')
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-delete').removeAttribute('open')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Eliminar</button>
            </div>
        </form>
    </div>
</details>

<div id="journal-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1f2937;color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.3)"></div>

<style>
.filter-bar{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:.25rem}
.filter-label{font-size:.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em}
.input-sm{padding:.3rem .6rem;font-size:.875rem}
.btn-sm{padding:.35rem .75rem;font-size:.875rem}
.stats-bar{display:flex;gap:.75rem;flex-wrap:wrap}
.stat-card{display:flex;flex-direction:column;align-items:center;padding:.5rem 1.25rem;border-radius:.5rem;border:1px solid #e5e7eb}
.stat-label{font-size:.75rem;color:#6b7280}
.stat-value{font-size:1.35rem;font-weight:700}
.link-code{color:#2563eb;font-weight:600;text-decoration:none;font-family:monospace}
.link-code:hover{text-decoration:underline}
.text-sm{font-size:.8rem}
.row-actions{display:flex;gap:.25rem}
.btn-icon{cursor:pointer;padding:.25rem .5rem;border-radius:.375rem;background:transparent;border:1px solid #e5e7eb;font-size:.85rem;list-style:none;text-decoration:none;color:inherit;line-height:1}
.btn-icon:hover{background:#f3f4f6}
.btn-icon-danger:hover{background:#fef2f2;border-color:#fca5a5;color:#dc2626}
.overlay-panel-sm{max-width:440px}
</style>

<script>
function confirmDelete(url, number) {
    document.getElementById('delete-entry-number').textContent = number;
    document.getElementById('form-delete').action = url;
    document.getElementById('modal-delete').setAttribute('open', '');
}

document.getElementById('form-delete')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    try {
        const res = await fetch(this.action, {
            method: 'POST',
            headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: new FormData(this)
        });
        const json = await res.json();
        showToast(json.message, res.ok);
        if (res.ok) setTimeout(() => window.location.reload(), 700);
    } catch { showToast('Error de red.', false); } finally { btn.disabled = false; }
});

function showToast(msg, ok) {
    const t = document.getElementById('journal-toast');
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', 3500);
}
</script>
@endsection
