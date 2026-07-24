<x-layouts.app title="Descargas | Coteja">
    <div class="top"><h1>Descargas</h1><span class="muted">Instaladores disponibles para clientes</span></div>
    <div class="card">
        <h3>Nueva version</h3>
        <form method="post" action="{{ route('admin.releases.store') }}" class="form-grid">
            @csrf
            <label>Plataforma<select name="platform"><option value="windows">Windows</option><option value="ios">iOS</option><option value="macos">macOS</option><option value="linux">Linux</option></select></label>
            <label>Version<input name="version" required></label>
            <label>Nombre archivo<input name="filename" required></label>
            <label>URL descarga<input type="url" name="download_url" required></label>
            <label>Activa<select name="is_active"><option value="1">Si</option><option value="0">No</option></select></label>
            <label style="grid-column:1/-1;">Notas<textarea name="notes"></textarea></label>
            <button class="btn">Publicar</button>
        </form>
    </div>
    <div class="card" style="margin-top:16px;">
        <table><tr><th>Plataforma</th><th>Version</th><th>Archivo</th><th>Estado</th><th>URL</th></tr>
            @foreach($releases as $release)
                <tr><td>{{ $release->platform }}</td><td>{{ $release->version }}</td><td>{{ $release->filename }}</td><td>{{ $release->is_active ? 'Activa' : 'Inactiva' }}</td><td><a href="{{ $release->download_url }}" target="_blank">Abrir</a></td></tr>
            @endforeach
        </table>
    </div>
</x-layouts.app>
