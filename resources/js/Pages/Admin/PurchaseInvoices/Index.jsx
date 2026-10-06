import { useState, useEffect } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

// ─── Modal ────────────────────────────────────────────────────────────────────

function Modal({ title, onClose, children }) {
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <div className="overlay-layer" role="dialog" aria-modal="true" aria-label={title}>
            <button className="overlay-backdrop" type="button" aria-label="Cerrar" onClick={onClose} />
            <div className="overlay-panel card" style={{ maxWidth: 700 }}>
                <div className="overlay-header">
                    <div><h3>{title}</h3></div>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>✕</button>
                </div>
                {children}
            </div>
        </div>
    );
}

// ─── DocumentDataView ─────────────────────────────────────────────────────────

function DocumentDataView({ data }) {
    if (!data) return <p className="muted">Sin datos de documento.</p>;

    const parsed = typeof data === 'string' ? (() => { try { return JSON.parse(data); } catch { return null; } })() : data;

    if (!parsed) {
        return <pre style={{ whiteSpace: 'pre-wrap', fontSize: 13, wordBreak: 'break-all' }}>{String(data)}</pre>;
    }

    return (
        <div style={{ maxHeight: 420, overflowY: 'auto' }}>
            <table style={{ width: '100%', fontSize: 13, borderCollapse: 'collapse' }}>
                <tbody>
                    {Object.entries(parsed).map(([key, val]) => (
                        <tr key={key} style={{ borderBottom: '1px solid var(--border, #e5e7eb)' }}>
                            <td style={{ padding: '6px 10px 6px 0', fontWeight: 600, whiteSpace: 'nowrap', verticalAlign: 'top', color: '#6b7280', width: '35%' }}>
                                {key}
                            </td>
                            <td style={{ padding: '6px 0', verticalAlign: 'top', wordBreak: 'break-all' }}>
                                {typeof val === 'object' && val !== null
                                    ? <pre style={{ margin: 0, fontSize: 12 }}>{JSON.stringify(val, null, 2)}</pre>
                                    : String(val ?? '—')
                                }
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PurchaseInvoicesIndex({ invoices, search, supplierId, suppliers, documentTypes }) {
    const [searchVal, setSearchVal]     = useState(search ?? '');
    const [supplierVal, setSupplierVal] = useState(supplierId ? String(supplierId) : '');
    const [viewItem, setViewItem]       = useState(null);

    function handleSearch(e) {
        e.preventDefault();
        router.get(
            route('admin.purchase-invoices.index'),
            { search: searchVal, supplierId: supplierVal },
            { preserveState: true }
        );
    }

    function handleDelete(invoice) {
        if (!confirm(`¿Eliminar la factura #${invoice.invoice_number}? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.purchase-invoices.destroy', invoice.id));
    }

    const hasFilter = !!(search || supplierId);

    const paymentBadge = {
        pending:     'suspended',
        partial:     '',
        paid:        'active',
    };
    const paymentLabel = {
        pending:     'Pendiente',
        partial:     'Parcial',
        paid:        'Pagado',
    };
    const statusBadge = {
        approved: 'active',
        pending:  'suspended',
        rejected: 'danger',
    };
    const statusLabel = {
        approved: 'Aprobada',
        pending:  'Pendiente',
        rejected: 'Rechazada',
    };

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    return (
        <AppLayout>
            <Head title="Facturas de compra" />

            <div className="top">
                <h1>Facturas de compra</h1>
            </div>

            {/* Search bar */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            placeholder="Número de factura…"
                        />
                    </label>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Proveedor
                        <select value={supplierVal} onChange={(e) => setSupplierVal(e.target.value)}>
                            <option value="">Todos los proveedores</option>
                            {suppliers.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                        <button type="submit" className="btn">Buscar</button>
                        {hasFilter && (
                            <Link href={route('admin.purchase-invoices.index')} className="btn secondary">
                                Limpiar
                            </Link>
                        )}
                    </div>
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha / Vence</th>
                            <th>Proveedor</th>
                            <th>Tipo / Número</th>
                            <th>Subtotal</th>
                            <th>IVA</th>
                            <th>Total</th>
                            <th>Pago</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {invoices.data.map((invoice) => (
                            <tr key={invoice.id}>
                                <td>
                                    <span>{invoice.date}</span>
                                    {invoice.due_date && (
                                        <><br /><span className="muted" style={{ fontSize: 12 }}>Vence: {invoice.due_date}</span></>
                                    )}
                                </td>
                                <td>
                                    <span style={{ fontWeight: 600 }}>{invoice.supplier?.name}</span>
                                    {invoice.supplier?.document_number && (
                                        <><br /><span className="muted" style={{ fontSize: 12 }}>{invoice.supplier.document_number}</span></>
                                    )}
                                </td>
                                <td>
                                    <span>{documentTypes[invoice.document_type] ?? invoice.document_type}</span>
                                    <br />
                                    <span className="muted" style={{ fontSize: 12 }}>{invoice.invoice_number}</span>
                                </td>
                                <td>{fmt(invoice.subtotal)}</td>
                                <td>{fmt(invoice.iva)}</td>
                                <td style={{ fontWeight: 600 }}>{fmt(invoice.total)}</td>
                                <td>
                                    <span className={`badge ${paymentBadge[invoice.payment_status] ?? ''}`}>
                                        {paymentLabel[invoice.payment_status] ?? invoice.payment_status}
                                    </span>
                                </td>
                                <td>
                                    <span className={`badge ${statusBadge[invoice.status] ?? ''}`}>
                                        {statusLabel[invoice.status] ?? invoice.status}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setViewItem(invoice)}
                                        >
                                            Ver
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm danger"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => handleDelete(invoice)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={invoices.links} />
            </div>

            {/* View modal */}
            {viewItem && (
                <Modal
                    title={`Factura ${viewItem.invoice_number}`}
                    onClose={() => setViewItem(null)}
                >
                    <DocumentDataView data={viewItem.document_data} />
                </Modal>
            )}
        </AppLayout>
    );
}
