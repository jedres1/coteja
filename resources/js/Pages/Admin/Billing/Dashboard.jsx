import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import TenantSelector from '@/Components/Billing/TenantSelector';

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

const QUICK_LINKS = [
    { label: 'Facturas', sub: 'Todas las facturas emitidas', icon: '🧾', name: 'admin.factura-sv.billing.facturas' },
    { label: 'Cuentas x cobrar', sub: 'Seguimiento de pagos', icon: '💰', name: 'admin.factura-sv.billing.cuentas-por-cobrar' },
    { label: 'Servicios', sub: 'Productos y precios', icon: '📦', name: 'admin.factura-sv.billing.productos' },
    { label: 'Configuración', sub: 'Emisor y firma digital', icon: '⚙️', name: 'admin.factura-sv.billing.configuracion' },
];

function money(v) { return `$${Number(v || 0).toFixed(2)}`; }

function StatCard({ label, value, color = 'blue', sub }) {
    return (
        <div className={`stat-card c-${color}`}>
            <div className="s-label">{label}</div>
            <div className="s-value">{value}</div>
            {sub && <div className="s-sub">{sub}</div>}
        </div>
    );
}

function StatusBadge({ status }) {
    return (
        <span className="badge" style={{ ...(STATUS_STYLE[status] ?? {}), padding: '2px 8px', borderRadius: 4, fontSize: '0.8em', fontWeight: 600 }}>
            {status}
        </span>
    );
}

export default function Dashboard({ stats, recent, availableCustomers = [] }) {
    const { auth, billingTenant } = usePage().props;
    return (
        <AppLayout title="Facturación — Dashboard">
            {auth?.user?.is_admin && (
                <TenantSelector
                    billingTenant={billingTenant}
                    availableCustomers={availableCustomers}
                    setRoute="billing.empresa.set"
                    clearRoute="billing.empresa.clear"
                />
            )}
            <div className="section-top">
                <div>
                    <h1>Facturación</h1>
                    <p className="muted">Panel de control DTE El Salvador</p>
                </div>
                <Link href={route('admin.factura-sv.billing.nueva-factura')} className="btn">
                    + Nueva Factura
                </Link>
            </div>

            <div className="stats-grid" style={{ marginBottom: 24 }}>
                <StatCard label="Facturas hoy" value={stats.todayCount} color="blue" />
                <StatCard label="Total enviado hoy" value={money(stats.todaySentTotal)} color="green" />
                <StatCard label="Enviadas a Hacienda" value={stats.sentCount} color="green" sub="acumulado" />
                <StatCard
                    label="Pendientes de envío"
                    value={stats.pendingCount}
                    color={stats.pendingCount > 0 ? 'amber' : 'gray'}
                />
                <StatCard label="Anuladas" value={stats.voidedCount} color="gray" />
            </div>

            <div className="action-grid" style={{ marginBottom: 24 }}>
                {QUICK_LINKS.map((item) => (
                    <Link key={item.name} href={route(item.name)} className="action-card">
                        <span className="ac-icon">{item.icon}</span>
                        <span className="ac-label">{item.label}</span>
                        <span className="ac-sub">{item.sub}</span>
                    </Link>
                ))}
            </div>

            <div className="card">
                <div className="list-header">
                    <h3>Facturas recientes</h3>
                    <Link href={route('admin.factura-sv.billing.facturas')} style={{ fontSize: '0.85em', color: 'var(--brand)' }}>
                        Ver todas →
                    </Link>
                </div>

                {stats.pendingCount > 0 && (
                    <div className="notice" style={{ marginBottom: 14 }}>
                        Hay <strong>{stats.pendingCount}</strong> factura{stats.pendingCount !== 1 ? 's' : ''} pendiente{stats.pendingCount !== 1 ? 's' : ''} de envío a Hacienda.{' '}
                        <Link href={route('admin.factura-sv.billing.facturas', { status: 'PENDIENTE' })} style={{ textDecoration: 'underline' }}>
                            Ver pendientes →
                        </Link>
                    </div>
                )}

                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>No. Control</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.length === 0 && (
                                <tr><td colSpan={7} className="empty">No hay facturas registradas</td></tr>
                            )}
                            {recent.map((invoice) => (
                                <tr key={invoice.id}>
                                    <td>{invoice.date}</td>
                                    <td style={{ fontFamily: 'monospace', fontSize: 12 }}>
                                        {invoice.numberControl || '—'}
                                        {invoice.generationCode && (
                                            <><br /><span className="muted">{invoice.generationCode.slice(0, 8)}…</span></>
                                        )}
                                    </td>
                                    <td>{DTE_LABEL[invoice.documentType] ?? invoice.documentType}</td>
                                    <td>{invoice.customerName}</td>
                                    <td style={{ fontWeight: 600 }}>{money(invoice.total)}</td>
                                    <td><StatusBadge status={invoice.status} /></td>
                                    <td>
                                        <Link
                                            href={route('admin.factura-sv.billing.facturas')}
                                            className="btn secondary"
                                            style={{ padding: '2px 10px', fontSize: '0.8em' }}
                                        >
                                            Ver
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
