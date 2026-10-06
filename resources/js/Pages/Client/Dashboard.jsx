import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const STATUS_LABELS = {
    active: 'Activa',
    expired: 'Vencida',
    suspended: 'Suspendida',
    cancelled: 'Cancelada',
};

const STATUS_COLORS = {
    active: { background: '#f0fdf4', color: '#15803d' },
    expired: { background: '#fef2f2', color: '#dc2626' },
    suspended: { background: '#fefce8', color: '#a16207' },
    cancelled: { background: '#f3f4f6', color: '#6b7280' },
};

const PLATFORM_ICONS = {
    windows: '🖥',
    linux: '🐧',
    mac: '🍎',
    android: '📱',
    ios: '📱',
};

export default function ClientDashboard({ customer, companies, activeCompany, releases, licenses }) {
    const [selectedCompany, setSelectedCompany] = useState(activeCompany?.id ?? '');

    function handleCompanySwitch(e) {
        e.preventDefault();
        if (!selectedCompany) return;
        router.post(route('client.companies.switch'), { company_id: selectedCompany });
    }

    return (
        <AppLayout>
            <Head title="Mi cuenta" />

            <div className="top">
                <h1>Mi cuenta</h1>
                {customer?.name && (
                    <span style={{ color: '#6b7280', fontWeight: 400, fontSize: 15 }}>
                        Bienvenido, {customer.name}
                    </span>
                )}
            </div>

            {/* Company switcher */}
            {companies && companies.length > 1 && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 12, fontSize: 15, fontWeight: 600 }}>Empresa activa</h2>
                    <form onSubmit={handleCompanySwitch} style={{ display: 'flex', gap: 10, alignItems: 'flex-end' }}>
                        <label style={{ margin: 0, flex: '0 1 360px' }}>
                            Empresa
                            <select
                                value={selectedCompany}
                                onChange={(e) => setSelectedCompany(e.target.value)}
                            >
                                <option value="">— Seleccionar empresa —</option>
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>{c.business_name}</option>
                                ))}
                            </select>
                        </label>
                        <button type="submit" className="btn" disabled={!selectedCompany || String(selectedCompany) === String(activeCompany?.id)}>
                            Cambiar empresa
                        </button>
                    </form>
                    {activeCompany && (
                        <p style={{ margin: '10px 0 0', fontSize: 13, color: '#6b7280' }}>
                            Empresa actual: <strong>{activeCompany.business_name}</strong>
                        </p>
                    )}
                </div>
            )}

            {/* Releases */}
            {releases && releases.length > 0 && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>
                        Descargas disponibles
                    </h2>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 12 }}>
                        {releases.map((rel) => (
                            <div
                                key={rel.id}
                                style={{
                                    border: '1px solid #e5e7eb',
                                    borderRadius: 8,
                                    padding: 16,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <span style={{ fontSize: 22 }}>
                                        {PLATFORM_ICONS[rel.platform?.toLowerCase()] || '📦'}
                                    </span>
                                    <div>
                                        <div style={{ fontWeight: 600, fontSize: 14, textTransform: 'capitalize' }}>
                                            {rel.platform}
                                        </div>
                                        <div style={{ fontSize: 12, color: '#6b7280' }}>v{rel.version}</div>
                                    </div>
                                </div>
                                {rel.filename && (
                                    <div style={{ fontSize: 12, color: '#6b7280', wordBreak: 'break-all' }}>
                                        {rel.filename}
                                    </div>
                                )}
                                {rel.download_url && (
                                    <a
                                        href={rel.download_url}
                                        className="btn"
                                        style={{ fontSize: 13, textAlign: 'center', textDecoration: 'none', display: 'block', padding: '6px 12px' }}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Descargar
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Licenses */}
            <div className="card">
                <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>Mis licencias</h2>

                {(!licenses || licenses.length === 0) && (
                    <p style={{ color: '#6b7280', margin: 0 }}>No tiene licencias asignadas.</p>
                )}

                {licenses && licenses.length > 0 && (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Vence</th>
                                    <th>Último backup</th>
                                    <th>Backup</th>
                                </tr>
                            </thead>
                            <tbody>
                                {licenses.map((lic) => {
                                    const statusStyle = STATUS_COLORS[lic.status] || STATUS_COLORS.cancelled;
                                    const lastBackup = lic.backups && lic.backups.length > 0
                                        ? lic.backups[lic.backups.length - 1]
                                        : null;
                                    return (
                                        <tr key={lic.id}>
                                            <td style={{ fontWeight: 500 }}>
                                                {lic.company?.business_name || '—'}
                                            </td>
                                            <td>{lic.plan?.name || '—'}</td>
                                            <td>
                                                <span style={{
                                                    fontSize: 12,
                                                    fontWeight: 600,
                                                    padding: '2px 8px',
                                                    borderRadius: 12,
                                                    ...statusStyle,
                                                }}>
                                                    {STATUS_LABELS[lic.status] || lic.status}
                                                </span>
                                            </td>
                                            <td style={{ fontSize: 13 }}>
                                                {lic.expires_at || '—'}
                                            </td>
                                            <td style={{ fontSize: 13, color: '#6b7280' }}>
                                                {lastBackup ? lastBackup.uploaded_at : '—'}
                                            </td>
                                            <td>
                                                {lastBackup ? (
                                                    <a
                                                        href={route('client.backups.download', lastBackup.id)}
                                                        className="btn secondary"
                                                        style={{ fontSize: 12, padding: '3px 10px', textDecoration: 'none', display: 'inline-block' }}
                                                    >
                                                        Descargar backup
                                                    </a>
                                                ) : (
                                                    <span style={{ fontSize: 12, color: '#9ca3af' }}>Sin backup</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
