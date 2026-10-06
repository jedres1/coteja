import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const STATUS_BADGE = {
    active:    'badge active',
    expired:   'badge expired',
    suspended: 'badge suspended',
    cancelled: 'badge cancelled',
};
const STATUS_LABEL = { active: 'Activa', expired: 'Vencida', suspended: 'Suspendida', cancelled: 'Cancelada' };

function StatCard({ label, value, color = 'blue', sub }) {
    return (
        <div className={`stat-card c-${color}`}>
            <div className="s-label">{label}</div>
            <div className="s-value">{value}</div>
            {sub && <div className="s-sub">{sub}</div>}
        </div>
    );
}

export default function Dashboard({ customersCount, activeLicensesCount, expiredLicensesCount, monthlyRevenue, recentLicenses, recentBackups }) {
    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="section-top">
                <div>
                    <h1>Dashboard</h1>
                    <p className="muted">Control general de plataforma</p>
                </div>
            </div>

            <div className="stats-grid" style={{ marginBottom: 24 }}>
                <StatCard label="Clientes" value={customersCount} color="blue" />
                <StatCard label="Licencias activas" value={activeLicensesCount} color="green" />
                <StatCard label="Licencias vencidas" value={expiredLicensesCount} color="red" />
                <StatCard
                    label="Cobrado este mes"
                    value={`$${Number(monthlyRevenue).toFixed(2)}`}
                    color="green"
                    sub="mes actual"
                />
            </div>

            <div className="grid">
                <div className="card">
                    <div className="list-header">
                        <h3>Licencias recientes</h3>
                    </div>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Empresa</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Vence</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentLicenses.length === 0 && (
                                    <tr><td colSpan={5} className="empty">Sin licencias recientes</td></tr>
                                )}
                                {recentLicenses.map((l) => (
                                    <tr key={l.id}>
                                        <td>{l.customer?.name || '—'}</td>
                                        <td>{l.company?.business_name || '—'}</td>
                                        <td>{l.plan?.name || '—'}</td>
                                        <td>
                                            <span className={STATUS_BADGE[l.status] || 'badge'}>
                                                {STATUS_LABEL[l.status] || l.status}
                                            </span>
                                        </td>
                                        <td>{l.expires_at ? l.expires_at.substring(0, 10) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card">
                    <div className="list-header">
                        <h3>Backups recientes</h3>
                    </div>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Empresa</th>
                                    <th>Fecha</th>
                                    <th>Tamaño</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentBackups.length === 0 && (
                                    <tr><td colSpan={4} className="empty">Sin backups recientes</td></tr>
                                )}
                                {recentBackups.map((b) => (
                                    <tr key={b.id}>
                                        <td>{b.license?.customer?.name || '—'}</td>
                                        <td>{b.company?.business_name || '—'}</td>
                                        <td>{b.uploaded_at ? b.uploaded_at.substring(0, 16).replace('T', ' ') : '—'}</td>
                                        <td>{b.size_bytes ? (b.size_bytes / 1024).toFixed(1) + ' KB' : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
