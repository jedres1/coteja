import { useState } from 'react';
import { Head, router, Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_BADGE  = { active: 'badge active', expired: 'badge expired', suspended: 'badge suspended', cancelled: 'badge suspended' };
const STATUS_LABEL  = { active: 'Activa', expired: 'Vencida', suspended: 'Suspendida', cancelled: 'Cancelada' };
const LICENSE_TYPE  = { propietario: 'Propietario', mensual: 'Mensual', anual: 'Anual' };
const INV_STATUS    = { issued: 'Emitida', void: 'Anulada', pending: 'Pendiente', paid: 'Pagada' };
const INV_COLORS    = {
    issued:  { background: '#eff6ff', color: '#1d4ed8' },
    void:    { background: '#fef2f2', color: '#dc2626' },
    pending: { background: '#fefce8', color: '#a16207' },
    paid:    { background: '#f0fdf4', color: '#15803d' },
};
const PLATFORM_ICON = { windows: '🖥', linux: '🐧', mac: '🍎', android: '📱', ios: '📱' };
const ENV_LABEL     = { production: 'Producción', testing: 'Pruebas' };

function StatCard({ label, value, sub, color }) {
    return (
        <div style={{
            background: 'var(--surface)',
            border: '1px solid var(--border)',
            borderRadius: 10,
            padding: '18px 22px',
            borderLeft: `4px solid ${color || 'var(--accent)'}`,
        }}>
            <div style={{ fontSize: 28, fontWeight: 700, color: color || 'var(--ink)' }}>{value}</div>
            <div style={{ fontSize: 13, color: 'var(--muted)', marginTop: 2 }}>{label}</div>
            {sub != null && <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 4 }}>{sub}</div>}
        </div>
    );
}

export default function ClientDashboard({ customer, companies, activeCompany, releases, licenses, stats, recentInvoices }) {
    const { auth } = usePage().props;
    const mod = auth?.user?.modules ?? {};
    const [selectedCompany, setSelectedCompany] = useState(activeCompany?.id ?? '');

    function handleCompanySwitch(e) {
        e.preventDefault();
        if (!selectedCompany) return;
        router.post(route('client.companies.switch'), { company_id: selectedCompany });
    }

    const needsCompanySelect = companies?.length > 1 && !activeCompany;

    return (
        <AppLayout>
            <Head title="Mi cuenta" />

            <div className="top">
                <div>
                    <h1>Mi cuenta</h1>
                    {customer?.name && <p className="muted" style={{ margin: 0 }}>Bienvenido, {customer.name}</p>}
                </div>
                <Link href={route('client.profile')} className="btn secondary">Mi perfil</Link>
            </div>

            {/* Selector de empresa */}
            {companies?.length > 1 && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <form onSubmit={handleCompanySwitch} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <label style={{ margin: 0, flex: '0 1 340px' }}>
                            Empresa activa
                            <select value={selectedCompany} onChange={(e) => setSelectedCompany(e.target.value)}>
                                <option value="">— Seleccionar empresa —</option>
                                {companies.map((c) => (
                                    <option key={c.id} value={c.id}>{c.business_name}</option>
                                ))}
                            </select>
                        </label>
                        <button type="submit" className="btn"
                            disabled={!selectedCompany || String(selectedCompany) === String(activeCompany?.id)}>
                            Cambiar empresa
                        </button>
                    </form>
                    {activeCompany && (
                        <p className="muted" style={{ margin: '10px 0 0', fontSize: 13 }}>
                            Empresa activa: <strong style={{ color: 'var(--ink)' }}>{activeCompany.business_name}</strong>
                        </p>
                    )}
                </div>
            )}

            {needsCompanySelect && (
                <div className="card" style={{ marginBottom: 16, borderLeft: '4px solid #f59e0b' }}>
                    <p style={{ margin: 0, color: '#92400e' }}>
                        Seleccione una empresa para ver sus datos de facturación y compras.
                    </p>
                </div>
            )}

            {/* Info empresa + estadísticas */}
            {activeCompany && (
                <>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 16, marginBottom: 16 }}>
                        <StatCard label="Facturas emitidas" value={stats?.billing ?? 0} color="#2563eb"
                            sub={stats?.pendingBilling > 0 ? `${stats.pendingBilling} pendiente(s)` : null} />
                        <StatCard label="Facturas de compra" value={stats?.purchases ?? 0} color="#7c3aed" />
                        <StatCard label="Licencias activas" color="#059669"
                            value={licenses?.filter(l => l.status === 'active').length ?? 0}
                            sub={`de ${licenses?.length ?? 0} total`} />
                    </div>

                    {/* Empresa info */}
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h3 style={{ margin: '0 0 12px' }}>Empresa</h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '8px 24px' }}>
                            {[
                                ['Razón social', activeCompany.business_name],
                                ['Nombre comercial', activeCompany.trade_name],
                                ['NIT / DUI', activeCompany.nit_dui],
                                ['NRC', activeCompany.nrc],
                                ['Correo', activeCompany.email],
                                ['Teléfono', activeCompany.phone],
                                ['Ambiente', ENV_LABEL[activeCompany.environment] ?? activeCompany.environment],
                            ].filter(([, v]) => v).map(([k, v]) => (
                                <div key={k}>
                                    <div style={{ fontSize: 11, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '.04em' }}>{k}</div>
                                    <div style={{ fontSize: 14, fontWeight: 500 }}>{v}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Facturas recientes */}
                    {recentInvoices?.length > 0 && mod.billing && (
                        <div className="card" style={{ marginBottom: 16 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                                <h3 style={{ margin: 0 }}>Facturas recientes</h3>
                                <Link href={route('client.billing')} style={{ fontSize: 13, color: 'var(--accent)' }}>Ver todas →</Link>
                            </div>
                            <div className="table-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Número</th>
                                            <th style={{ textAlign: 'right' }}>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentInvoices.map((inv) => (
                                            <tr key={inv.id}>
                                                <td style={{ fontSize: 13 }}>{inv.issued_at}</td>
                                                <td style={{ fontFamily: 'monospace', fontSize: 13 }}>{inv.document_number || inv.number_control || '—'}</td>
                                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>{fmt(inv.total)}</td>
                                                <td>
                                                    <span style={{ fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 12, ...(INV_COLORS[inv.status] ?? {}) }}>
                                                        {INV_STATUS[inv.status] ?? inv.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </>
            )}

            {/* Licencias */}
            {licenses?.length > 0 && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3 style={{ margin: '0 0 12px' }}>Mis licencias</h3>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Plan</th>
                                    <th>Tipo</th>
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
                                            <td style={{ fontSize: 13 }}>{lic.plan?.name || '—'}</td>
                                            <td style={{ fontSize: 12 }}>{LICENSE_TYPE[lic.license_type] ?? lic.license_type ?? '—'}</td>
                                            <td>
                                                <span className={STATUS_BADGE[lic.status] || 'badge'}>
                                                    {STATUS_LABEL[lic.status] || lic.status}
                                                </span>
                                            </td>
                                            <td className="muted" style={{ fontSize: 13 }}>
                                                {lic.license_type === 'propietario'
                                                    ? <span style={{ fontStyle: 'italic' }}>Sin vencimiento</span>
                                                    : (lic.expires_at || '—')}
                                            </td>
                                            <td className="muted" style={{ fontSize: 13 }}>
                                                {lastBackup ? lastBackup.uploaded_at?.substring(0, 10) : '—'}
                                            </td>
                                            <td>
                                                {lastBackup ? (
                                                    <a href={route('client.backups.download', lastBackup.id)}
                                                        className="btn secondary"
                                                        style={{ fontSize: 12, padding: '3px 10px', textDecoration: 'none' }}>
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
                </div>
            )}

            {/* Descargas */}
            {releases?.length > 0 && (
                <div className="card">
                    <h3 style={{ margin: '0 0 12px' }}>Descargas disponibles</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 12 }}>
                        {releases.map((rel) => (
                            <div key={rel.id} style={{ border: '1px solid var(--border)', borderRadius: 8, padding: '14px 16px', textAlign: 'center' }}>
                                <div style={{ fontSize: 28 }}>{PLATFORM_ICON[rel.platform?.toLowerCase()] || '📦'}</div>
                                <div style={{ fontWeight: 600, fontSize: 13, textTransform: 'capitalize', margin: '6px 0 2px' }}>{rel.platform}</div>
                                <div className="muted" style={{ fontSize: 12 }}>v{rel.version}</div>
                                {rel.download_url && (
                                    <a href={rel.download_url} className="btn" target="_blank" rel="noopener noreferrer"
                                        style={{ marginTop: 10, fontSize: 12, textDecoration: 'none', display: 'block' }}>
                                        Descargar
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
