import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

const DTE_TYPES = [
    { code: '01', label: 'Factura' },
    { code: '03', label: 'CCF' },
    { code: '05', label: 'Nota de Crédito' },
    { code: '06', label: 'Nota de Débito' },
    { code: '07', label: 'Comp. Retención' },
    { code: '11', label: 'Factura Exportación' },
    { code: '14', label: 'Sujeto Excluido' },
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

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

// ── void window helpers (mirrors Vue logic) ──────────────────────────────────
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

export default function Facturas({ stats, invoices, filters }) {
    const [search, setSearch]     = useState(filters?.search ?? '');
    const [from, setFrom]         = useState(filters?.from ?? '');
    const [to, setTo]             = useState(filters?.to ?? '');
    const [status, setStatus]     = useState(filters?.status ?? '');
    const [type, setType]         = useState(filters?.type ?? '');
    const [detailInv, setDetailInv] = useState(null);
    const [voidInv, setVoidInv]     = useState(null);
    const [sending, setSending]     = useState(null);

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

    const hasFilter = !!(filters?.search || filters?.from || filters?.to || filters?.status || filters?.type);

    return (
        <AppLayout>
            <Head title="Facturas electrónicas" />

            <div className="top">
                <h1>Facturas electrónicas</h1>
                <a href={route('admin.factura-sv', { view: 'nueva-factura' })} className="btn">
                    Nueva Factura
                </a>
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
                                    <div style={{ display: 'flex', gap: 4 }}>
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
                                        {canVoidInvoice(inv) && (
                                            <button
                                                type="button"
                                                style={{ fontSize: 11, padding: '2px 8px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                                                onClick={() => setVoidInv(inv)}
                                            >
                                                Anular
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
        </AppLayout>
    );
}
