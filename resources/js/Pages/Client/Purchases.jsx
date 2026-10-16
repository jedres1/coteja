import { useRef, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const PAYMENT_LABELS = { paid: 'Pagado', pending: 'Pendiente', void: 'Anulado' };
const PAYMENT_COLORS = {
    paid:    { background: '#f0fdf4', color: '#15803d' },
    pending: { background: '#fefce8', color: '#a16207' },
    void:    { background: '#fef2f2', color: '#dc2626' },
};

export default function ClientPurchases({ customer, activeCompany, invoices, search, statusFilter }) {
    const searchRef = useRef(null);

    useEffect(() => {
        if (searchRef.current) searchRef.current.value = search || '';
    }, [search]);

    function handleSearch(e) {
        e.preventDefault();
        const q = searchRef.current?.value.trim();
        router.get(route('client.purchases'), {
            search: q || undefined,
            status: statusFilter || undefined,
        }, { preserveScroll: true });
    }

    function setStatus(val) {
        router.get(route('client.purchases'), {
            search: search || undefined,
            status: val || undefined,
        }, { preserveScroll: true });
    }

    const hasFilter = search || statusFilter;

    return (
        <AppLayout>
            <Head title="Mis compras" />

            <div className="top">
                <div>
                    <h1>Mis compras</h1>
                    {activeCompany && (
                        <span style={{ fontSize: 13, color: 'var(--muted)' }}>{activeCompany.business_name}</span>
                    )}
                </div>
            </div>

            {/* Filtros */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <label style={{ margin: 0, flex: '1 1 220px' }}>
                        Buscar
                        <input ref={searchRef} type="text" defaultValue={search || ''}
                            placeholder="Proveedor, número de factura..." />
                    </label>
                    <label style={{ margin: 0, flex: '0 1 160px' }}>
                        Estado de pago
                        <select value={statusFilter || ''} onChange={(e) => setStatus(e.target.value)}>
                            <option value="">Todos</option>
                            {Object.entries(PAYMENT_LABELS).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                        <button type="submit" className="btn">Buscar</button>
                        {hasFilter && (
                            <button type="button" className="btn secondary"
                                onClick={() => router.get(route('client.purchases'))}>
                                Limpiar
                            </button>
                        )}
                    </div>
                </form>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Número</th>
                            <th style={{ textAlign: 'right' }}>Subtotal</th>
                            <th style={{ textAlign: 'right' }}>IVA</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Estado pago</th>
                            <th>Método</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!invoices?.data || invoices.data.length === 0) && (
                            <tr>
                                <td colSpan={8} className="empty">
                                    {hasFilter ? 'Sin resultados para la búsqueda' : 'Sin compras registradas'}
                                </td>
                            </tr>
                        )}
                        {invoices?.data?.map((inv) => (
                            <tr key={inv.id}>
                                <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>{inv.purchase_date}</td>
                                <td style={{ fontWeight: 500 }}>
                                    {inv.supplier?.name || '—'}
                                    {inv.supplier?.document_number && (
                                        <div style={{ fontSize: 12, color: 'var(--muted)' }}>{inv.supplier.document_number}</div>
                                    )}
                                </td>
                                <td style={{ fontFamily: 'monospace', fontSize: 13 }}>{inv.invoice_number || '—'}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                    {inv.subtotal != null ? fmt(inv.subtotal) : '—'}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                    {inv.iva != null ? fmt(inv.iva) : '—'}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 700 }}>
                                    {inv.total != null ? fmt(inv.total) : '—'}
                                </td>
                                <td>
                                    <span style={{
                                        fontSize: 12, fontWeight: 600,
                                        padding: '2px 8px', borderRadius: 12,
                                        ...(PAYMENT_COLORS[inv.payment_status] ?? { background: '#f3f4f6', color: '#374151' }),
                                    }}>
                                        {PAYMENT_LABELS[inv.payment_status] || inv.payment_status || '—'}
                                    </span>
                                </td>
                                <td style={{ fontSize: 13, color: 'var(--muted)' }}>{inv.payment_method || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {invoices?.links && <Pagination links={invoices.links} />}
        </AppLayout>
    );
}
