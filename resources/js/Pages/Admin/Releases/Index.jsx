import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { route } from 'ziggy-js';

const platforms = {
    windows: 'Windows',
    ios:     'iOS',
    macos:   'macOS',
    linux:   'Linux',
};

function ReleaseForm({ onSuccess }) {
    const form = useForm({
        platform: 'windows',
        version: '',
        filename: '',
        download_url: '',
        is_active: 1,
        notes: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.releases.store'), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="field">
                <label>Plataforma</label>
                <select
                    value={form.data.platform}
                    onChange={(e) => form.setData('platform', e.target.value)}
                >
                    {Object.entries(platforms).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.platform && <p className="field-error">{form.errors.platform}</p>}
            </div>
            <div className="field">
                <label>Versión</label>
                <input
                    type="text"
                    value={form.data.version}
                    onChange={(e) => form.setData('version', e.target.value)}
                    placeholder="1.0.0"
                />
                {form.errors.version && <p className="field-error">{form.errors.version}</p>}
            </div>
            <div className="field">
                <label>Nombre de archivo</label>
                <input
                    type="text"
                    value={form.data.filename}
                    onChange={(e) => form.setData('filename', e.target.value)}
                    placeholder="setup-1.0.0.exe"
                />
                {form.errors.filename && <p className="field-error">{form.errors.filename}</p>}
            </div>
            <div className="field">
                <label>URL de descarga</label>
                <input
                    type="text"
                    value={form.data.download_url}
                    onChange={(e) => form.setData('download_url', e.target.value)}
                    placeholder="https://..."
                />
                {form.errors.download_url && <p className="field-error">{form.errors.download_url}</p>}
            </div>
            <div className="field">
                <label>Activo</label>
                <select
                    value={form.data.is_active}
                    onChange={(e) => form.setData('is_active', Number(e.target.value))}
                >
                    <option value={1}>Si</option>
                    <option value={0}>No</option>
                </select>
                {form.errors.is_active && <p className="field-error">{form.errors.is_active}</p>}
            </div>
            <div className="field">
                <label>Notas</label>
                <textarea
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    rows={4}
                />
                {form.errors.notes && <p className="field-error">{form.errors.notes}</p>}
            </div>
            <div className="modal-footer">
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

export default function ReleasesIndex({ releases }) {
    const [showCreate, setShowCreate] = useState(false);

    function handleDelete(id) {
        if (!confirm('¿Eliminar esta descarga?')) return;
        router.delete(route('admin.releases.destroy', id));
    }

    return (
        <AppLayout>
            <Head title="Descargas" />
            <div className="top">
                <h1>Descargas</h1>
                <button className="btn" onClick={() => setShowCreate(true)}>
                    Nueva versión
                </button>
            </div>

            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Plataforma</th>
                            <th>Versión</th>
                            <th>Archivo</th>
                            <th>URL</th>
                            <th>Activo</th>
                            <th>Notas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {releases.length === 0 && (
                            <tr>
                                <td colSpan={7} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {releases.map((rel) => (
                            <tr key={rel.id}>
                                <td>{platforms[rel.platform] ?? rel.platform}</td>
                                <td>{rel.version}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: '0.85em' }}>{rel.filename}</td>
                                <td>
                                    {rel.download_url ? (
                                        <a href={rel.download_url} target="_blank" rel="noreferrer">
                                            {rel.download_url.length > 40
                                                ? rel.download_url.substring(0, 40) + '…'
                                                : rel.download_url}
                                        </a>
                                    ) : '—'}
                                </td>
                                <td>
                                    <span className={`badge ${rel.is_active ? 'active' : 'inactive'}`}>
                                        {rel.is_active ? 'Si' : 'No'}
                                    </span>
                                </td>
                                <td className="muted" style={{ fontSize: '0.85em' }}>
                                    {rel.notes ? rel.notes.substring(0, 60) + (rel.notes.length > 60 ? '…' : '') : '—'}
                                </td>
                                <td>
                                    <button
                                        className="btn btn-sm btn-danger"
                                        onClick={() => handleDelete(rel.id)}
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showCreate && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Nueva versión</h2>
                            <button className="modal-close" onClick={() => setShowCreate(false)}>✕</button>
                        </div>
                        <ReleaseForm onSuccess={() => setShowCreate(false)} />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
