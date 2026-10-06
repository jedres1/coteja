import { useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = {
    issued: 'Emitida',
    void: 'Anulada',
    pending: 'Pendiente',
    paid: 'Pagada',
};

const STATUS_COLORS = {
    issued: { background: '#eff6ff', color: '#1d4ed8' },
    void: { background: '#fef2f2', color: '#dc2626' },
    pending: { background: '#fefce8', color: '#a16207' },
    paid: { background: '#f0fdf4', color: '#15803d' },
};

export default function ClientBilling({ customer, activeCompany, invoices, search, documentTypes }) {
    const searchRef = useRef(null);

    useEffect(() => {
        if (searchRef.current) searchRef.current.value = search || '';
    }, [search]);

    function handleSearch(e) {
        e.preventDefault();
        const q = searchRef.current?.value.trim();
        router.get(route('client.billing'), { search: q || undefined }, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <Head title="Mis facturas" />

            <div className="top">
                <div>
                    <h1>Mis facturas</h1>
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
                            placeholder="Número de documento, código de control..."
                        />
                    </label>
                    <button type="submit" className="btn">Buscar</button>
                    {search && (
                        <button
                            type="button"
                            className="btn secondary"
                            onClick={() => router.get(route('client.billing'))}
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
                            <th>Tipo / Número</th>
                            <th>Código control</th>
                            <th style={{ textAlign: 'right' }}>Subtotal</th>
                            <th style={{ textAlign: 'right' }}>IVA</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!invoices?.data || invoices.data.length === 0) && (
                            <tr>
                                <td colSpan={7} className="empty">Sin facturas registradas</td>
                            </tr>
                        )}
                        {invoices?.data?.map((inv) => {
                            const statusStyle = STATUS_COLORS[inv.status] || { background: '#f3f4f6', color: '#374151' };
                            const typeLabel = documentTypes && documentTypes[inv.document_type]
                                ? documentTypes[inv.document_type]
                                : inv.document_type;
                            return (
                                <tr key={inv.id}>
                                    <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>{inv.issued_at}</td>
                                    <td>
                                        <div style={{ fontSize: 13, color: '#6b7280' }}>{typeLabel}</div>
                                        <div style={{ fontFamily: 'monospace', fontWeight: 500 }}>
                                            {inv.document_number || '—'}
                                        </div>
                                    </td>
                                    <td style={{ fontFamily: 'monospace', fontSize: 13 }}>
                                        {inv.control_number || '—'}
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
                                            {STATUS_LABELS[inv.status] || inv.status}
                                        </span>
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
