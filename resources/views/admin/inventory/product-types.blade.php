<x-layouts.app title="Tipos de producto | Inventario">
    <style>
        .inv-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .badge-inv { display:inline-flex; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
        .yes { background:#dcfce7; color:#166534; } .no { background:#f1f5f9; color:#475569; }
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; min-width:320px; max-width:460px; width:100%; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .overlay-header h3 { margin:0; font-size:16px; }
        .form-stack { display:grid; gap:12px; }
        .form-stack label { display:grid; gap:5px; font-size:13px; font-weight:600; }
        .switch-row { display:flex; align-items:center; gap:10px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .btn.secondary { background:#4b5563; } .btn.sm { min-height:30px; padding:5px 10px; font-size:12px; }
        .btn.red { background:var(--bad); }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .empty-state { padding:48px; text-align:center; color:var(--muted); font-size:15px; }
    </style>

    <div class="inv-header">
        <div>
            <h1 style="margin:0;font-size:26px">Tipos de producto</h1>
            <p class="muted" style="margin:4px 0 0">Clasifica los productos e indica si controlan existencias de inventario.</p>
        </div>
        <button class="btn" type="button" onclick="openForm()">Nuevo tipo</button>
    </div>

    @if(session('status'))
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--ok);font-size:13px">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--bad);font-size:13px">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($types->isEmpty())
        <div class="empty-state">No hay tipos de producto registrados.</div>
    @else
        <div class="card" style="padding:0;overflow:hidden">
            <table>
                <thead><tr><th>Nombre</th><th>Controla existencias</th><th>Productos</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach($types as $type)
                        <tr>
                            <td><strong>{{ $type->name }}</strong></td>
                            <td><span class="badge-inv {{ $type->controls_inventory ? 'yes' : 'no' }}">{{ $type->controls_inventory ? 'Sí' : 'No' }}</span></td>
                            <td>{{ $type->products_count }}</td>
                            <td>
                                <div class="actions">
                                    <button class="btn secondary sm" type="button" onclick="openForm({{ $type->id }}, '{{ addslashes($type->name) }}', {{ $type->controls_inventory ? 'true' : 'false' }})">Editar</button>
                                    @if($type->products_count === 0)
                                        <form method="post" action="{{ route('admin.inventory.product-types.destroy', $type) }}" class="inline" data-confirm="Eliminar tipo «{{ $type->name }}»">
                                            @csrf @method('DELETE')
                                            <button class="btn red sm" type="submit">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Overlay formulario --}}
    <div id="type-overlay" style="display:none" class="overlay-layer">
        <button class="overlay-backdrop" type="button" onclick="closeForm()" aria-label="Cerrar"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <h3 id="form-title">Nuevo tipo de producto</h3>
                <button class="btn secondary sm" type="button" onclick="closeForm()">✕</button>
            </div>
            <form method="post" action="{{ route('admin.inventory.product-types.store') }}" class="form-stack">
                @csrf
                <input type="hidden" name="id" id="form-id">
                <label>Nombre
                    <input type="text" name="name" id="form-name" placeholder="Ej: Materia Prima" required maxlength="100">
                </label>
                <label class="switch-row">
                    <input type="checkbox" name="controls_inventory" id="form-controls" value="1">
                    <span>Controla existencias en inventario</span>
                </label>
                <div class="actions" style="margin-top:4px">
                    <button class="btn secondary" type="button" onclick="closeForm()">Cancelar</button>
                    <button class="btn" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openForm(id, name, controls) {
            document.getElementById('form-title').textContent = id ? 'Editar tipo de producto' : 'Nuevo tipo de producto';
            document.getElementById('form-id').value = id || '';
            document.getElementById('form-name').value = name || '';
            document.getElementById('form-controls').checked = controls || false;
            document.getElementById('type-overlay').style.display = 'flex';
        }
        function closeForm() { document.getElementById('type-overlay').style.display = 'none'; }
        document.querySelectorAll('[data-confirm]').forEach(f => {
            f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm + '. ¿Continuar?')) e.preventDefault(); });
        });
        @if($errors->any()) openForm(); @endif
    </script>
</x-layouts.app>
