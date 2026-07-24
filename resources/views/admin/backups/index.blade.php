<x-layouts.app title="Backups | Coteja">
    <div class="top"><h1>Backups</h1><span class="muted">Ultimo respaldo cifrado por licencia</span></div>
    <div class="card">
        <table><tr><th>Cliente</th><th>Empresa</th><th>Archivo</th><th>Fecha</th><th>Tamano</th><th>Checksum</th><th>Accion</th></tr>
            @foreach($backups as $backup)
                <tr>
                    <td>{{ $backup->license->customer->name }}</td><td>{{ $backup->company->business_name }}</td><td>{{ $backup->filename }}</td>
                    <td>{{ optional($backup->uploaded_at)->format('Y-m-d H:i') }}</td><td>{{ number_format($backup->size_bytes / 1024, 1) }} KB</td><td><code>{{ \Illuminate\Support\Str::limit($backup->checksum, 18) }}</code></td>
                    <td><a class="btn secondary" href="{{ route('admin.backups.download', $backup) }}">Descargar</a></td>
                </tr>
            @endforeach
        </table>
        {{ $backups->links() }}
    </div>
</x-layouts.app>
