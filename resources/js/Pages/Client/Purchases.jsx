import { useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const DEFAULT_PAYMENT_STATUSES = {
    paid: 'Pagado',
    pending: 'Pendiente',
    void: 'Anulado',
};

const PAYMENT_STATUS_COLORS = {
    paid: { background: '#f0fdf4', color: '#15803d' },
    pending: { background: '#fefce8', color: '#a16207' },
    void: { background: '#fef2f2', color: '#dc2626' },
};

export default function ClientPurchases({
    customer,
    activeCompany,
    invoices,
    search,
    documentTypes,
    paymentStatuses,
}) {
    const searchRef = useRef(null);
    const statuses = paymentStatuses || DEFAULT_PAYMENT_STATUSES;

    useEffect(() => {
        if (searchRef.current) searchRef.current.value = search || '';
    }, [search]);

    function handleSearch(e) {
        e.preventDefault();
        const q = searchRef.current?.value.trim();
        router.get(route('client.purchases'), { search: q || undefined }, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title="Mis compras" />

            <div className="top">
                <div>
                    <h1>Mis compras</h1>
                    {activeCompany && (
                        <span style={{ fontSize: 13, color: '#6b7280' }}>{activeCompany.business_name}</span>
                    )}
                </div>
            </div>

            {/* Search */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, alignItems: 'flex-end' }}>
                    <label style={{ margin: 0, flex: 1 }}>
                        Buscar
                        <input
                            ref={searchRef}
                            type="text"
                            defaultValue={search || ''}
                            placeholder="Proveedor, número de factura..."
                        />
                    </label>
                    <button type="submit" className="btn">Buscar</button>
                    {search && (
                        <button
                            type="button"
                            className="btn secondary"
                            onClick={() => router.get(route('client.purchases'))}
                        >
                            Limpiar
                        </button>
                    )}
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Tipo / Número</th>
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
                                <td colSpan={8} className="empty">Sin compras registradas</td>
                            </tr>
                        )}
                        {invoices?.data?.map((inv) => {
                            const statusStyle = PAYMENT_STATUS_COLORS[inv.payment_status] || { background: '#f3f4f6', color: '#374151' };
                            const typeLabel = documentTypes && documentTypes[inv.document_type]
                                ? documentTypes[inv.document_type]
                                : inv.document_type;
                            return (
                                <tr key={inv.id}>
                                    <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>{inv.date}</td>
                                    <td style={{ fontWeight: 500 }}>{inv.supplier?.name || '—'}</td>
                                    <td>
                                        {typeLabel && (
                                            <div style={{ fontSize: 13, color: '#6b7280' }}>{typeLabel}</div>
                                        )}
                                        <div style={{ fontFamily: 'monospace', fontWeight: 500 }}>
                                            {inv.invoice_number || '—'}
                                        </div>
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {inv.subtotal != null ? fmt(inv.subtotal) : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {inv.iva != null ? fmt(inv.iva) : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 700 }}>
                                        {inv.total != null ? fmt(inv.total) : '—'}
                                    </td>
                                    <td>
                                        <span style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            padding: '2px 8px',
                                            borderRadius: 12,
                                            ...statusStyle,
                                        }}>
                                            {statuses[inv.payment_status] || inv.payment_status || '—'}
                                        </span>
                                    </td>
                                    <td style={{ fontSize: 13, color: '#6b7280' }}>
                                        {inv.method || '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {invoices?.links && <Pagination links={invoices.links} />}
        </AppLayout>
    );
}
