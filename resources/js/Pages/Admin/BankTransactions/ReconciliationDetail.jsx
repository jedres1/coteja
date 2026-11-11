import { useState, useCallback } from 'react';
import { Head, router, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

// ─── Constants ─────────────────────────────────────────────────────────────────

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const STATUS_LABEL = { abierta: 'Abierta', en_firme: 'En firme', anulada: 'Anulada' };
const STATUS_BADGE = { abierta: 'suspended', en_firme: 'active', anulada: '' };

const TYPE_LABEL = {
    deposito: 'Depósito', pago: 'Pago', transferencia: 'Transferencia',
    transferencia_entrada: 'Transferencia entrada', transferencia_salida: 'Transferencia salida',
    cheque: 'Cheque', nota_debito: 'Nota débito', nota_credito: 'Nota crédito',
    ajuste_apertura: 'Ajuste apertura', reversion: 'Reversión', otro: 'Otro',
};

const fmt = (n) =>
    n != null ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';

const fmtSigned = (n) => {
    if (n == null) return '—';
    const v = Number(n);
    return (v >= 0 ? '+' : '') + v.toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ─── Summary panel ──────────────────────────────────────────────────────────────

function SummaryPanel({ summary, loadingSummary }) {
    const diff    = summary?.difference ?? 0;
    const ok      = summary?.balances ?? false;
    const diffColor = ok ? '#16a34a' : '#dc2626';

    return (
        <div className="card" style={{ marginBottom: 16 }}>
            <h3 style={{ margin: '0 0 14px', fontSize: 14, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', color: '#6b7280' }}>
                Resumen de conciliación {loadingSummary && <span style={{ fontSize: 11, fontWeight: 400, color: '#9ca3af' }}>actualizando…</span>}
            </h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 2 }}>
                <SummaryRow label="Saldo en libros"           value={fmt(summary?.book_balance)} />
                <SummaryRow label="(−) Salidas en tránsito"  value={fmt(summary?.total_transit_out)} muted />
                <SummaryRow label="(+) Entradas en tránsito" value={fmt(summary?.total_transit_in)} muted />
                <SummaryRow label="Saldo conciliado"         value={fmt(summary?.reconciled_balance)} bold />
                <SummaryRow label="Saldo según banco"        value={fmt(summary?.statement_balance)} bold />
                <div style={{ padding: '10px 12px', borderRadius: 8, background: ok ? '#f0fdf4' : '#fef2f2', border: `1.5px solid ${diffColor}30` }}>
                    <p style={{ margin: '0 0 2px', fontSize: 11, textTransform: 'uppercase', color: '#6b7280', letterSpacing: '0.05em' }}>Diferencia</p>
                    <p style={{ margin: 0, fontFamily: 'monospace', fontSize: 20, fontWeight: 700, color: diffColor }}>
                        {fmt(diff)}
                        {ok && <span style={{ marginLeft: 8, fontSize: 16 }}>✓</span>}
                    </p>
                    {!ok && diff != null && (
                        <p style={{ margin: '4px 0 0', fontSize: 11, color: diffColor }}>
                            {summary?.pending_count} movimiento{summary?.pending_count !== 1 ? 's' : ''} pendiente{summary?.pending_count !== 1 ? 's' : ''}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function SummaryRow({ label, value, bold, muted }) {
    return (
        <div style={{ padding: '10px 12px', borderRadius: 8, background: '#f9fafb' }}>
            <p style={{ margin: '0 0 2px', fontSize: 11, textTransform: 'uppercase', color: '#6b7280', letterSpacing: '0.05em' }}>{label}</p>
            <p style={{ margin: 0, fontFamily: 'monospace', fontSize: 15, fontWeight: bold ? 700 : 400, color: muted ? '#9ca3af' : '#111827' }}>{value}</p>
        </div>
    );
}

// ─── Void modal ────────────────────────────────────────────────────────────────

function VoidModal({ reconciliation, onClose }) {
    const { data, setData, post, processing, errors } = useForm({ void_reason: '' });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.reconciliations.void', reconciliation.id), {
            onSuccess: onClose,
        });
    }

    return (
        <Modal title="Anular conciliación" onClose={onClose} maxWidth={480}>
            <p style={{ color: '#6b7280', fontSize: 13, marginTop: 0 }}>
                Esta acción libera todos los movimientos marcados y deja la conciliación como anulada.
                Solo se puede anular la última conciliación en firme de la cuenta.
            </p>
            <form onSubmit={handleSubmit}>
                <label>
                    Motivo de anulación <span style={{ color: '#ef4444' }}>*</span>
                    <textarea
                        value={data.void_reason}
                        onChange={(e) => setData('void_reason', e.target.value)}
                        rows={3} required minLength={10} maxLength={500}
                        placeholder="Describa el motivo de la anulación…"
                        style={{ resize: 'vertical' }}
                    />
                    {errors.void_reason && <p className="field-error">{errors.void_reason}</p>}
                </label>
                {errors.recon && <p className="field-error">{errors.recon}</p>}
                <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                    <button type="submit" className="btn" style={{ background: '#dc2626' }} disabled={processing}>
                        {processing ? 'Anulando…' : 'Confirmar anulación'}
                    </button>
                    <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                </div>
            </form>
        </Modal>
    );
}

// ─── Main page ─────────────────────────────────────────────────────────────────

export default function ReconciliationDetail({ reconciliation, candidates, billingPayments, initialSummary }) {
    const [summary, setSummary]           = useState(initialSummary);
    const [loadingSummary, setLoading]    = useState(false);
    const [filter, setFilter]             = useState('all');   // all | pending | marked
    const [showVoid, setShowVoid]         = useState(false);
    const [uploading, setUploading]       = useState(false);
    const [marking, setMarking]           = useState(false);

    const isOpen   = reconciliation.status === 'abierta';
    const isFirm   = reconciliation.status === 'en_firme';
    const cutDate  = reconciliation.cut_date ?? '';
    const periodStr = MONTHS[(reconciliation.period_month ?? 1) - 1] + ' ' + reconciliation.period_year;

    // ── Fetch updated summary after mark/unmark ──────────────────────────────

    const refreshSummary = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(
                route('admin.bank-transactions.reconciliations.summary', reconciliation.id),
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const data = await res.json();
            setSummary(data);
        } catch (_) {}
        finally { setLoading(false); }
    }, [reconciliation.id]);

    // ── Mark / unmark individual transaction ────────────────────────────────

    async function handleMark(txId, currentlyMarked) {
        if (marking) return;
        setMarking(true);
        const endpoint = currentlyMarked
            ? route('admin.bank-transactions.reconciliations.unmark', reconciliation.id)
            : route('admin.bank-transactions.reconciliations.mark', reconciliation.id);

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ ids: [txId] }),
            });
            const data = await res.json();
            if (res.ok) {
                setSummary(data);
                // Refresh page data to update candidate list
                router.reload({ only: ['candidates', 'initialSummary'] });
            }
        } catch (_) {}
        finally { setMarking(false); }
    }

    // ── Old toggle (billing payments) ───────────────────────────────────────

    function handleBillingToggle(rawId, currentlyReconciled) {
        router.post(route('admin.bank-transactions.reconciliations.toggle', reconciliation.id), {
            tx_id: rawId, tx_source: 'facturacion', reconciled: !currentlyReconciled,
        });
    }

    // ── Confirm en firme ─────────────────────────────────────────────────────

    function handleConfirm() {
        if (!confirm('¿Poner en firme esta conciliación? No podrá modificarse después.')) return;
        router.post(route('admin.bank-transactions.reconciliations.confirm', reconciliation.id));
    }

    // ── Delete (open only) ───────────────────────────────────────────────────

    function handleDelete() {
        if (!confirm('¿Eliminar esta conciliación? Los movimientos marcados quedarán pendientes.')) return;
        router.delete(route('admin.bank-transactions.reconciliations.destroy', reconciliation.id));
    }

    // ── Upload statement ─────────────────────────────────────────────────────

    function handleUpload(e) {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        setUploading(true);
        router.post(
            route('admin.bank-transactions.reconciliations.upload', reconciliation.id),
            formData,
            { forceFormData: true, onFinish: () => setUploading(false) }
        );
    }

    // ── Filtered candidates ──────────────────────────────────────────────────

    const filtered = (candidates ?? []).filter((t) => {
        if (filter === 'pending') return t.reconciliation_id == null;
        if (filter === 'marked')  return t.reconciliation_id != null;
        return true;
    });

    return (
        <AppLayout>
            <Head title="Detalle de conciliación" />

            {/* Header */}
            <div className="top" style={{ flexWrap: 'wrap', gap: 10 }}>
                <div>
                    <Link href={route('admin.bank-transactions.reconciliations')} className="btn secondary" style={{ marginBottom: 8 }}>
                        ← Volver
                    </Link>
                    <h1 style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                        Conciliación bancaria
                        <span className={`badge ${STATUS_BADGE[reconciliation.status] ?? ''}`}>
                            {STATUS_LABEL[reconciliation.status] ?? reconciliation.status}
                        </span>
                        {reconciliation.is_opening && (
                            <span style={{ fontSize: 11, background: '#e0f2fe', color: '#0369a1', padding: '2px 8px', borderRadius: 10, fontWeight: 600 }}>Apertura</span>
                        )}
                    </h1>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {isOpen && (
                        <>
                            <button
                                className="btn" type="button"
                                onClick={handleConfirm}
                                disabled={!summary?.balances}
                                title={!summary?.balances ? 'La diferencia debe ser 0.00 para poner en firme' : ''}
                            >
                                Poner en firme
                            </button>
                            <button className="btn secondary" type="button" onClick={handleDelete}
                                style={{ color: '#dc2626', borderColor: '#fca5a5' }}>
                                Eliminar
                            </button>
                        </>
                    )}
                    {isFirm && (
                        <button className="btn secondary" type="button" onClick={() => setShowVoid(true)}
                            style={{ color: '#dc2626', borderColor: '#fca5a5' }}>
                            Anular
                        </button>
                    )}
                </div>
            </div>

            {/* Info row */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 16 }}>
                    <InfoItem label="Cuenta"        value={reconciliation.bank_account?.name ?? '—'} />
                    <InfoItem label="Período"       value={periodStr} />
                    <InfoItem label="Fecha de corte" value={cutDate || '—'} />
                    <InfoItem label="Saldo banco"   value={fmt(reconciliation.statement_balance)} mono />
                    {reconciliation.confirmed_at && (
                        <InfoItem label="Puesta en firme" value={reconciliation.confirmed_at.substring(0, 10)} />
                    )}
                    {reconciliation.voided_at && (
                        <InfoItem label="Anulada" value={reconciliation.voided_at.substring(0, 10)} />
                    )}
                    {reconciliation.void_reason && (
                        <div style={{ gridColumn: '1 / -1' }}>
                            <InfoItem label="Motivo anulación" value={reconciliation.void_reason} />
                        </div>
                    )}
                    {reconciliation.notes && (
                        <div style={{ gridColumn: '1 / -1' }}>
                            <InfoItem label="Notas" value={reconciliation.notes} />
                        </div>
                    )}
                </div>
            </div>

            {/* Summary */}
            <SummaryPanel summary={summary} loadingSummary={loadingSummary} />

            {/* Upload statement (open only) */}
            {isOpen && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3 style={{ marginTop: 0, fontSize: 14 }}>Cargar estado de cuenta bancario</h3>
                    <form onSubmit={handleUpload} encType="multipart/form-data"
                        style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <label style={{ flex: '1 1 260px', margin: 0 }}>
                            Archivo (PDF / JPG / PNG)
                            <input type="file" name="statement_file" accept=".pdf,.jpg,.jpeg,.png" required />
                        </label>
                        <label style={{ margin: 0 }}>
                            Saldo según banco
                            <input type="number" name="statement_balance" step="0.01" placeholder="0.00" />
                        </label>
                        <button type="submit" className="btn secondary" disabled={uploading}>
                            {uploading ? 'Cargando…' : 'Cargar archivo'}
                        </button>
                    </form>
                </div>
            )}

            {/* Bank transactions */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, flexWrap: 'wrap', gap: 8 }}>
                    <h3 style={{ margin: 0, fontSize: 14 }}>
                        Movimientos bancarios
                        <span style={{ marginLeft: 8, fontSize: 12, color: '#6b7280', fontWeight: 400 }}>
                            ({(candidates ?? []).length} candidatos)
                        </span>
                    </h3>
                    <div style={{ display: 'flex', gap: 6 }}>
                        {['all','pending','marked'].map((f) => (
                            <button key={f} type="button"
                                className={`btn ${filter === f ? '' : 'secondary'}`}
                                style={{ fontSize: 12, padding: '4px 10px' }}
                                onClick={() => setFilter(f)}
                            >
                                {{ all: 'Todos', pending: 'Pendientes', marked: 'Marcados' }[f]}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Referencia / Notas</th>
                                <th>Origen</th>
                                <th>Tipo</th>
                                <th style={{ textAlign: 'right' }}>Monto</th>
                                <th style={{ textAlign: 'center' }}>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {!filtered.length && (
                                <tr><td colSpan={6} className="empty">Sin movimientos</td></tr>
                            )}
                            {filtered.map((t) => {
                                const isMarked  = t.reconciliation_id != null;
                                const isVoided  = t.transaction_status === 'voided';
                                const isCredit  = Number(t.amount) > 0;
                                return (
                                    <tr key={t.id} style={{ opacity: isVoided ? 0.45 : 1 }}>
                                        <td className="muted" style={{ whiteSpace: 'nowrap' }}>{t.date}</td>
                                        <td>
                                            {t.reference && <div style={{ fontWeight: 500 }}>{t.reference}</div>}
                                            {t.notes    && <div style={{ fontSize: 12, color: '#6b7280' }}>{t.notes}</div>}
                                        </td>
                                        <td style={{ fontSize: 12 }}>{t.source}</td>
                                        <td style={{ fontSize: 13 }}>{TYPE_LABEL[t.type] ?? t.type}</td>
                                        <td style={{
                                            textAlign: 'right', fontFamily: 'monospace', fontWeight: 600,
                                            color: isCredit ? '#16a34a' : '#dc2626',
                                        }}>
                                            {fmtSigned(t.amount)}
                                        </td>
                                        <td style={{ textAlign: 'center' }}>
                                            {isVoided ? (
                                                <span style={{ fontSize: 11, color: '#9ca3af' }}>Anulado</span>
                                            ) : isOpen ? (
                                                <button
                                                    type="button"
                                                    className={`btn ${isMarked ? '' : 'secondary'}`}
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                    disabled={marking}
                                                    onClick={() => handleMark(t.id, isMarked)}
                                                >
                                                    {isMarked ? '✓ Marcado' : 'Marcar'}
                                                </button>
                                            ) : (
                                                <span className={`badge ${isMarked ? 'active' : 'suspended'}`}>
                                                    {isMarked ? 'Conciliado' : 'Pendiente'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Billing payments */}
            {(billingPayments ?? []).length > 0 && (
                <div className="card">
                    <h3 style={{ marginTop: 0, fontSize: 14 }}>Cobros de facturación en el período</h3>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Referencia</th>
                                    <th>Tipo</th>
                                    <th style={{ textAlign: 'right' }}>Monto</th>
                                    <th style={{ textAlign: 'center' }}>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {billingPayments.map((p) => (
                                    <tr key={p.raw_id}>
                                        <td className="muted" style={{ whiteSpace: 'nowrap' }}>{p.date}</td>
                                        <td style={{ fontSize: 13 }}>
                                            {p.document_label && <span style={{ fontSize: 11, color: '#6b7280', marginRight: 4 }}>[{p.document_label}]</span>}
                                            {p.document}
                                        </td>
                                        <td style={{ fontSize: 12, color: '#6b7280' }}>{p.reference ?? '—'}</td>
                                        <td style={{ fontSize: 13 }}>{TYPE_LABEL[p.type] ?? p.type}</td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600, color: '#16a34a' }}>
                                            {fmt(p.amount)}
                                        </td>
                                        <td style={{ textAlign: 'center' }}>
                                            {isOpen ? (
                                                <button
                                                    type="button"
                                                    className={`btn ${p.is_reconciled ? '' : 'secondary'}`}
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                    onClick={() => handleBillingToggle(p.raw_id, p.is_reconciled)}
                                                >
                                                    {p.is_reconciled ? '✓ Marcado' : 'Marcar'}
                                                </button>
                                            ) : (
                                                <span className={`badge ${p.is_reconciled ? 'active' : 'suspended'}`}>
                                                    {p.is_reconciled ? 'Conciliado' : 'Pendiente'}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Void modal */}
            {showVoid && <VoidModal reconciliation={reconciliation} onClose={() => setShowVoid(false)} />}
        </AppLayout>
    );
}

function InfoItem({ label, value, mono }) {
    return (
        <div>
            <p className="muted" style={{ margin: '0 0 3px', fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</p>
            <p style={{ margin: 0, fontWeight: 500, fontFamily: mono ? 'monospace' : undefined }}>{value}</p>
        </div>
    );
}
