import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

const DTE_TYPES = [
    { code: '01', label: 'Factura' },
    { code: '03', label: 'CCF' },
    { code: '04', label: 'Nota de Remisión' },
    { code: '05', label: 'Nota de Crédito' },
    { code: '06', label: 'Nota de Débito' },
    { code: '07', label: 'Comp. Retención' },
    { code: '08', label: 'Comp. Liquidación' },
    { code: '11', label: 'Factura Exportación' },
    { code: '14', label: 'Sujeto Excluido' },
    { code: '15', label: 'Comp. Donación' },
];

const STATUS_STYLE = {
    PENDIENTE:    { background: '#fef3c7', color: '#92400e' },
    FIRMADO:      { background: '#eff6ff', color: '#1d4ed8' },
    CONTINGENCIA: { background: '#fff7ed', color: '#c2410c' },
    ENVIADO:      { background: '#e0f2fe', color: '#0369a1' },
    ACEPTADO:     { background: '#f0fdf4', color: '#15803d' },
    RECHAZADO:    { background: '#fef2f2', color: '#dc2626' },
    ANULADO:      { background: '#f1f5f9', color: '#64748b' },
};

const VOID_TYPE_OPTIONS = [
    { value: 1, label: 'Error en el documento' },
    { value: 2, label: 'Rescindir operación' },
    { value: 3, label: 'Otro' },
];

const EOE_TIPO_OPTIONS = [
    { value: '01', label: 'Compra / Venta' },
    { value: '02', label: 'Exportación' },
    { value: '03', label: 'Otro' },
];

const CONTINGENCIA_TIPO_OPTIONS = [
    { value: 1, label: 'Falla en el sistema informático del emisor' },
    { value: 2, label: 'Falla en las comunicaciones' },
    { value: 3, label: 'Falla en el sistema de la Administración Tributaria' },
    { value: 4, label: 'Falla en el suministro eléctrico' },
    { value: 5, label: 'Otro motivo' },
];

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

// ── void window helpers ───────────────────────────────────────────────────────
function parseInvoiceDate(value) {
    const m = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), 0, 0, 0, 0);
    return isNaN(d.getTime()) ? null : d;
}
function endOfLocalDay(d) { const e = new Date(d); e.setHours(23, 59, 59, 999); return e; }
function addLocalDays(d, n) { const e = new Date(d); e.setDate(e.getDate() + n); return e; }

function invoiceWithinVoidWindow(invoice) {
    const type   = String(invoice?.documentType ?? '').padStart(2, '0');
    const issued = parseInvoiceDate(invoice?.date);
    if (!issued) return false;
    if (type === '03') return Date.now() <= endOfLocalDay(addLocalDays(issued, 1)).getTime();
    if (['01', '11', '14'].includes(type)) return Date.now() <= endOfLocalDay(addLocalDays(issued, 90)).getTime();
    return true;
}
function canVoidInvoice(invoice) {
    return Boolean(
        invoice?.accepted &&
        invoice?.receptionStamp &&
        ['ENVIADO', 'ACEPTADO'].includes(invoice?.status) &&
        invoiceWithinVoidWindow(invoice)
    );
}
function allowedVoidTypes(invoice) {
    const type = String(invoice?.documentType ?? '').padStart(2, '0');
    if (['01', '11'].includes(type)) return [1, 2, 3];
    if (type === '03') return invoiceWithinVoidWindow(invoice) ? [1, 2, 3] : [1, 3];
    if (['05', '08'].includes(type)) return [2];
    return [1, 3];
}
function requiresVoidReplacement(documentType, voidType) {
    const type = String(documentType ?? '').padStart(2, '0');
    if (![1, 3].includes(Number(voidType))) return false;
    return !['05', '08'].includes(type);
}
function voidHelpText(invoice) {
    const type = String(invoice?.documentType ?? '').padStart(2, '0');
    if (type === '03') return 'El CCF puede invalidarse directamente hasta las 23:59 del día siguiente a su emisión. Después debe corregirse con Nota de Crédito.';
    if (['01', '11', '14'].includes(type)) return 'Este documento puede anularse directamente durante 90 días desde su emisión.';
    return 'Se enviará un evento de invalidación a Hacienda. Si es aceptado, la factura quedará marcada como ANULADO.';
}
function voidReplacementHelp(documentType) {
    const type = String(documentType ?? '').padStart(2, '0');
    if (type === '03') return 'Ingrese el código de generación del CCF corregido que reemplaza al documento.';
    return 'Ingrese el código de generación del DTE que reemplaza al documento a invalidar.';
}
function canReturnInvoice(invoice) {
    return (
        String(invoice?.documentType ?? '').padStart(2, '0') === '11' &&
        Boolean(invoice?.accepted && invoice?.receptionStamp) &&
        ['ENVIADO', 'ACEPTADO'].includes(invoice?.status) &&
        !invoice?.returnStamp
    );
}
function canMarkContingencia(invoice) {
    return invoice?.status === 'FIRMADO' && !invoice?.accepted;
}
function canCorrectInvoice(invoice) {
    return invoice?.status === 'RECHAZADO';
}
// ─────────────────────────────────────────────────────────────────────────────

function StatCard({ label, value, mono = false }) {
    return (
        <div className="card" style={{ padding: '12px 16px', minWidth: 120 }}>
            <p style={{ margin: '0 0 4px', fontSize: 11, color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {label}
            </p>
            <p style={{ margin: 0, fontWeight: 700, fontSize: 18, fontFamily: mono ? 'monospace' : undefined }}>
                {value}
            </p>
        </div>
    );
}

function StatusBadge({ status }) {
    const style = STATUS_STYLE[status] ?? { background: '#f3f4f6', color: '#374151' };
    return (
        <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...style }}>
            {status}
        </span>
    );
}

function DetailModal({ invoice, onClose }) {
    if (!invoice) return null;
    const row = (label, value) => (
        <div key={label}>
            <p style={{ margin: '0 0 2px', fontSize: 11, color: '#6b7280' }}>{label}</p>
            <p style={{ margin: 0, fontSize: 13 }}>{value ?? '—'}</p>
        </div>
    );
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px 20px' }}>
                {row('Fecha', invoice.date)}
                {row('No. Control', invoice.numberControl)}
                {row('Código generación', <span style={{ fontFamily: 'monospace', fontSize: 11, wordBreak: 'break-all' }}>{invoice.generationCode}</span>)}
                {row('Tipo', DTE_TYPES.find((t) => t.code === invoice.documentType)?.label ?? invoice.documentType)}
                {row('Cliente', invoice.customerName)}
                {row('Total', `$${fmt(invoice.total)}`)}
                {row('Estado', <StatusBadge status={invoice.status} />)}
                {row('Aceptado', invoice.accepted ? 'Sí' : 'No')}
                {row('Correo enviado', invoice.emailSent ? 'Sí' : 'No')}
            </div>
            {invoice.receptionStamp && (
                <div>
                    <p style={{ margin: '0 0 2px', fontSize: 11, color: '#6b7280' }}>Sello de recepción</p>
                    <p style={{ margin: 0, fontFamily: 'monospace', fontSize: 11, wordBreak: 'break-all' }}>
                        {invoice.receptionStamp}
                    </p>
                </div>
            )}
            {invoice.returnStamp && (
                <div>
                    <p style={{ margin: '0 0 2px', fontSize: 11, color: '#6b7280' }}>Sello retorno</p>
                    <p style={{ margin: 0, fontFamily: 'monospace', fontSize: 11, wordBreak: 'break-all' }}>
                        {invoice.returnStamp}
                    </p>
                </div>
            )}
            {invoice.voidStamp && (
                <div>
                    <p style={{ margin: '0 0 2px', fontSize: 11, color: '#6b7280' }}>Sello anulación</p>
                    <p style={{ margin: 0, fontFamily: 'monospace', fontSize: 11, wordBreak: 'break-all' }}>
                        {invoice.voidStamp}
                    </p>
                    {invoice.voidReason && <p style={{ margin: '4px 0 0', fontSize: 12, color: '#6b7280' }}>Motivo: {invoice.voidReason}</p>}
                </div>
            )}
            {invoice.observations && (
                <div>
                    <p style={{ margin: '0 0 2px', fontSize: 11, color: '#6b7280' }}>Observaciones / Error</p>
                    <p style={{ margin: 0, fontSize: 12, color: '#dc2626', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                        {invoice.observations}
                    </p>
                </div>
            )}
            <div style={{ marginTop: 8 }}>
                <button type="button" className="btn secondary" onClick={onClose}>Cerrar</button>
            </div>
        </div>
    );
}

function VoidForm({ invoice, onClose }) {
    const allowed  = allowedVoidTypes(invoice);
    const form = useForm({
        tipoAnulacion:     allowed.includes(2) ? 2 : allowed[0],
        motivo:            allowed.includes(2) ? 'Rescindir operación realizada' : 'Error en la información del documento',
        codigoGeneracionR: '',
    });

    const needsReplacement = requiresVoidReplacement(invoice.documentType, form.data.tipoAnulacion);
    const helpText         = voidHelpText(invoice);
    const replacementHelp  = voidReplacementHelp(invoice.documentType);

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.factura-sv.facturas.anular', invoice.id), { onSuccess: onClose });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 6, padding: '8px 12px', marginBottom: 14, fontSize: 12, color: '#1e40af' }}>
                {helpText}
            </div>

            <div className="field">
                <label>Tipo de anulación CAT-024 <span style={{ color: '#ef4444' }}>*</span></label>
                <select
                    value={form.data.tipoAnulacion}
                    onChange={(e) => form.setData('tipoAnulacion', Number(e.target.value))}
                >
                    {VOID_TYPE_OPTIONS.filter((o) => allowed.includes(o.value)).map((o) => (
                        <option key={o.value} value={o.value}>{o.value} - {o.label}</option>
                    ))}
                </select>
                {form.errors.tipoAnulacion && <p className="field-error">{form.errors.tipoAnulacion}</p>}
            </div>

            {needsReplacement && (
                <div className="field">
                    <label>Código de generación reemplazo</label>
                    <input
                        type="text"
                        value={form.data.codigoGeneracionR}
                        onChange={(e) => form.setData('codigoGeneracionR', e.target.value)}
                        maxLength={36}
                        placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
                    />
                    <small style={{ fontSize: 11, color: '#6b7280' }}>{replacementHelp}</small>
                    {form.errors.codigoGeneracionR && <p className="field-error">{form.errors.codigoGeneracionR}</p>}
                </div>
            )}

            <div className="field">
                <label>Motivo <span style={{ color: '#ef4444' }}>*</span></label>
                <textarea
                    value={form.data.motivo}
                    onChange={(e) => form.setData('motivo', e.target.value)}
                    rows={4}
                    maxLength={250}
                    placeholder="Detalle el motivo de la anulación"
                    required
                />
                {form.errors.motivo && <p className="field-error">{form.errors.motivo}</p>}
            </div>

            <div className="form-actions">
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button
                    type="submit"
                    disabled={form.processing}
                    style={{ padding: '8px 16px', background: '#dc2626', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                >
                    {form.processing ? 'Enviando…' : 'Enviar anulación'}
                </button>
            </div>
        </form>
    );
}

const BLANK_ITEM = { descripcion: '', cantidad: 1, precioUni: 0, seguro: 0, flete: 0 };

function ReturnForm({ invoice, onClose }) {
    const [items, setItems] = useState([{ ...BLANK_ITEM }]);
    const form = useForm({ items, resumen: { condicionOperacion: 1, observaciones: '' } });

    function updateItem(i, field, value) {
        const next = items.map((it, idx) => idx === i ? { ...it, [field]: value } : it);
        setItems(next);
        form.setData('items', next);
    }
    function addItem() {
        const next = [...items, { ...BLANK_ITEM }];
        setItems(next);
        form.setData('items', next);
    }
    function removeItem(i) {
        const next = items.filter((_, idx) => idx !== i);
        setItems(next);
        form.setData('items', next);
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.factura-sv.facturas.retorno', invoice.id), { onSuccess: onClose });
    }

    const inputStyle = { width: '100%', padding: '4px 6px', fontSize: 12, border: '1px solid #d1d5db', borderRadius: 4 };
    const labelStyle = { fontSize: 11, color: '#6b7280', display: 'block', marginBottom: 2 };

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 6, padding: '8px 12px', marginBottom: 14, fontSize: 12, color: '#15803d' }}>
                Registra la devolución de mercancías exportadas. Se enviará un Evento de Retorno (ER) a Hacienda vinculado a esta FEXE.
            </div>

            <p style={{ fontWeight: 600, fontSize: 13, margin: '0 0 8px' }}>Ítems a retornar</p>
            {items.map((it, i) => (
                <div key={i} style={{ border: '1px solid #e5e7eb', borderRadius: 6, padding: 10, marginBottom: 8 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr 1fr auto', gap: 6, alignItems: 'flex-end' }}>
                        <div>
                            <label style={labelStyle}>Descripción *</label>
                            <input style={inputStyle} value={it.descripcion} onChange={e => updateItem(i, 'descripcion', e.target.value)} required />
                        </div>
                        <div>
                            <label style={labelStyle}>Cantidad *</label>
                            <input style={inputStyle} type="number" min="0.000001" step="any" value={it.cantidad} onChange={e => updateItem(i, 'cantidad', e.target.value)} required />
                        </div>
                        <div>
                            <label style={labelStyle}>Precio Unit. *</label>
                            <input style={inputStyle} type="number" min="0" step="any" value={it.precioUni} onChange={e => updateItem(i, 'precioUni', e.target.value)} required />
                        </div>
                        <div>
                            <label style={labelStyle}>Seguro</label>
                            <input style={inputStyle} type="number" min="0" step="any" value={it.seguro} onChange={e => updateItem(i, 'seguro', e.target.value)} />
                        </div>
                        <div>
                            <label style={labelStyle}>Flete</label>
                            <input style={inputStyle} type="number" min="0" step="any" value={it.flete} onChange={e => updateItem(i, 'flete', e.target.value)} />
                        </div>
                        <button type="button" onClick={() => removeItem(i)} disabled={items.length === 1}
                            style={{ padding: '4px 8px', fontSize: 14, background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 4, cursor: 'pointer', marginTop: 14 }}>
                            ✕
                        </button>
                    </div>
                </div>
            ))}
            <button type="button" onClick={addItem}
                style={{ fontSize: 12, padding: '4px 10px', background: '#f0fdf4', color: '#15803d', border: '1px solid #86efac', borderRadius: 4, cursor: 'pointer', marginBottom: 14 }}>
                + Agregar ítem
            </button>

            {form.errors.items && <p style={{ color: '#dc2626', fontSize: 12 }}>{form.errors.items}</p>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 12 }}>
                <div className="field">
                    <label>Condición de operación</label>
                    <select value={form.data.resumen?.condicionOperacion} onChange={e => form.setData('resumen', { ...form.data.resumen, condicionOperacion: Number(e.target.value) })}>
                        <option value={1}>1 - Contado</option>
                        <option value={2}>2 - Crédito</option>
                        <option value={3}>3 - Otro</option>
                    </select>
                </div>
            </div>

            <div className="field">
                <label>Observaciones</label>
                <textarea rows={3} maxLength={3000} value={form.data.resumen?.observaciones}
                    onChange={e => form.setData('resumen', { ...form.data.resumen, observaciones: e.target.value })}
                    placeholder="Motivo del retorno (opcional)" />
            </div>

            <div className="form-actions">
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button type="submit" disabled={form.processing}
                    style={{ padding: '8px 16px', background: '#15803d', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}>
                    {form.processing ? 'Enviando…' : 'Enviar retorno a Hacienda'}
                </button>
            </div>
        </form>
    );
}

const BLANK_EOE = { tipoOperacion: '01', descripcion: '', monto: 0, observaciones: '' };

function EOEForm({ onClose }) {
    const [detalle, setDetalle] = useState([{ ...BLANK_EOE }]);
    const form = useForm({
        detalle,
        resumen: { condicionOperacion: 1, observaciones: '' },
        receptor: { nombre: '', codPais: '' },
    });

    function updateDetalle(i, field, value) {
        const next = detalle.map((it, idx) => idx === i ? { ...it, [field]: value } : it);
        setDetalle(next);
        form.setData('detalle', next);
    }
    function addDetalle() {
        const next = [...detalle, { ...BLANK_EOE }];
        setDetalle(next);
        form.setData('detalle', next);
    }
    function removeDetalle(i) {
        const next = detalle.filter((_, idx) => idx !== i);
        setDetalle(next);
        form.setData('detalle', next);
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.factura-sv.eventos.eoe'), { onSuccess: onClose });
    }

    const inputStyle = { width: '100%', padding: '4px 6px', fontSize: 12, border: '1px solid #d1d5db', borderRadius: 4 };
    const labelStyle = { fontSize: 11, color: '#6b7280', display: 'block', marginBottom: 2 };

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 6, padding: '8px 12px', marginBottom: 14, fontSize: 12, color: '#1e40af' }}>
                El Evento de Operaciones Especiales (EOE) reporta operaciones no representadas en DTEs estándar (servicios especiales, comisiones, etc.). Se enviará firmado a Hacienda.
            </div>

            <p style={{ fontWeight: 600, fontSize: 13, margin: '0 0 8px' }}>Detalle de operaciones</p>
            {detalle.map((it, i) => (
                <div key={i} style={{ border: '1px solid #e5e7eb', borderRadius: 6, padding: 10, marginBottom: 8 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr 1fr auto', gap: 6, alignItems: 'flex-end' }}>
                        <div>
                            <label style={labelStyle}>Tipo operación *</label>
                            <select style={inputStyle} value={it.tipoOperacion} onChange={e => updateDetalle(i, 'tipoOperacion', e.target.value)}>
                                {EOE_TIPO_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.value} — {o.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Descripción *</label>
                            <input style={inputStyle} value={it.descripcion} onChange={e => updateDetalle(i, 'descripcion', e.target.value)} maxLength={1000} required />
                        </div>
                        <div>
                            <label style={labelStyle}>Monto *</label>
                            <input style={inputStyle} type="number" min="0" step="0.01" value={it.monto} onChange={e => updateDetalle(i, 'monto', e.target.value)} required />
                        </div>
                        <button type="button" onClick={() => removeDetalle(i)} disabled={detalle.length === 1}
                            style={{ padding: '4px 8px', fontSize: 14, background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 4, cursor: 'pointer', marginTop: 14 }}>
                            ✕
                        </button>
                    </div>
                    <div style={{ marginTop: 6 }}>
                        <label style={labelStyle}>Observaciones</label>
                        <textarea style={{ ...inputStyle, resize: 'vertical' }} rows={2} value={it.observaciones} maxLength={3000}
                            onChange={e => updateDetalle(i, 'observaciones', e.target.value)} />
                    </div>
                </div>
            ))}
            <button type="button" onClick={addDetalle}
                style={{ fontSize: 12, padding: '4px 10px', background: '#eff6ff', color: '#1d4ed8', border: '1px solid #93c5fd', borderRadius: 4, cursor: 'pointer', marginBottom: 14 }}>
                + Agregar operación
            </button>

            {form.errors.detalle && <p style={{ color: '#dc2626', fontSize: 12 }}>{form.errors.detalle}</p>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 12 }}>
                <div className="field">
                    <label>Condición de operación</label>
                    <select value={form.data.resumen?.condicionOperacion}
                        onChange={e => form.setData('resumen', { ...form.data.resumen, condicionOperacion: Number(e.target.value) })}>
                        <option value={1}>1 - Contado</option>
                        <option value={2}>2 - Crédito</option>
                        <option value={3}>3 - Otro</option>
                    </select>
                </div>
            </div>

            <div className="field">
                <label>Observaciones del resumen</label>
                <textarea rows={2} maxLength={3000} value={form.data.resumen?.observaciones}
                    onChange={e => form.setData('resumen', { ...form.data.resumen, observaciones: e.target.value })} />
            </div>

            <div className="form-actions">
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button type="submit" disabled={form.processing}
                    style={{ padding: '8px 16px', background: '#1d4ed8', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}>
                    {form.processing ? 'Enviando…' : 'Enviar EOE a Hacienda'}
                </button>
            </div>
        </form>
    );
}

function ContingenciaForm({ contingenciaInvoices, onClose }) {
    const today = new Date().toISOString().slice(0, 10);
    const [selectedIds, setSelectedIds] = useState(contingenciaInvoices.map(i => i.id));

    const form = useForm({
        invoiceIds: selectedIds,
        emisor: { nombreResponsable: '', tipoDocResponsable: '36', numeroDocResponsable: '' },
        motivo: { tipoContingencia: 1, motivoContingencia: '', fInicio: today, hInicio: '', fFin: today, hFin: '' },
    });

    function toggleInvoice(id) {
        const next = selectedIds.includes(id) ? selectedIds.filter(x => x !== id) : [...selectedIds, id];
        setSelectedIds(next);
        form.setData('invoiceIds', next);
    }
    function setMotivo(field, value) {
        form.setData('motivo', { ...form.data.motivo, [field]: value });
    }
    function setEmisor(field, value) {
        form.setData('emisor', { ...form.data.emisor, [field]: value });
    }
    function handleTimeChange(field, value) {
        setMotivo(field, value ? value + ':00' : '');
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.factura-sv.facturas.contingencia'), { onSuccess: onClose });
    }

    const inputStyle = { width: '100%', padding: '4px 6px', fontSize: 12, border: '1px solid #d1d5db', borderRadius: 4 };
    const labelStyle = { fontSize: 11, color: '#6b7280', display: 'block', marginBottom: 2 };
    const requiresMotivo = form.data.motivo?.tipoContingencia === 5;

    if (contingenciaInvoices.length === 0) {
        return (
            <div>
                <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 6, padding: '12px 16px', fontSize: 13, color: '#c2410c' }}>
                    No hay facturas en estado CONTINGENCIA con documento firmado. Marque las facturas como contingencia (botón en la columna de acciones) antes de usar esta función.
                </div>
                <div className="form-actions" style={{ marginTop: 16 }}>
                    <button type="button" className="btn secondary" onClick={onClose}>Cerrar</button>
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: 6, padding: '8px 12px', marginBottom: 14, fontSize: 12, color: '#c2410c' }}>
                Envía el lote de DTEs firmados en contingencia y el evento de contingencia a Hacienda. Selecciona las facturas a incluir en el lote.
            </div>

            <p style={{ fontWeight: 600, fontSize: 13, margin: '0 0 6px' }}>
                Facturas en contingencia — selecciona las que deseas enviar
            </p>
            <div style={{ maxHeight: 180, overflowY: 'auto', border: '1px solid #e5e7eb', borderRadius: 6, padding: 8, marginBottom: 14 }}>
                {contingenciaInvoices.map(inv => (
                    <label key={inv.id} style={{ display: 'flex', gap: 8, alignItems: 'center', padding: '4px 0', fontSize: 12, cursor: 'pointer' }}>
                        <input type="checkbox" checked={selectedIds.includes(inv.id)} onChange={() => toggleInvoice(inv.id)} />
                        <span style={{ fontFamily: 'monospace' }}>{inv.numberControl}</span>
                        <span style={{ color: '#6b7280' }}>{inv.customerName}</span>
                        <span style={{ color: '#9ca3af', fontSize: 11 }}>{inv.date}</span>
                    </label>
                ))}
            </div>

            <p style={{ fontWeight: 600, fontSize: 13, margin: '0 0 8px' }}>Responsable del establecimiento</p>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gap: 8, marginBottom: 14 }}>
                <div>
                    <label style={labelStyle}>Nombre responsable *</label>
                    <input style={inputStyle} value={form.data.emisor?.nombreResponsable}
                        onChange={e => setEmisor('nombreResponsable', e.target.value)} minLength={5} maxLength={100} required />
                    {form.errors['emisor.nombreResponsable'] && <p style={{ color: '#dc2626', fontSize: 11, margin: '2px 0 0' }}>{form.errors['emisor.nombreResponsable']}</p>}
                </div>
                <div>
                    <label style={labelStyle}>Tipo documento</label>
                    <select style={inputStyle} value={form.data.emisor?.tipoDocResponsable}
                        onChange={e => setEmisor('tipoDocResponsable', e.target.value)}>
                        <option value="36">36 — NIT</option>
                        <option value="13">13 — DUI</option>
                        <option value="02">02 — Residente</option>
                        <option value="03">03 — Pasaporte</option>
                        <option value="37">37 — Otro</option>
                    </select>
                </div>
                <div>
                    <label style={labelStyle}>No. documento *</label>
                    <input style={inputStyle} value={form.data.emisor?.numeroDocResponsable}
                        onChange={e => setEmisor('numeroDocResponsable', e.target.value)} minLength={3} maxLength={20} required />
                    {form.errors['emisor.numeroDocResponsable'] && <p style={{ color: '#dc2626', fontSize: 11, margin: '2px 0 0' }}>{form.errors['emisor.numeroDocResponsable']}</p>}
                </div>
            </div>

            <p style={{ fontWeight: 600, fontSize: 13, margin: '0 0 8px' }}>Motivo de contingencia</p>
            <div className="field" style={{ marginBottom: 10 }}>
                <label>Tipo CAT-014</label>
                <select value={form.data.motivo?.tipoContingencia}
                    onChange={e => setMotivo('tipoContingencia', Number(e.target.value))}>
                    {CONTINGENCIA_TIPO_OPTIONS.map(o => (
                        <option key={o.value} value={o.value}>{o.value} — {o.label}</option>
                    ))}
                </select>
            </div>
            {requiresMotivo && (
                <div className="field" style={{ marginBottom: 10 }}>
                    <label>Descripción del motivo *</label>
                    <textarea rows={2} maxLength={500} value={form.data.motivo?.motivoContingencia}
                        onChange={e => setMotivo('motivoContingencia', e.target.value)} required />
                </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: 8, marginBottom: 14 }}>
                <div>
                    <label style={labelStyle}>Fecha inicio *</label>
                    <input style={inputStyle} type="date" value={form.data.motivo?.fInicio}
                        onChange={e => setMotivo('fInicio', e.target.value)} required />
                </div>
                <div>
                    <label style={labelStyle}>Hora inicio *</label>
                    <input style={inputStyle} type="time" step="1"
                        onChange={e => handleTimeChange('hInicio', e.target.value)} required />
                </div>
                <div>
                    <label style={labelStyle}>Fecha fin *</label>
                    <input style={inputStyle} type="date" value={form.data.motivo?.fFin}
                        onChange={e => setMotivo('fFin', e.target.value)} required />
                </div>
                <div>
                    <label style={labelStyle}>Hora fin *</label>
                    <input style={inputStyle} type="time" step="1"
                        onChange={e => handleTimeChange('hFin', e.target.value)} required />
                </div>
            </div>

            {form.errors.invoiceIds && <p style={{ color: '#dc2626', fontSize: 12 }}>{form.errors.invoiceIds}</p>}

            <div className="form-actions">
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button type="submit" disabled={form.processing || selectedIds.length === 0}
                    style={{ padding: '8px 16px', background: '#c2410c', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}>
                    {form.processing ? 'Enviando…' : `Enviar contingencia (${selectedIds.length} factura${selectedIds.length !== 1 ? 's' : ''})`}
                </button>
            </div>
        </form>
    );
}

export default function Facturas({ stats, invoices, filters, contingenciaInvoices = [] }) {
    const [search, setSearch]         = useState(filters?.search ?? '');
    const [from, setFrom]             = useState(filters?.from ?? '');
    const [to, setTo]                 = useState(filters?.to ?? '');
    const [status, setStatus]         = useState(filters?.status ?? '');
    const [type, setType]             = useState(filters?.type ?? '');
    const [detailInv, setDetailInv]   = useState(null);
    const [voidInv, setVoidInv]       = useState(null);
    const [returnInv, setReturnInv]   = useState(null);
    const [showEOE, setShowEOE]       = useState(false);
    const [showContingencia, setShowContingencia] = useState(false);
    const [sending, setSending]       = useState(null);
    const [markingContingencia, setMarkingContingencia] = useState(null);

    function handleFilter(e) {
        e.preventDefault();
        router.get(
            route('admin.factura-sv.billing.facturas'),
            { search, from, to, status, type },
            { preserveState: true }
        );
    }

    function clearFilters() {
        setSearch(''); setFrom(''); setTo(''); setStatus(''); setType('');
        router.get(route('admin.factura-sv.billing.facturas'));
    }

    function handleSend(invoice) {
        if (!confirm(`¿Enviar factura ${invoice.numberControl} a Hacienda? Esta acción no se puede deshacer.`)) return;
        setSending(invoice.id);
        router.post(
            route('admin.factura-sv.facturas.enviar', invoice.id),
            {},
            { onFinish: () => setSending(null) }
        );
    }

    function handleMarkContingencia(invoice) {
        if (!confirm(`¿Marcar la factura ${invoice.numberControl} como CONTINGENCIA?\n\nPodrá enviarla en lote cuando Hacienda esté disponible.`)) return;
        setMarkingContingencia(invoice.id);
        router.post(
            route('admin.factura-sv.facturas.marcar-contingencia', invoice.id),
            {},
            { onFinish: () => setMarkingContingencia(null) }
        );
    }

    const hasFilter = !!(filters?.search || filters?.from || filters?.to || filters?.status || filters?.type);

    return (
        <AppLayout>
            <Head title="Facturas electrónicas" />

            <div className="top">
                <h1>Facturas electrónicas</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button
                        type="button"
                        onClick={() => setShowContingencia(true)}
                        style={{ padding: '7px 14px', background: contingenciaInvoices.length > 0 ? '#fff7ed' : '#f9fafb', color: contingenciaInvoices.length > 0 ? '#c2410c' : '#6b7280', border: `1px solid ${contingenciaInvoices.length > 0 ? '#fed7aa' : '#e5e7eb'}`, borderRadius: 6, cursor: 'pointer', fontWeight: 600, fontSize: 13 }}
                    >
                        Contingencia{contingenciaInvoices.length > 0 ? ` (${contingenciaInvoices.length})` : ''}
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowEOE(true)}
                        style={{ padding: '7px 14px', background: '#eff6ff', color: '#1d4ed8', border: '1px solid #bfdbfe', borderRadius: 6, cursor: 'pointer', fontWeight: 600, fontSize: 13 }}
                    >
                        EOE
                    </button>
                    <a href={route('admin.factura-sv', { view: 'nueva-factura' })} className="btn">
                        Nueva Factura
                    </a>
                </div>
            </div>

            {/* Stats */}
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 16 }}>
                <StatCard label="Facturas hoy" value={stats.todayCount} />
                <StatCard label="Total enviado hoy" value={`$${fmt(stats.todaySentTotal)}`} mono />
                <StatCard label="Enviadas a Hacienda" value={stats.sentCount} />
                <StatCard label="Pendientes" value={stats.pendingCount} />
                <StatCard label="Anuladas" value={stats.voidedCount} />
            </div>

            {/* Filters */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleFilter} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '2 1 200px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Factura, cliente, sello o estado…"
                        />
                    </label>
                    <label style={{ flex: '1 1 130px', margin: 0 }}>
                        Desde
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </label>
                    <label style={{ flex: '1 1 130px', margin: 0 }}>
                        Hasta
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </label>
                    <label style={{ flex: '1 1 150px', margin: 0 }}>
                        Estado
                        <select value={status} onChange={(e) => setStatus(e.target.value)}>
                            <option value="">Todos los estados</option>
                            <option value="PENDIENTE">Pendiente / Acción</option>
                            <option value="CONTINGENCIA">Contingencia</option>
                            <option value="ENVIADO">Enviado</option>
                            <option value="RECHAZADO">Rechazado</option>
                            <option value="ANULADO">Anulado</option>
                        </select>
                    </label>
                    <label style={{ flex: '1 1 150px', margin: 0 }}>
                        Documento
                        <select value={type} onChange={(e) => setType(e.target.value)}>
                            <option value="">Todos los documentos</option>
                            {DTE_TYPES.map((t) => (
                                <option key={t.code} value={t.code}>{t.code} - {t.label}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" className="btn">Filtrar</button>
                        {hasFilter && (
                            <button type="button" className="btn secondary" onClick={clearFilters}>
                                Limpiar
                            </button>
                        )}
                    </div>
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>No. Control</th>
                            <th>Cliente</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Estado</th>
                            <th>Error</th>
                            <th>Aceptado</th>
                            <th>Correo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="empty">No hay facturas</td>
                            </tr>
                        )}
                        {invoices.data.map((inv) => (
                            <tr key={inv.id}>
                                <td style={{ fontSize: 13 }}>{inv.date ?? '—'}</td>
                                <td>
                                    <div style={{ fontFamily: 'monospace', fontSize: 12 }}>{inv.numberControl}</div>
                                    {inv.generationCode && (
                                        <div style={{ fontFamily: 'monospace', fontSize: 10, color: '#9ca3af' }}>
                                            {inv.generationCode.substring(0, 8)}…
                                        </div>
                                    )}
                                </td>
                                <td style={{ fontWeight: 500 }}>{inv.customerName}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>
                                    ${fmt(inv.total)}
                                </td>
                                <td><StatusBadge status={inv.status} /></td>
                                <td style={{ fontSize: 12 }}>
                                    {inv.hasError
                                        ? <span style={{ color: '#dc2626', fontWeight: 600 }}>Sí</span>
                                        : <span className="muted">No</span>}
                                </td>
                                <td style={{ fontSize: 12 }}>
                                    {inv.accepted
                                        ? <span style={{ color: '#15803d' }}>Sí</span>
                                        : <span className="muted">No</span>}
                                </td>
                                <td style={{ fontSize: 12 }}>
                                    {inv.emailSent
                                        ? <span style={{ color: '#15803d' }}>Sí</span>
                                        : <span className="muted">No</span>}
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                                        <button
                                            type="button"
                                            className="btn secondary"
                                            style={{ fontSize: 11, padding: '2px 8px' }}
                                            onClick={() => setDetailInv(inv)}
                                        >
                                            Ver
                                        </button>
                                        {['FIRMADO', 'PENDIENTE'].includes(inv.status) && (
                                            <button
                                                type="button"
                                                className="btn"
                                                style={{ fontSize: 11, padding: '2px 8px' }}
                                                disabled={sending === inv.id}
                                                onClick={() => handleSend(inv)}
                                            >
                                                {sending === inv.id ? '…' : 'Enviar'}
                                            </button>
                                        )}
                                        {canMarkContingencia(inv) && (
                                            <button
                                                type="button"
                                                style={{ fontSize: 11, padding: '2px 8px', background: '#fff7ed', color: '#c2410c', border: '1px solid #fed7aa', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                                                disabled={markingContingencia === inv.id}
                                                onClick={() => handleMarkContingencia(inv)}
                                            >
                                                {markingContingencia === inv.id ? '…' : 'Contingencia'}
                                            </button>
                                        )}
                                        {canCorrectInvoice(inv) && (
                                            <a
                                                href={route('admin.factura-sv.billing.editar-factura', inv.id)}
                                                style={{ fontSize: 11, padding: '2px 8px', background: '#fefce8', color: '#854d0e', border: '1px solid #fde68a', borderRadius: 6, cursor: 'pointer', fontWeight: 600, textDecoration: 'none', display: 'inline-block' }}
                                            >
                                                Corregir
                                            </a>
                                        )}
                                        {canVoidInvoice(inv) && (
                                            <button
                                                type="button"
                                                style={{ fontSize: 11, padding: '2px 8px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                                                onClick={() => setVoidInv(inv)}
                                            >
                                                Anular
                                            </button>
                                        )}
                                        {canReturnInvoice(inv) && (
                                            <button
                                                type="button"
                                                style={{ fontSize: 11, padding: '2px 8px', background: '#f0fdf4', color: '#15803d', border: '1px solid #86efac', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                                                onClick={() => setReturnInv(inv)}
                                            >
                                                Retorno
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={invoices.links} />
            </div>

            {detailInv && (
                <Modal
                    title={`Factura ${detailInv.numberControl}`}
                    onClose={() => setDetailInv(null)}
                    maxWidth={560}
                >
                    <DetailModal invoice={detailInv} onClose={() => setDetailInv(null)} />
                </Modal>
            )}

            {voidInv && (
                <Modal
                    title={`Anular ${voidInv.numberControl}`}
                    onClose={() => setVoidInv(null)}
                    maxWidth={520}
                >
                    <VoidForm invoice={voidInv} onClose={() => setVoidInv(null)} />
                </Modal>
            )}

            {returnInv && (
                <Modal
                    title={`Evento de Retorno — ${returnInv.numberControl}`}
                    onClose={() => setReturnInv(null)}
                    maxWidth={720}
                >
                    <ReturnForm invoice={returnInv} onClose={() => setReturnInv(null)} />
                </Modal>
            )}

            {showEOE && (
                <Modal
                    title="Evento de Operaciones Especiales (EOE)"
                    onClose={() => setShowEOE(false)}
                    maxWidth={680}
                >
                    <EOEForm onClose={() => setShowEOE(false)} />
                </Modal>
            )}

            {showContingencia && (
                <Modal
                    title="Enviar Lote de Contingencia"
                    onClose={() => setShowContingencia(false)}
                    maxWidth={700}
                >
                    <ContingenciaForm
                        contingenciaInvoices={contingenciaInvoices}
                        onClose={() => setShowContingencia(false)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
