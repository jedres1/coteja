import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

// ─── DteDetailView ────────────────────────────────────────────────────────────

const TIPOS_DTE = {
    '01': 'Factura consumidor final',
    '03': 'CCF',
    '04': 'Nota de remisión',
    '05': 'Nota de crédito',
    '06': 'Nota de débito',
    '07': 'Comp. de retención',
    '11': 'Fact. sujeto excluido',
    '14': 'Fact. de exportación',
};

const METODOS_PAGO = {
    '01': 'Billetes y monedas',
    '02': 'Tarjeta débito',
    '03': 'Tarjeta crédito',
    '04': 'Cheque',
    '05': 'Transferencia bancaria',
    '06': 'Cupones',
    '07': 'Vales',
    '08': 'Otros',
};

function Section({ title, children }) {
    return (
        <section style={{ marginBottom: 14, padding: '10px 12px', background: '#f8fafc', borderRadius: 6, border: '1px solid #e2e8f0' }}>
            <div style={{ fontWeight: 700, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#6b7280', marginBottom: 8 }}>
                {title}
            </div>
            {children}
        </section>
    );
}

function Row({ label, value, mono = false, full = false }) {
    return (
        <>
            <div style={full ? { gridColumn: '1/-1', color: '#6b7280', fontSize: 12 } : { color: '#6b7280', fontSize: 12 }}>{label}</div>
            {!full && (
                <div style={{ fontFamily: mono ? 'monospace' : undefined, fontSize: 13, wordBreak: 'break-all' }}>{value ?? '—'}</div>
            )}
            {full && (
                <div style={{ gridColumn: '1/-1', fontFamily: mono ? 'monospace' : undefined, fontSize: 12, wordBreak: 'break-all', marginTop: -4 }}>{value ?? '—'}</div>
            )}
        </>
    );
}

function DteDetailView({ invoice }) {
    const fmt = (n) => n != null ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';

    const dte = (() => {
        const raw = invoice.extracted_document_body;
        if (!raw) return null;
        try { return typeof raw === 'string' ? JSON.parse(raw) : raw; }
        catch { return null; }
    })();

    if (!dte) {
        return (
            <div style={{ fontSize: 13 }}>
                <Section title="Factura">
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 16px' }}>
                        <Row label="Proveedor" value={invoice.supplier?.name} />
                        <Row label="Fecha" value={invoice.purchase_date} />
                        <Row label="Número" value={invoice.invoice_number} mono />
                        <Row label="Subtotal" value={fmt(invoice.subtotal)} mono />
                        <Row label="IVA" value={fmt(invoice.iva)} mono />
                        <Row label="Total" value={fmt(invoice.total)} mono />
                    </div>
                </Section>
                <p className="muted" style={{ fontSize: 12 }}>No hay datos DTE adjuntos para esta factura.</p>
            </div>
        );
    }

    const id      = dte.identificacion   ?? {};
    const emisor  = dte.emisor           ?? {};
    const receptor = dte.receptor        ?? {};
    const items   = dte.cuerpoDocumento  ?? [];
    const res     = dte.resumen          ?? {};

    return (
        <div style={{ fontSize: 13, maxHeight: '75vh', overflowY: 'auto', paddingRight: 4 }}>

            {/* Identificación */}
            <Section title="Identificación">
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 16px' }}>
                    <Row label="Tipo DTE" value={TIPOS_DTE[id.tipoDte] ?? id.tipoDte} />
                    <Row label="Fecha / Hora" value={`${id.fecEmi ?? ''} ${id.horEmi ?? ''}`.trim()} />
                    <Row label="Número de control" value={id.numeroControl} mono full />
                    <Row label="Código de generación" value={id.codigoGeneracion} mono full />
                </div>
            </Section>

            {/* Emisor / Receptor */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 14 }}>
                <Section title="Emisor">
                    <div style={{ fontWeight: 600, marginBottom: 4 }}>{emisor.nombre ?? '—'}</div>
                    {emisor.nombreComercial && emisor.nombreComercial !== emisor.nombre && (
                        <div style={{ color: '#6b7280', marginBottom: 4 }}>{emisor.nombreComercial}</div>
                    )}
                    <div style={{ fontSize: 12, color: '#6b7280' }}>NIT: <span style={{ color: '#111' }}>{emisor.nit ?? '—'}</span></div>
                    <div style={{ fontSize: 12, color: '#6b7280' }}>NRC: <span style={{ color: '#111' }}>{emisor.nrc ?? '—'}</span></div>
                    {emisor.correo && <div style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>{emisor.correo}</div>}
                    {emisor.telefono && <div style={{ fontSize: 12, color: '#6b7280' }}>{emisor.telefono}</div>}
                </Section>
                <Section title="Receptor">
                    <div style={{ fontWeight: 600, marginBottom: 4 }}>{receptor.nombre ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#6b7280' }}>Doc: <span style={{ color: '#111' }}>{receptor.numDocumento ?? '—'}</span></div>
                    {receptor.nrc && <div style={{ fontSize: 12, color: '#6b7280' }}>NRC: <span style={{ color: '#111' }}>{receptor.nrc}</span></div>}
                    {receptor.correo && <div style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>{receptor.correo}</div>}
                    {receptor.telefono && <div style={{ fontSize: 12, color: '#6b7280' }}>{receptor.telefono}</div>}
                </Section>
            </div>

            {/* Detalle de líneas */}
            {items.length > 0 && (
                <Section title={`Detalle (${items.length} línea${items.length !== 1 ? 's' : ''})`}>
                    <table style={{ width: '100%', fontSize: 12, borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid #e2e8f0' }}>
                                <th style={{ textAlign: 'left', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600 }}>Descripción</th>
                                <th style={{ textAlign: 'right', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' }}>Cant.</th>
                                <th style={{ textAlign: 'right', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' }}>P. Unit.</th>
                                <th style={{ textAlign: 'right', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' }}>Desc.</th>
                                <th style={{ textAlign: 'right', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' }}>Gravado</th>
                                <th style={{ textAlign: 'right', padding: '3px 6px 5px', color: '#6b7280', fontWeight: 600 }}>IVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => (
                                <tr key={i} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                    <td style={{ padding: '5px 6px', verticalAlign: 'top' }}>
                                        {item.codigo && (
                                            <span style={{ fontSize: 11, color: '#6b7280', marginRight: 4 }}>[{item.codigo}]</span>
                                        )}
                                        {item.descripcion}
                                    </td>
                                    <td style={{ textAlign: 'right', padding: '5px 6px', fontFamily: 'monospace' }}>{item.cantidad}</td>
                                    <td style={{ textAlign: 'right', padding: '5px 6px', fontFamily: 'monospace' }}>{fmt(item.precioUni)}</td>
                                    <td style={{ textAlign: 'right', padding: '5px 6px', fontFamily: 'monospace' }}>
                                        {item.montoDescu > 0 ? fmt(item.montoDescu) : <span style={{ color: '#d1d5db' }}>—</span>}
                                    </td>
                                    <td style={{ textAlign: 'right', padding: '5px 6px', fontFamily: 'monospace' }}>{fmt(item.ventaGravada)}</td>
                                    <td style={{ textAlign: 'right', padding: '5px 6px', fontFamily: 'monospace' }}>{fmt(item.ivaItem)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Section>
            )}

            {/* Resumen */}
            <Section title="Resumen">
                <div style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: '3px 24px', maxWidth: 340, marginLeft: 'auto' }}>
                    {res.subTotalVentas > 0 && <>
                        <div style={{ color: '#6b7280' }}>Subtotal ventas</div>
                        <div style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(res.subTotalVentas)}</div>
                    </>}
                    {res.totalDescu > 0 && <>
                        <div style={{ color: '#6b7280' }}>Descuento</div>
                        <div style={{ textAlign: 'right', fontFamily: 'monospace', color: '#dc2626' }}>-{fmt(res.totalDescu)}</div>
                    </>}
                    {res.totalGravada > 0 && <>
                        <div style={{ color: '#6b7280' }}>Total gravado</div>
                        <div style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(res.totalGravada)}</div>
                    </>}
                    {res.totalExenta > 0 && <>
                        <div style={{ color: '#6b7280' }}>Total exento</div>
                        <div style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(res.totalExenta)}</div>
                    </>}
                    {res.totalNoSuj > 0 && <>
                        <div style={{ color: '#6b7280' }}>Total no sujeto</div>
                        <div style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(res.totalNoSuj)}</div>
                    </>}
                    <div style={{ color: '#6b7280' }}>IVA</div>
                    <div style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(res.totalIva)}</div>
                    <div style={{ fontWeight: 700, paddingTop: 6, borderTop: '1px solid #e2e8f0', marginTop: 4 }}>Total a pagar</div>
                    <div style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 700, fontSize: 15, paddingTop: 6, borderTop: '1px solid #e2e8f0', marginTop: 4 }}>{fmt(res.totalPagar)}</div>
                </div>
                {res.totalLetras && (
                    <div style={{ marginTop: 10, color: '#6b7280', fontStyle: 'italic', fontSize: 12, borderTop: '1px solid #e2e8f0', paddingTop: 8 }}>
                        {res.totalLetras}
                    </div>
                )}
                {res.pagos?.length > 0 && (
                    <div style={{ marginTop: 8, fontSize: 12 }}>
                        <span style={{ color: '#6b7280' }}>Forma de pago: </span>
                        {res.pagos.map((p, i) => (
                            <span key={i}>
                                {METODOS_PAGO[p.codigo] ?? p.codigo} — {fmt(p.montoPago)}
                                {p.referencia ? ` (Ref: ${p.referencia})` : ''}
                                {i < res.pagos.length - 1 ? ', ' : ''}
                            </span>
                        ))}
                    </div>
                )}
            </Section>
        </div>
    );
}

// ─── ExtractForm ──────────────────────────────────────────────────────────────

function ExtractForm() {
    const today        = new Date().toISOString().split('T')[0];
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const { data, setData, post, processing, errors } = useForm({
        from_date: firstOfMonth,
        to_date:   today,
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

// ─── Constants ────────────────────────────────────────────────────────────────

const documentTypes = {
    '01': 'Fact. consumidor final',
    '03': 'CCF',
    '04': 'Nota de remisión',
    '05': 'Nota de crédito',
    '06': 'Nota de débito',
    '07': 'Comp. de retención',
    '08': 'Comp. de liquidación',
    '09': 'Doc. contable de liquidación',
    '11': 'Fact. sujeto excluido',
    '14': 'Fact. de exportación',
};

const statusLabels = {
    pending:  'Pendiente',
    approved: 'Aprobado',
    rejected: 'Rechazado',
};

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PendingApproval({ invoices, total, showAll }) {
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
                            <th>Receptor</th>
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
                                <td colSpan={showAll ? 11 : 10} className="empty">Sin registros</td>
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
                                    {invoice.receptor_name
                                        ? <span style={{ fontSize: 13 }}>{invoice.receptor_name}</span>
                                        : <span className="muted">—</span>
                                    }
                                </td>
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
                    maxWidth={820}
                >
                    <DteDetailView invoice={viewItem} />
                </Modal>
            )}
        </AppLayout>
    );
}
