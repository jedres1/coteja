import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { route } from 'ziggy-js';

export default function BackupsIndex({ backups }) {
    function formatBytes(bytes) {
        if (!bytes) return '—';
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return (bytes / 1024).toFixed(1) + ' KB';
    }

    return (
        <AppLayout>
            <Head title="Backups" />
            <div className="top">
                <h1>Backups</h1>
                <span className="muted">Archivos de respaldo subidos por los clientes</span>
            </div>
            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Empresa</th>
                            <th>Archivo</th>
                            <th>Subido</th>
                            <th>Tamaño</th>
                            <th>Checksum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {backups.data.length === 0 && (
                            <tr>
                                <td colSpan={7} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {backups.data.map((backup) => (
                            <tr key={backup.id}>
                                <td>{backup.license?.customer?.name ?? '—'}</td>
                                <td>{backup.company?.business_name ?? '—'}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: '0.85em' }}>{backup.filename}</td>
                                <td>
                                    {backup.uploaded_at
                                        ? backup.uploaded_at.substring(0, 16).replace('T', ' ')
                                        : '—'}
                                </td>
                                <td>{formatBytes(backup.size_bytes)}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: '0.75em' }}>
                                    {backup.checksum ? backup.checksum.substring(0, 12) + '…' : '—'}
                                </td>
                                <td>
                                    <a
                                        href={route('admin.backups.download', backup.id)}
                                        className="btn btn-sm"
                                    >
                                        Descargar
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={backups.links} />
            </div>
        </AppLayout>
    );
}
