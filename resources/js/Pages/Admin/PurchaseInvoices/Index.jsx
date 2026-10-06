import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

const DOC_LABELS = {
    ccf: 'CCF',
    fce: 'FCE',
    fcc: 'FCC',
    nota_credito: 'Nota crédito',
    nota_debito: 'Nota débito',
};

const PAYMENT_LABEL = { pending: 'Pendiente', partial: 'Parcial', paid: 'Pagado' };
const PAYMENT_COLOR = {
    pending: { background: '#fef3c7', color: '#92400e' },
    partial: { background: '#eff6ff', color: '#1d4ed8' },
    paid:    { background: '#f0fdf4', color: '#15803d' },
};
const STATUS_LABEL = { approved: 'Aprobada', pending: 'Pendiente', rejected: 'Rechazada' };
const STATUS_COLOR = {
    approved: { background: '#f0fdf4', color: '#15803d' },
    pending:  { background: '#fef3c7', color: '#92400e' },
    rejected: { background: '#fef2f2', color: '#dc2626' },
};

const badge = (map, key) => {
    const style = map[key] ?? { background: '#f3f4f6', color: '#374151' };
    return (
        <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, whiteSpace: 'nowrap', ...style }}>
            {(map === PAYMENT_LABEL ? PAYMENT_LABEL[key] : STATUS_LABEL[key]) ?? key}
        </span>
    );
};

function InvoiceDetail({ invoice }) {
    const fmt = (n) => n != null ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2 }) : '—';

    return (
        <>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 16 }}>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Proveedor</span><br /><strong>{invoice.supplier?.name ?? '—'}</strong></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>NIT / NRC</span><br /><span style={{ fontSize: 13 }}>{invoice.supplier?.document_number ?? '—'}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Fecha de compra</span><br /><span style={{ fontSize: 13 }}>{invoice.purchase_date ?? '—'}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Vencimiento</span><br /><span style={{ fontSize: 13 }}>{invoice.due_date ?? '—'}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Tipo</span><br /><span style={{ fontSize: 13 }}>{DOC_LABELS[invoice.document_type] ?? invoice.document_type ?? '—'}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Método de pago</span><br /><span style={{ fontSize: 13 }}>{invoice.payment_method ?? '—'}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Subtotal</span><br /><span style={{ fontFamily: 'monospace' }}>{fmt(invoice.subtotal)}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>IVA</span><br /><span style={{ fontFamily: 'monospace' }}>{fmt(invoice.iva)}</span></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Total</span><br /><strong style={{ fontFamily: 'monospace', fontSize: 15 }}>{fmt(invoice.total)}</strong></div>
                <div><span style={{ fontSize: 12, color: '#6b7280' }}>Estado de pago</span><br />
                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(PAYMENT_COLOR[invoice.payment_status] ?? {}) }}>
                        {PAYMENT_LABEL[invoice.payment_status] ?? invoice.payment_status}
                    </span>
                </div>
            </div>

            {invoice.notes && (
                <div style={{ marginBottom: 12 }}>
                    <span style={{ fontSize: 12, color: '#6b7280' }}>Notas</span>
                    <p style={{ margin: '4px 0 0', fontSize: 13 }}>{invoice.notes}</p>
                </div>
            )}
        </>
    );
}

export default function PurchaseInvoicesIndex({ invoices, search, supplierId, suppliers }) {
    const [searchVal, setSearchVal]     = useState(search ?? '');
    const [supplierVal, setSupplierVal] = useState(supplierId ? String(supplierId) : '');
    const [viewItem, setViewItem]       = useState(null);

    const fmt = (n) => n != null
        ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2 })
        : '—';

    function handleSearch(e) {
        e.preventDefault();
        router.get(
            route('admin.purchase-invoices.index'),
            { search: searchVal, supplierId: supplierVal },
            { preserveState: true }
        );
    }

    function handleDelete(invoice) {
        if (!confirm(`¿Eliminar la factura ${invoice.invoice_number}? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.purchase-invoices.destroy', invoice.id));
    }

    const hasFilter = !!(search || supplierId);

    return (
        <AppLayout>
            <Head title="Facturas de compra" />

            <div className="top">
                <h1>Facturas de compra</h1>
                <a href={route('admin.purchase-invoices.export')} className="btn secondary">
                    Exportar
                </a>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            placeholder="Número de factura, proveedor…"
                        />
                    </label>
                    <label style={{ flex: '1 1 180px', margin: 0 }}>
                        Proveedor
                        <select value={supplierVal} onChange={(e) => setSupplierVal(e.target.value)}>
                            <option value="">Todos los proveedores</option>
                            {(suppliers ?? []).map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" className="btn">Buscar</button>
                        {hasFilter && (
                            <Link href={route('admin.purchase-invoices.index')} className="btn secondary">
                                Limpiar
                            </Link>
                        )}
                    </div>
                </form>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Tipo / N°</th>
                            <th style={{ textAlign: 'right' }}>Subtotal</th>
                            <th style={{ textAlign: 'right' }}>IVA</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Pago</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="empty">Sin facturas registradas</td>
                            </tr>
                        )}
                        {invoices.data.map((inv) => (
                            <tr key={inv.id}>
                                <td>
                                    <div style={{ fontSize: 13 }}>{inv.purchase_date ?? '—'}</div>
                                    {inv.due_date && (
                                        <div style={{ fontSize: 11, color: '#6b7280' }}>Vence: {inv.due_date}</div>
                                    )}
                                </td>
                                <td>
                                    <div style={{ fontWeight: 500 }}>{inv.supplier?.name ?? '—'}</div>
                                    {inv.supplier?.document_number && (
                                        <div style={{ fontSize: 11, color: '#6b7280' }}>{inv.supplier.document_number}</div>
                                    )}
                                </td>
                                <td>
                                    <div style={{ fontSize: 12, color: '#6b7280' }}>{DOC_LABELS[inv.document_type] ?? inv.document_type}</div>
                                    <div style={{ fontFamily: 'monospace', fontSize: 12 }}>{inv.invoice_number}</div>
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>{fmt(inv.subtotal)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>{fmt(inv.iva)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>{fmt(inv.total)}</td>
                                <td>
                                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(PAYMENT_COLOR[inv.payment_status] ?? {}) }}>
                                        {PAYMENT_LABEL[inv.payment_status] ?? inv.payment_status}
                                    </span>
                                </td>
                                <td>
                                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(STATUS_COLOR[inv.status] ?? {}) }}>
                                        {STATUS_LABEL[inv.status] ?? inv.status}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn secondary"
                                            style={{ fontSize: 12, padding: '3px 10px' }}
                                            onClick={() => setViewItem(inv)}
                                        >
                                            Ver
                                        </button>
                                        <button
                                            type="button"
                                            style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                            onClick={() => handleDelete(inv)}
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

            {viewItem && (
                <Modal title={`Factura ${viewItem.invoice_number}`} onClose={() => setViewItem(null)} maxWidth={560}>
                    <InvoiceDetail invoice={viewItem} />
                </Modal>
            )}
        </AppLayout>
    );
}
