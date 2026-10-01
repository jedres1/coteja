@extends('layouts.app')

@section('title', 'Catálogo de Cuentas')

@section('content')
<div class="page-header">
    <h1 class="page-title">Catálogo de Cuentas</h1>
    <div class="page-actions">
        <details class="overlay-modal" id="modal-nueva-cuenta">
            <summary class="btn btn-primary">+ Nueva cuenta</summary>
            <div class="overlay-backdrop" data-overlay-close></div>
            <div class="overlay-panel">
                <div class="overlay-header">
                    <h2>Nueva cuenta contable</h2>
                    <button class="overlay-close" data-overlay-close type="button">✕</button>
                </div>
                <form id="form-nueva-cuenta" method="POST" action="{{ route('admin.accounting.catalogo.store') }}">
                    @csrf
                    @include('admin.accounting._form', ['account' => null, 'parents' => $parents])
                    <div class="form-actions">
                        <button type="button" class="btn btn-ghost" data-overlay-close>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar cuenta</button>
                    </div>
                </form>
            </div>
        </details>
    </div>
</div>

{{-- Stats bar --}}
<div class="stats-bar" style="margin-bottom:1rem;">
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => ''])) }}" class="stat-card {{ $filter === '' ? 'stat-active' : '' }}">
        <span class="stat-label">Total</span>
        <span class="stat-value">{{ $stats['total'] }}</span>
    </a>
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => 'activo'])) }}" class="stat-card {{ $filter === 'activo' ? 'stat-active' : '' }}" style="--accent:#3b82f6">
        <span class="stat-label">Activos</span>
        <span class="stat-value">{{ $stats['activo'] }}</span>
    </a>
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => 'pasivo'])) }}" class="stat-card {{ $filter === 'pasivo' ? 'stat-active' : '' }}" style="--accent:#f59e0b">
        <span class="stat-label">Pasivos</span>
        <span class="stat-value">{{ $stats['pasivo'] }}</span>
    </a>
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => 'patrimonio'])) }}" class="stat-card {{ $filter === 'patrimonio' ? 'stat-active' : '' }}" style="--accent:#8b5cf6">
        <span class="stat-label">Patrimonio</span>
        <span class="stat-value">{{ $stats['patrimonio'] }}</span>
    </a>
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => 'gasto'])) }}" class="stat-card {{ $filter === 'gasto' ? 'stat-active' : '' }}" style="--accent:#ef4444">
        <span class="stat-label">Gastos</span>
        <span class="stat-value">{{ $stats['gasto'] }}</span>
    </a>
    <a href="{{ route('admin.accounting.catalogo', array_merge(request()->query(), ['tipo' => 'ingreso'])) }}" class="stat-card {{ $filter === 'ingreso' ? 'stat-active' : '' }}" style="--accent:#22c55e">
        <span class="stat-label">Ingresos</span>
        <span class="stat-value">{{ $stats['ingreso'] }}</span>
    </a>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.accounting.catalogo') }}" class="filter-bar" style="margin-bottom:1rem;">
    @if($filter)
        <input type="hidden" name="tipo" value="{{ $filter }}">
    @endif
    <input type="search" name="search" value="{{ $search }}" placeholder="Buscar por código o nombre…" class="input" style="max-width:320px;">
    <button type="submit" class="btn btn-ghost">Buscar</button>
    @if($search)
        <a href="{{ route('admin.accounting.catalogo', $filter ? ['tipo' => $filter] : []) }}" class="btn btn-ghost">Limpiar</a>
    @endif
</form>

{{-- Table --}}
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th style="width:130px">Código</th>
                <th>Cuenta</th>
                <th style="width:110px">Tipo</th>
                <th style="width:100px">Naturaleza</th>
                <th style="width:90px">Nivel</th>
                <th style="width:80px">Estado</th>
                <th style="width:100px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
            @php
                $indent = ($account->level - 1) * 1.4;
                $typeColors = [
                    'activo'     => '#3b82f6',
                    'pasivo'     => '#f59e0b',
                    'patrimonio' => '#8b5cf6',
                    'gasto'      => '#ef4444',
                    'ingreso'    => '#22c55e',
                ];
                $color = $typeColors[$account->type] ?? '#6b7280';
            @endphp
            <tr class="{{ $account->level === 1 ? 'row-level-1' : ($account->level === 2 ? 'row-level-2' : '') }}">
                <td>
                    <code style="color:{{ $color }};font-weight:{{ $account->level <= 2 ? '600' : '400' }}">
                        {{ $account->code }}
                    </code>
                </td>
                <td>
                    <span style="padding-left:{{ $indent }}rem;display:block;font-weight:{{ $account->level === 1 ? '700' : ($account->level === 2 ? '600' : '400') }}">
                        @if($account->level > 1)
                            <span style="color:#9ca3af;margin-right:.25rem">{{ $account->level === 2 ? '├' : '└' }}</span>
                        @endif
                        {{ $account->name }}
                    </span>
                </td>
                <td>
                    <span class="badge" style="background:{{ $color }}20;color:{{ $color }};border:1px solid {{ $color }}40">
                        {{ $account->typeLabel() }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $account->nature === 'deudora' ? 'badge-blue' : 'badge-orange' }}">
                        {{ ucfirst($account->nature) }}
                    </span>
                </td>
                <td class="text-center text-muted">{{ $account->level }}</td>
                <td class="text-center">
                    @if($account->is_active)
                        <span class="badge badge-green">Activa</span>
                    @else
                        <span class="badge badge-gray">Inactiva</span>
                    @endif
                </td>
                <td>
                    <div class="row-actions">
                        <details class="overlay-modal">
                            <summary class="btn-icon" title="Editar">✎</summary>
                            <div class="overlay-backdrop" data-overlay-close></div>
                            <div class="overlay-panel">
                                <div class="overlay-header">
                                    <h2>Editar cuenta</h2>
                                    <button class="overlay-close" data-overlay-close type="button">✕</button>
                                </div>
                                <form method="POST" action="{{ route('admin.accounting.catalogo.update', $account) }}"
                                      data-ajax="true" data-reload="true">
                                    @csrf @method('PUT')
                                    @include('admin.accounting._form', ['account' => $account, 'parents' => $parents])
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-ghost" data-overlay-close>Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                    </div>
                                </form>
                            </div>
                        </details>

                        <details class="overlay-modal">
                            <summary class="btn-icon btn-icon-danger" title="Eliminar">✕</summary>
                            <div class="overlay-backdrop" data-overlay-close></div>
                            <div class="overlay-panel overlay-panel-sm">
                                <div class="overlay-header">
                                    <h2>Eliminar cuenta</h2>
                                    <button class="overlay-close" data-overlay-close type="button">✕</button>
                                </div>
                                <p>¿Eliminar la cuenta <strong>{{ $account->code }} - {{ $account->name }}</strong>?</p>
                                @if($account->children()->exists())
                                    <div class="alert alert-warning" style="margin-top:.75rem">
                                        Esta cuenta tiene subcuentas y no puede eliminarse.
                                    </div>
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-ghost" data-overlay-close>Cerrar</button>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('admin.accounting.catalogo.destroy', $account) }}"
                                          data-ajax="true" data-reload="true">
                                        @csrf @method('DELETE')
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-ghost" data-overlay-close>Cancelar</button>
                                            <button type="submit" class="btn btn-danger">Eliminar</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </details>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-muted" style="padding:2rem">
                    No se encontraron cuentas contables.
                    @if(!$search)
                        <br><small>Cargue el catálogo base desde la consola: <code>php artisan db:seed --class=AccountingAccountSeeder</code></small>
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit/Delete ajax responses --}}
<div id="accounting-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1f2937;color:#fff;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;box-shadow:0 4px 12px rgba(0,0,0,.3)">
</div>

<style>
.row-level-1 td { background:#f8fafc; }
.row-level-2 td { background:#fafafa; }
.stat-card { display:flex;flex-direction:column;align-items:center;padding:.5rem 1rem;border-radius:.5rem;border:1px solid #e5e7eb;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s; }
.stat-card:hover { border-color:var(--accent,#6b7280);box-shadow:0 0 0 2px color-mix(in srgb,var(--accent,#6b7280) 20%,transparent); }
.stat-active { border-color:var(--accent,#6b7280);background:color-mix(in srgb,var(--accent,#6b7280) 8%,white); }
.stats-bar { display:flex;gap:.75rem;flex-wrap:wrap; }
.stat-label { font-size:.75rem;color:#6b7280; }
.stat-value { font-size:1.25rem;font-weight:700;color:var(--accent,#374151); }
.badge-blue   { background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe; }
.badge-orange { background:#fffbeb;color:#d97706;border:1px solid #fde68a; }
.badge-green  { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.badge-gray   { background:#f9fafb;color:#6b7280;border:1px solid #e5e7eb; }
.row-actions  { display:flex;gap:.25rem;justify-content:center; }
.btn-icon     { cursor:pointer;padding:.25rem .5rem;border-radius:.375rem;background:transparent;border:1px solid #e5e7eb;font-size:.875rem;list-style:none; }
.btn-icon:hover { background:#f3f4f6; }
.btn-icon-danger:hover { background:#fef2f2;border-color:#fca5a5;color:#dc2626; }
.overlay-panel-sm { max-width:420px; }
.filter-bar { display:flex;gap:.5rem;align-items:center; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Ajax form handler for edit/delete inside overlays
    document.querySelectorAll('form[data-ajax]').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = form.querySelector('[type=submit]');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch(form.action, {
                    method: form.method.toUpperCase() === 'GET' ? 'POST' : form.method.toUpperCase(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form)
                });
                const json = await res.json();
                showToast(json.message || (res.ok ? 'Listo.' : 'Error.'), res.ok);
                if (res.ok && form.dataset.reload) {
                    setTimeout(() => window.location.reload(), 600);
                }
            } catch (err) {
                showToast('Error de red.', false);
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    });

    // Nueva cuenta form (full page reload on success)
    const formNueva = document.getElementById('form-nueva-cuenta');
    if (formNueva) {
        formNueva.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = formNueva.querySelector('[type=submit]');
            if (btn) btn.disabled = true;
            try {
                const res = await fetch(formNueva.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(formNueva)
                });
                const json = await res.json();
                showToast(json.message || (res.ok ? 'Cuenta guardada.' : 'Error.'), res.ok);
                if (res.ok) {
                    setTimeout(() => window.location.reload(), 700);
                }
            } catch (err) {
                showToast('Error de red.', false);
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }

    function showToast(msg, ok) {
        const t = document.getElementById('accounting-toast');
        t.textContent = msg;
        t.style.background = ok ? '#166534' : '#991b1b';
        t.style.display = 'block';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => { t.style.display = 'none'; }, 3000);
    }
});
</script>
@endsection
