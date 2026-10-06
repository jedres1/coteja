import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function Dashboard({ customersCount, activeLicensesCount, expiredLicensesCount, monthlyRevenue, recentLicenses, recentBackups }) {
    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="top">
                <h1>Dashboard</h1>
                <span className="muted">Control general de plataforma</span>
            </div>
            <div className="grid">
                <div className="card"><div className="muted">Clientes</div><div className="metric">{customersCount}</div></div>
                <div className="card"><div className="muted">Licencias activas</div><div className="metric">{activeLicensesCount}</div></div>
                <div className="card"><div className="muted">Licencias vencidas</div><div className="metric">{expiredLicensesCount}</div></div>
                <div className="card"><div className="muted">Cobrado este mes</div><div className="metric">${Number(monthlyRevenue).toFixed(2)}</div></div>
            </div>
            <div className="grid" style={{ marginTop: '16px' }}>
                <div className="card">
                    <h3>Licencias recientes</h3>
                    <table>
                        <thead><tr><th>Cliente</th><th>Empresa</th><th>Plan</th><th>Estado</th><th>Vence</th></tr></thead>
                        <tbody>
                            {recentLicenses.map((l) => (
                                <tr key={l.id}>
                                    <td>{l.customer?.name}</td>
                                    <td>{l.company?.business_name}</td>
                                    <td>{l.plan?.name}</td>
                                    <td><span className={`badge ${l.status}`}>{l.status}</span></td>
                                    <td>{l.expires_at ? l.expires_at.substring(0, 10) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="card">
                    <h3>Backups recientes</h3>
                    <table>
                        <thead><tr><th>Cliente</th><th>Empresa</th><th>Fecha</th><th>Tamaño</th></tr></thead>
                        <tbody>
                            {recentBackups.map((b) => (
                                <tr key={b.id}>
                                    <td>{b.license?.customer?.name}</td>
                                    <td>{b.company?.business_name}</td>
                                    <td>{b.uploaded_at ? b.uploaded_at.substring(0, 16).replace('T', ' ') : '—'}</td>
                                    <td>{b.size_bytes ? (b.size_bytes / 1024).toFixed(1) + ' KB' : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
