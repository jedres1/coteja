import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
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

// ─── ExtractForm ──────────────────────────────────────────────────────────────

function ExtractForm() {
    const { data, setData, post, processing, errors } = useForm({
        from_date: '',
        to_date:   '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.extract'));
    }

    return (
        <form onSubmit={handleSubmit} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
            <label style={{ flex: '1 1 160px', margin: 0 }}>
                Desde
                <input
                    type="date"
                    value={data.from_date}
                    onChange={(e) => setData('from_date', e.target.value)}
                    required
                />
                {errors.from_date && <p className="field-error">{errors.from_date}</p>}
            </label>
            <label style={{ flex: '1 1 160px', margin: 0 }}>
                Hasta
                <input
                    type="date"
                    value={data.to_date}
                    onChange={(e) => setData('to_date', e.target.value)}
                    required
                />
                {errors.to_date && <p className="field-error">{errors.to_date}</p>}
            </label>
            <button type="submit" className="btn" disabled={processing}>
                {processing ? 'Extrayendo…' : 'Extraer facturas'}
            </button>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PendingApproval({ invoices, total, showAll, documentTypes, statusLabels }) {
    const [selectedIds, setSelectedIds] = useState([]);
    const [selectAll, setSelectAll]     = useState(false);
    const [viewItem, setViewItem]       = useState(null);

    const ids = invoices.data.map((i) => i.id);

    function toggleSelectAll() {
        if (selectAll) {
            setSelectedIds([]);
            setSelectAll(false);
        } else {
            setSelectedIds(ids);
            setSelectAll(true);
        }
    }

    function toggleRow(id) {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    }

    useEffect(() => {
        setSelectAll(ids.length > 0 && ids.every((id) => selectedIds.includes(id)));
    }, [selectedIds, invoices.data]);

    function handleApprove(invoice) {
        router.post(route('admin.purchase-invoices.approve', invoice.id));
    }

    function handleReject(invoice) {
        router.post(route('admin.purchase-invoices.reject', invoice.id));
    }

    function handleBulkApprove() {
        if (selectedIds.length === 0) return;
        router.post(route('admin.purchase-invoices.approve-bulk'), { ids: selectedIds });
    }

    function handleBulkReject() {
        if (selectedIds.length === 0) return;
        router.post(route('admin.purchase-invoices.reject-bulk'), { ids: selectedIds });
    }

    function handleToggleShowAll() {
        router.get(route('admin.purchase-invoices.pending-approval'), { show_all: !showAll });
    }

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    const statusBadgeMap = {
        pending:  'suspended',
        approved: 'active',
        rejected: 'danger',
    };

    return (
        <AppLayout>
            <Head title="Extraer facturas" />

            <div className="top">
                <div>
                    <h1>Extraer facturas</h1>
                    <p className="muted" style={{ margin: '4px 0 0' }}>
                        {total} factura{total !== 1 ? 's' : ''} pendiente{total !== 1 ? 's' : ''} de aprobación
                    </p>
                </div>
            </div>

            {/* Extract form */}
            <div className="card" style={{ marginBottom: 16 }}>
                <ExtractForm />
            </div>

            {/* Bulk actions + show-all toggle */}
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12, alignItems: 'center' }}>
                {selectedIds.length > 0 && (
                    <>
                        <span className="muted">{selectedIds.length} seleccionada{selectedIds.length !== 1 ? 's' : ''}</span>
                        <button type="button" className="btn" onClick={handleBulkApprove}>
                            Aprobar selección
                        </button>
                        <button type="button" className="btn danger" onClick={handleBulkReject}>
                            Rechazar selección
                        </button>
                    </>
                )}
                <button
                    type="button"
                    className="btn secondary"
                    style={{ marginLeft: 'auto' }}
                    onClick={handleToggleShowAll}
                >
                    {showAll ? 'Ver solo pendientes' : 'Ver todas'}
                </button>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th style={{ width: 36 }}>
                                <input
                                    type="checkbox"
                                    checked={selectAll}
                                    onChange={toggleSelectAll}
                                    style={{ width: 'auto' }}
                                />
                            </th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Tipo / Número</th>
                            <th>Subtotal</th>
                            <th>IVA</th>
                            <th>Total</th>
                            <th>Notas</th>
                            {showAll && <th>Estado</th>}
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={showAll ? 10 : 9} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {invoices.data.map((invoice) => (
                            <tr key={invoice.id}>
                                <td>
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.includes(invoice.id)}
                                        onChange={() => toggleRow(invoice.id)}
                                        style={{ width: 'auto' }}
                                    />
                                </td>
                                <td className="muted">{invoice.date}</td>
                                <td style={{ fontWeight: 600 }}>{invoice.supplier?.name}</td>
                                <td>
                                    <span>{documentTypes[invoice.document_type] ?? invoice.document_type}</span>
                                    <br />
                                    <span className="muted" style={{ fontSize: 12 }}>{invoice.invoice_number}</span>
                                </td>
                                <td>{fmt(invoice.subtotal)}</td>
                                <td>{fmt(invoice.iva)}</td>
                                <td style={{ fontWeight: 600 }}>{fmt(invoice.total)}</td>
                                <td
                                    style={{ maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                    title={invoice.notes ?? ''}
                                >
                                    {invoice.notes
                                        ? invoice.notes.length > 60
                                            ? invoice.notes.slice(0, 60) + '…'
                                            : invoice.notes
                                        : <span className="muted">—</span>
                                    }
                                </td>
                                {showAll && (
                                    <td>
                                        <span className={`badge ${statusBadgeMap[invoice.status] ?? ''}`}>
                                            {statusLabels[invoice.status] ?? invoice.status}
                                        </span>
                                    </td>
                                )}
                                <td>
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 12, padding: '4px 8px' }}
                                            onClick={() => setViewItem(invoice)}
                                        >
                                            Ver
                                        </button>
                                        {invoice.status === 'pending' && (
                                            <>
                                                <button
                                                    type="button"
                                                    className="btn btn-sm"
                                                    style={{ fontSize: 12, padding: '4px 8px' }}
                                                    onClick={() => handleApprove(invoice)}
                                                >
                                                    Aprobar
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-sm danger"
                                                    style={{ fontSize: 12, padding: '4px 8px' }}
                                                    onClick={() => handleReject(invoice)}
                                                >
                                                    Rechazar
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={invoices.links} />
            </div>

            {viewItem && (
                <Modal
                    title={`Documento — ${viewItem.invoice_number}`}
                    onClose={() => setViewItem(null)}
                >
                    <DocumentDataView data={viewItem.document_data} />
                </Modal>
            )}
        </AppLayout>
    );
}
