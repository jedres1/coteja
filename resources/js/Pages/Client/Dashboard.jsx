import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const STATUS_BADGE = {
    active:    'badge active',
    expired:   'badge expired',
    suspended: 'badge suspended',
    cancelled: 'badge cancelled',
};
const STATUS_LABEL = { active: 'Activa', expired: 'Vencida', suspended: 'Suspendida', cancelled: 'Cancelada' };

const PLATFORM_ICON = { windows: '🖥', linux: '🐧', mac: '🍎', android: '📱', ios: '📱' };

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

            <div className="section-top">
                <div>
                    <h1>Mi cuenta</h1>
                    {customer?.name && (
                        <p className="muted">Bienvenido, {customer.name}</p>
                    )}
                </div>
            </div>

            {companies && companies.length > 1 && (
                <div className="card" style={{ marginBottom: 20 }}>
                    <h3>Empresa activa</h3>
                    <form onSubmit={handleCompanySwitch} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <label style={{ margin: 0, flex: '0 1 340px' }}>
                            Seleccionar empresa
                            <select value={selectedCompany} onChange={(e) => setSelectedCompany(e.target.value)}>
                                <option value="">— Elige una empresa —</option>
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>{c.business_name}</option>
                                ))}
                            </select>
                        </label>
                        <button
                            type="submit"
                            className="btn"
                            disabled={!selectedCompany || String(selectedCompany) === String(activeCompany?.id)}
                        >
                            Cambiar empresa
                        </button>
                    </form>
                    {activeCompany && (
                        <p className="muted" style={{ margin: '10px 0 0', fontSize: 13 }}>
                            Empresa actual: <strong style={{ color: 'var(--ink)' }}>{activeCompany.business_name}</strong>
                        </p>
                    )}
                </div>
            )}

            {releases && releases.length > 0 && (
                <div className="card" style={{ marginBottom: 20 }}>
                    <div className="list-header">
                        <h3>Descargas disponibles</h3>
                    </div>
                    <div className="action-grid">
                        {releases.map((rel) => (
                            <div key={rel.id} className="action-card" style={{ cursor: 'default' }}>
                                <span className="ac-icon">
                                    {PLATFORM_ICON[rel.platform?.toLowerCase()] || '📦'}
                                </span>
                                <span className="ac-label" style={{ textTransform: 'capitalize' }}>
                                    {rel.platform}
                                </span>
                                <span className="ac-sub">v{rel.version}</span>
                                {rel.filename && (
                                    <span className="ac-sub" style={{ wordBreak: 'break-all' }}>{rel.filename}</span>
                                )}
                                {rel.download_url && (
                                    <a
                                        href={rel.download_url}
                                        className="btn"
                                        style={{ marginTop: 6, fontSize: 13, textDecoration: 'none', textAlign: 'center' }}
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

            <div className="card">
                <div className="list-header">
                    <h3>Mis licencias</h3>
                </div>

                {(!licenses || licenses.length === 0) && (
                    <p className="muted">No tiene licencias asignadas.</p>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {licenses.map((lic) => {
                                    const lastBackup = lic.backups?.at(-1) ?? null;
                                    return (
                                        <tr key={lic.id}>
                                            <td style={{ fontWeight: 600 }}>{lic.company?.business_name || '—'}</td>
                                            <td>{lic.plan?.name || '—'}</td>
                                            <td>
                                                <span className={STATUS_BADGE[lic.status] || 'badge'}>
                                                    {STATUS_LABEL[lic.status] || lic.status}
                                                </span>
                                            </td>
                                            <td className="muted">{lic.expires_at || '—'}</td>
                                            <td className="muted">
                                                {lastBackup ? lastBackup.uploaded_at : '—'}
                                            </td>
                                            <td>
                                                {lastBackup ? (
                                                    <a
                                                        href={route('client.backups.download', lastBackup.id)}
                                                        className="btn secondary"
                                                        style={{ fontSize: 12, padding: '3px 10px', textDecoration: 'none' }}
                                                    >
                                                        Backup
                                                    </a>
                                                ) : (
                                                    <span className="muted" style={{ fontSize: 12 }}>Sin backup</span>
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
