<x-layouts.app title="Bodegas | Inventario">
    <style>
        .inv-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; min-width:320px; max-width:500px; width:100%; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .overlay-header h3 { margin:0; font-size:16px; }
        .form-stack { display:grid; gap:12px; }
        .form-stack label { display:grid; gap:5px; font-size:13px; font-weight:600; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .btn.secondary { background:#4b5563; } .btn.sm { min-height:30px; padding:5px 10px; font-size:12px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .empty-state { padding:48px; text-align:center; color:var(--muted); font-size:15px; }
        .address-cell { max-width:280px; font-size:12px; color:var(--muted); }
    </style>

    <div class="inv-header">
        <div>
            <h1 style="margin:0;font-size:26px">Bodegas</h1>
            <p class="muted" style="margin:4px 0 0">Administra las bodegas donde se almacenan los productos.</p>
        </div>
        <button class="btn" type="button" onclick="openForm()">Nueva bodega</button>
    </div>

    @if(session('status'))
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--ok);font-size:13px">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--bad);font-size:13px">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($warehouses->isEmpty())
        <div class="empty-state">No hay bodegas registradas. Cree una para comenzar a controlar inventario.</div>
    @else
        <div class="card" style="padding:0;overflow:hidden">
            <table>
                <thead><tr><th>Nombre</th><th>Dirección</th><th>Teléfono</th><th>Movimientos</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach($warehouses as $wh)
                        <tr>
                            <td><strong>{{ $wh->name }}</strong></td>
                            <td class="address-cell">{{ $wh->address }}</td>
                            <td>{{ $wh->phone ?: '—' }}</td>
                            <td>{{ $wh->movements_count }}</td>
                            <td>
                                <button class="btn secondary sm" type="button"
                                    onclick="openForm({{ $wh->id }}, '{{ addslashes($wh->name) }}', '{{ addslashes($wh->address) }}', '{{ addslashes($wh->phone ?? '') }}')">
                                    Editar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Overlay formulario --}}
    <div id="wh-overlay" style="display:none" class="overlay-layer">
        <button class="overlay-backdrop" type="button" onclick="closeForm()" aria-label="Cerrar"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <h3 id="form-title">Nueva bodega</h3>
                <button class="btn secondary sm" type="button" onclick="closeForm()">✕</button>
            </div>
            <form method="post" action="{{ route('admin.inventory.warehouses.store') }}" class="form-stack">
                @csrf
                <input type="hidden" name="id" id="form-id">
                <label>Nombre <input type="text" name="name" id="form-name" placeholder="Ej: Bodega Central" required maxlength="100"></label>
                <label>Dirección <textarea name="address" id="form-address" placeholder="Dirección completa" required maxlength="500" rows="2"></textarea></label>
                <label>Teléfono <small style="font-weight:400;color:var(--muted)">(opcional)</small>
                    <input type="text" name="phone" id="form-phone" placeholder="Ej: +503 2222-3333" maxlength="30">
                </label>
                <div class="actions" style="margin-top:4px">
                    <button class="btn secondary" type="button" onclick="closeForm()">Cancelar</button>
                    <button class="btn" type="submit">Guardar bodega</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openForm(id, name, address, phone) {
            document.getElementById('form-title').textContent = id ? 'Editar bodega' : 'Nueva bodega';
            document.getElementById('form-id').value = id || '';
            document.getElementById('form-name').value = name || '';
            document.getElementById('form-address').value = address || '';
            document.getElementById('form-phone').value = phone || '';
            document.getElementById('wh-overlay').style.display = 'flex';
        }
        function closeForm() { document.getElementById('wh-overlay').style.display = 'none'; }
        @if($errors->any()) openForm(); @endif
    </script>
</x-layouts.app>
