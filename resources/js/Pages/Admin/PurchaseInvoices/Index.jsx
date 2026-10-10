import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
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

const DOC_TYPE_OPTIONS = [
    { value: '01', label: '01 - Factura consumidor final' },
    { value: '03', label: '03 - Comprobante de Crédito Fiscal' },
    { value: '05', label: '05 - Nota de Crédito' },
    { value: '06', label: '06 - Nota de Débito' },
    { value: '11', label: '11 - Factura de Exportación' },
    { value: '14', label: '14 - Factura Sujeto Excluido' },
    { value: '99', label: '99 - Otro' },
];

const badge = (map, key) => {
    const style = map[key] ?? { background: '#f3f4f6', color: '#374151' };
    return (
        <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, whiteSpace: 'nowrap', ...style }}>
            {(map === PAYMENT_LABEL ? PAYMENT_LABEL[key] : STATUS_LABEL[key]) ?? key}
        </span>
    );
};

function CreateForm({ suppliers, onCancel }) {
    const today = new Date().toISOString().split('T')[0];
    const { data, setData, post, processing, errors, reset } = useForm({
        supplier_id:    '',
        document_type:  '03',
        invoice_number: '',
        purchase_date:  today,
        due_date:       '',
        subtotal:       '',
        iva:            '',
        total:          '',
        payment_method: '',
        payment_status: 'pending',
        status:         'registered',
        notes:          '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.store'), {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    const g = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12 };
    const err = (f) => errors[f] && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors[f]}</span>;

    return (
        <form onSubmit={handleSubmit} noValidate>
            <div style={g}>
                <label style={{ margin: 0 }}>
                    Proveedor <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} required>
                        <option value="">— Seleccionar —</option>
                        {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    {err('supplier_id')}
                </label>
                <label style={{ margin: 0 }}>
                    Tipo de documento <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                        {DOC_TYPE_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                    {err('document_type')}
                </label>
                <label style={{ margin: 0 }}>
                    N° de factura <span style={{ color: '#ef4444' }}>*</span>
                    <input type="text" value={data.invoice_number} onChange={(e) => setData('invoice_number', e.target.value)} placeholder="Número de documento" />
                    {err('invoice_number')}
                </label>
                <label style={{ margin: 0 }}>
                    Fecha de compra <span style={{ color: '#ef4444' }}>*</span>
                    <input type="date" value={data.purchase_date} onChange={(e) => setData('purchase_date', e.target.value)} />
                    {err('purchase_date')}
                </label>
                <label style={{ margin: 0 }}>
                    Fecha de vencimiento
                    <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                    {err('due_date')}
                </label>
                <label style={{ margin: 0 }}>
                    Subtotal <span style={{ color: '#ef4444' }}>*</span>
                    <input type="number" step="0.01" min="0" value={data.subtotal} onChange={(e) => setData('subtotal', e.target.value)} />
                    {err('subtotal')}
                </label>
                <label style={{ margin: 0 }}>
                    IVA <span style={{ color: '#ef4444' }}>*</span>
                    <input type="number" step="0.01" min="0" value={data.iva} onChange={(e) => setData('iva', e.target.value)} />
                    {err('iva')}
                </label>
                <label style={{ margin: 0 }}>
                    Total <span style={{ color: '#ef4444' }}>*</span>
                    <input type="number" step="0.01" min="0" value={data.total} onChange={(e) => setData('total', e.target.value)} />
                    {err('total')}
                </label>
                <label style={{ margin: 0 }}>
                    Método de pago
                    <input type="text" value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)} placeholder="Transferencia, cheque…" />
                    {err('payment_method')}
                </label>
                <label style={{ margin: 0 }}>
                    Estado de pago
                    <select value={data.payment_status} onChange={(e) => setData('payment_status', e.target.value)}>
                        <option value="pending">Pendiente</option>
                        <option value="partial">Parcial</option>
                        <option value="paid">Pagado</option>
                    </select>
                    {err('payment_status')}
                </label>
                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Notas
                    <input type="text" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    {err('notes')}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : 'Registrar factura'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

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

function DteSection({ title, children }) {
    return (
        <section style={{ marginBottom: 14, padding: '10px 12px', background: '#f8fafc', borderRadius: 6, border: '1px solid #e2e8f0' }}>
            <div style={{ fontWeight: 700, fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#6b7280', marginBottom: 8 }}>
                {title}
            </div>
            {children}
        </section>
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
                <DteSection title="Factura">
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px 16px' }}>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Proveedor</span><br /><strong>{invoice.supplier?.name ?? '—'}</strong></div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>NIT / NRC</span><br />{invoice.supplier?.document_number ?? '—'}</div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Fecha de compra</span><br />{invoice.purchase_date ?? '—'}</div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Vencimiento</span><br />{invoice.due_date ?? '—'}</div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Tipo</span><br />{TIPOS_DTE[invoice.document_type] ?? invoice.document_type ?? '—'}</div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Método de pago</span><br />{invoice.payment_method ?? '—'}</div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Subtotal</span><br /><span style={{ fontFamily: 'monospace' }}>{fmt(invoice.subtotal)}</span></div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>IVA</span><br /><span style={{ fontFamily: 'monospace' }}>{fmt(invoice.iva)}</span></div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Total</span><br /><strong style={{ fontFamily: 'monospace', fontSize: 15 }}>{fmt(invoice.total)}</strong></div>
                        <div><span style={{ color: '#6b7280', fontSize: 12 }}>Estado de pago</span><br />
                            <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(PAYMENT_COLOR[invoice.payment_status] ?? {}) }}>
                                {PAYMENT_LABEL[invoice.payment_status] ?? invoice.payment_status}
                            </span>
                        </div>
                    </div>
                    {invoice.notes && (
                        <div style={{ marginTop: 10, paddingTop: 10, borderTop: '1px solid #e2e8f0' }}>
                            <span style={{ color: '#6b7280', fontSize: 12 }}>Notas</span>
                            <p style={{ margin: '4px 0 0', fontSize: 13 }}>{invoice.notes}</p>
                        </div>
                    )}
                </DteSection>
            </div>
        );
    }

    const id       = dte.identificacion  ?? {};
    const emisor   = dte.emisor          ?? {};
    const receptor = dte.receptor        ?? {};
    const items    = dte.cuerpoDocumento ?? [];
    const res      = dte.resumen         ?? {};

    return (
        <div style={{ fontSize: 13, maxHeight: '75vh', overflowY: 'auto', paddingRight: 4 }}>

            {/* Estado de pago (sistema interno) */}
            <div style={{ display: 'flex', gap: 8, marginBottom: 12, flexWrap: 'wrap', alignItems: 'center' }}>
                <span style={{ color: '#6b7280', fontSize: 12 }}>Estado de pago:</span>
                <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(PAYMENT_COLOR[invoice.payment_status] ?? {}) }}>
                    {PAYMENT_LABEL[invoice.payment_status] ?? invoice.payment_status}
                </span>
                {invoice.notes && (
                    <span style={{ color: '#6b7280', fontSize: 12, marginLeft: 8 }}>Notas: {invoice.notes}</span>
                )}
            </div>

            {/* Identificación */}
            <DteSection title="Identificación">
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 16px' }}>
                    <div><span style={{ color: '#6b7280', fontSize: 12 }}>Tipo DTE</span><br />{TIPOS_DTE[id.tipoDte] ?? id.tipoDte ?? '—'}</div>
                    <div><span style={{ color: '#6b7280', fontSize: 12 }}>Fecha / Hora</span><br />{`${id.fecEmi ?? ''} ${id.horEmi ?? ''}`.trim() || '—'}</div>
                    <div style={{ gridColumn: '1/-1' }}><span style={{ color: '#6b7280', fontSize: 12 }}>Número de control</span><br /><span style={{ fontFamily: 'monospace', fontSize: 12 }}>{id.numeroControl ?? '—'}</span></div>
                    <div style={{ gridColumn: '1/-1' }}><span style={{ color: '#6b7280', fontSize: 12 }}>Código de generación</span><br /><span style={{ fontFamily: 'monospace', fontSize: 11, wordBreak: 'break-all' }}>{id.codigoGeneracion ?? '—'}</span></div>
                </div>
            </DteSection>

            {/* Emisor / Receptor */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 14 }}>
                <DteSection title="Emisor">
                    <div style={{ fontWeight: 600, marginBottom: 4 }}>{emisor.nombre ?? '—'}</div>
                    {emisor.nombreComercial && emisor.nombreComercial !== emisor.nombre && (
                        <div style={{ color: '#6b7280', marginBottom: 4 }}>{emisor.nombreComercial}</div>
                    )}
                    <div style={{ fontSize: 12, color: '#6b7280' }}>NIT: <span style={{ color: '#111' }}>{emisor.nit ?? '—'}</span></div>
                    <div style={{ fontSize: 12, color: '#6b7280' }}>NRC: <span style={{ color: '#111' }}>{emisor.nrc ?? '—'}</span></div>
                    {emisor.correo && <div style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>{emisor.correo}</div>}
                    {emisor.telefono && <div style={{ fontSize: 12, color: '#6b7280' }}>{emisor.telefono}</div>}
                </DteSection>
                <DteSection title="Receptor">
                    <div style={{ fontWeight: 600, marginBottom: 4 }}>{receptor.nombre ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#6b7280' }}>Doc: <span style={{ color: '#111' }}>{receptor.numDocumento ?? '—'}</span></div>
                    {receptor.nrc && <div style={{ fontSize: 12, color: '#6b7280' }}>NRC: <span style={{ color: '#111' }}>{receptor.nrc}</span></div>}
                    {receptor.correo && <div style={{ fontSize: 12, color: '#6b7280', marginTop: 2 }}>{receptor.correo}</div>}
                    {receptor.telefono && <div style={{ fontSize: 12, color: '#6b7280' }}>{receptor.telefono}</div>}
                </DteSection>
            </div>

            {/* Detalle de líneas */}
            {items.length > 0 && (
                <DteSection title={`Detalle (${items.length} línea${items.length !== 1 ? 's' : ''})`}>
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
                                        {item.codigo && <span style={{ fontSize: 11, color: '#6b7280', marginRight: 4 }}>[{item.codigo}]</span>}
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
                </DteSection>
            )}

            {/* Resumen */}
            <DteSection title="Resumen">
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
            </DteSection>
        </div>
    );
}

export default function PurchaseInvoicesIndex({ invoices, search, supplierId, suppliers, from: fromProp = '', to: toProp = '' }) {
    const today        = new Date().toISOString().split('T')[0];
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    const [showCreate, setShowCreate]   = useState(false);
    const [searchVal, setSearchVal]     = useState(search ?? '');
    const [supplierVal, setSupplierVal] = useState(supplierId ? String(supplierId) : '');
    const [fromVal, setFromVal]         = useState(fromProp || firstOfMonth);
    const [toVal, setToVal]             = useState(toProp   || today);
    const [viewItem, setViewItem]       = useState(null);

    const fmt = (n) => n != null
        ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2 })
        : '—';

    function handleSearch(e) {
        e.preventDefault();
        router.get(
            route('admin.purchase-invoices.index'),
            { search: searchVal, supplier_id: supplierVal, from: fromVal, to: toVal },
            { preserveState: true }
        );
    }

    function clearFilters() {
        setSearchVal(''); setSupplierVal(''); setFromVal(firstOfMonth); setToVal(today);
        router.get(route('admin.purchase-invoices.index'), { from: firstOfMonth, to: today });
    }

    function handleDelete(invoice) {
        if (!confirm(`¿Eliminar la factura ${invoice.invoice_number}? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.purchase-invoices.destroy', invoice.id));
    }

    return (
        <AppLayout>
            <Head title="Facturas de compra" />

            <div className="top">
                <h1>Facturas de compra</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.purchase-invoices.export')} className="btn secondary">Exportar</a>
                    <button type="button" className="btn" onClick={() => setShowCreate((v) => !v)}>
                        {showCreate ? 'Cancelar' : '+ Nueva factura'}
                    </button>
                </div>
            </div>

            {showCreate && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ margin: '0 0 16px', fontSize: 15, fontWeight: 600 }}>Registrar factura de compra</h2>
                    <CreateForm suppliers={suppliers ?? []} onCancel={() => setShowCreate(false)} />
                </div>
            )}

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
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Desde
                        <input type="date" value={fromVal} onChange={(e) => setFromVal(e.target.value)} />
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Hasta
                        <input type="date" value={toVal} onChange={(e) => setToVal(e.target.value)} />
                    </label>
                    <button type="submit" className="btn">Filtrar</button>
                    <button type="button" className="btn secondary" onClick={clearFilters}>Limpiar</button>
                </form>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Receptor</th>
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
                                <td colSpan={10} className="empty">Sin facturas registradas</td>
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
                                    {inv.receptor_name
                                        ? <div style={{ fontSize: 13 }}>{inv.receptor_name}</div>
                                        : <span style={{ color: '#6b7280' }}>—</span>
                                    }
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
                <Modal title={`Factura ${viewItem.invoice_number}`} onClose={() => setViewItem(null)} maxWidth={820}>
                    <DteDetailView invoice={viewItem} />
                </Modal>
            )}
        </AppLayout>
    );
}
