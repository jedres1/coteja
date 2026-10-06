import { Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const DTE_LABEL = {
    '01': 'Factura', '03': 'CCF', '05': 'N. Crédito', '06': 'N. Débito',
    '07': 'C. Retención', '11': 'F. Exportación', '14': 'F. Excluido',
};

const STATUS_STYLE = {
    PENDIENTE:    { background: '#fff3cd', color: '#856404' },
    FIRMADO:      { background: '#cfe2ff', color: '#084298' },
    CONTINGENCIA: { background: '#fff3cd', color: '#856404' },
    ENVIADO:      { background: '#d1e7dd', color: '#0a3622' },
    ACEPTADO:     { background: '#d1e7dd', color: '#0a3622' },
    RECHAZADO:    { background: '#f8d7da', color: '#842029' },
    ANULADO:      { background: '#e2e3e5', color: '#41464b' },
};

function money(v) { return `$${Number(v || 0).toFixed(2)}`; }

function StatCard({ label, value }) {
    return (
        <div className="stat">
            <div className="stat-info">
                <span>{label}</span>
                <strong>{value}</strong>
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const style = STATUS_STYLE[status] ?? {};
    return (
        <span className="badge" style={{ ...style, padding: '2px 8px', borderRadius: 4, fontSize: '0.8em', fontWeight: 600 }}>
            {status}
        </span>
    );
}

export default function Dashboard({ stats, recent }) {
    return (
        <AppLayout title="Facturación — Dashboard">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <h2 style={{ margin: 0 }}>Facturación</h2>
                <Link href={route('admin.factura-sv.billing.nueva-factura')} className="btn">
                    Nueva Factura
                </Link>
            </div>

            {/* Stats */}
            <div className="stats-grid" style={{ marginBottom: 24 }}>
                <StatCard label="Facturas Hoy" value={stats.todayCount} />
                <StatCard label="Total Enviado Hoy" value={money(stats.todaySentTotal)} />
                <StatCard label="Enviadas a Hacienda" value={stats.sentCount} />
                <StatCard label="Pendientes de envío" value={stats.pendingCount} />
                <StatCard label="Anuladas" value={stats.voidedCount} />
            </div>

            {/* Quick links */}
            <div style={{ display: 'flex', gap: 12, marginBottom: 24, flexWrap: 'wrap' }}>
                <Link href={route('admin.factura-sv.billing.facturas')} className="btn secondary">Ver todas las facturas</Link>
                <Link href={route('admin.factura-sv.billing.cuentas-por-cobrar')} className="btn secondary">Cuentas por cobrar</Link>
                <Link href={route('admin.factura-sv.billing.productos')} className="btn secondary">Servicios</Link>
                <Link href={route('admin.factura-sv.billing.configuracion')} className="btn secondary">Configuración</Link>
            </div>

            {/* Recent invoices */}
            <div className="card">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h3 style={{ margin: 0 }}>Facturas recientes</h3>
                    <Link href={route('admin.factura-sv.billing.facturas')} style={{ fontSize: '0.85em' }}>Ver todas →</Link>
                </div>
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>No. Control</th>
                            <th>Tipo</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Aceptado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {recent.length === 0 && (
                            <tr><td colSpan={8} className="empty">No hay facturas registradas</td></tr>
                        )}
                        {recent.map((invoice) => (
                            <tr key={invoice.id}>
                                <td>{invoice.date}</td>
                                <td>
                                    {invoice.numberControl}
                                    {invoice.generationCode && (
                                        <><br /><span className="muted" style={{ fontSize: '0.8em' }}>{invoice.generationCode.slice(0, 8)}…</span></>
                                    )}
                                </td>
                                <td>{DTE_LABEL[invoice.documentType] ?? invoice.documentType}</td>
                                <td>{invoice.customerName}</td>
                                <td>{money(invoice.total)}</td>
                                <td><StatusBadge status={invoice.status} /></td>
                                <td>{invoice.accepted ? 'Sí' : '—'}</td>
                                <td>
                                    <Link
                                        href={route('admin.factura-sv.billing.facturas')}
                                        className="btn secondary"
                                        style={{ padding: '2px 10px', fontSize: '0.85em' }}
                                    >
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {stats.pendingCount > 0 && (
                    <div className="notice" style={{ marginTop: 12 }}>
                        Hay <strong>{stats.pendingCount}</strong> factura{stats.pendingCount !== 1 ? 's' : ''} pendiente{stats.pendingCount !== 1 ? 's' : ''} de firma o envío a Hacienda.{' '}
                        <Link href={route('admin.factura-sv.billing.facturas', { status: 'PENDIENTE' })}>
                            Ver pendientes →
                        </Link>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
