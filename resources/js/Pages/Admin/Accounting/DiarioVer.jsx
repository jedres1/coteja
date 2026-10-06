import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = { borrador: 'Borrador', aprobado: 'Aprobado', anulado: 'Anulado' };
const STATUS_STYLE  = {
    borrador: { background: '#fef3c7', color: '#92400e' },
    aprobado: { background: '#f0fdf4', color: '#15803d' },
    anulado:  { background: '#f1f5f9', color: '#64748b' },
};

export default function DiarioVer({ entry }) {
    const [annulModal, setAnnulModal] = useState(false);
    const [motivo, setMotivo]         = useState('');
    const [submitting, setSubmitting] = useState(false);

    function handleApprove() {
        if (!confirm('¿Aprobar este asiento contable?')) return;
        router.post(route('admin.accounting.diario.approve', entry.id));
    }

    function handleAnnul(e) {
        e.preventDefault();
        if (!motivo.trim() || motivo.trim().length < 5) return;
        setSubmitting(true);
        router.post(route('admin.accounting.diario.annul', entry.id), { motivo }, {
            onFinish: () => { setSubmitting(false); setAnnulModal(false); },
        });
    }

    const totalDebit  = (entry.lines ?? []).reduce((s, l) => s + Number(l.debit ?? 0),  0);
    const totalCredit = (entry.lines ?? []).reduce((s, l) => s + Number(l.credit ?? 0), 0);
    const balanced    = Math.abs(totalDebit - totalCredit) < 0.01;

    const entryDate = entry.entry_date
        ? String(entry.entry_date).substring(0, 10)
        : '—';

    return (
        <AppLayout>
            <Head title={`Asiento ${entry.entry_number}`} />

            <div className="top">
                <div>
                    <h1 style={{ marginBottom: 4 }}>Asiento {entry.entry_number}</h1>
                    <span style={{ fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(STATUS_STYLE[entry.status] ?? {}) }}>
                        {STATUS_LABELS[entry.status] ?? entry.status}
                    </span>
                </div>
                <Link href={route('admin.accounting.diario.index')} className="btn secondary">
                    ← Volver
                </Link>
            </div>

            {/* Detalle de cabecera */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px 24px' }}>
                    <div>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Fecha</p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{entryDate}</p>
                    </div>
                    <div>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Referencia</p>
                        <p style={{ margin: 0, fontFamily: 'monospace' }}>{entry.reference ?? '—'}</p>
                    </div>
                    <div>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Descripción</p>
                        <p style={{ margin: 0 }}>{entry.description || '—'}</p>
                    </div>
                    <div>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Total débito</p>
                        <p style={{ margin: 0, fontWeight: 600, fontFamily: 'monospace' }}>{fmt(totalDebit)}</p>
                    </div>
                    <div>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Total crédito</p>
                        <p style={{ margin: 0, fontWeight: 600, fontFamily: 'monospace' }}>{fmt(totalCredit)}</p>
                    </div>
                    {!balanced && (
                        <div>
                            <p style={{ margin: 0, color: '#991b1b', fontWeight: 600, fontSize: 13 }}>⚠ Partida desbalanceada</p>
                        </div>
                    )}
                </div>
                {entry.notes && (
                    <div style={{ marginTop: 12, paddingTop: 12, borderTop: '1px solid #e5e7eb' }}>
                        <p style={{ margin: '0 0 2px', fontSize: 12, color: '#6b7280' }}>Notas</p>
                        <p style={{ margin: 0, fontSize: 13 }}>{entry.notes}</p>
                    </div>
                )}
            </div>

            {/* Líneas */}
            <div className="card table-scroll" style={{ marginBottom: 16 }}>
                <table>
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <th>Descripción</th>
                            <th>Centro de costo</th>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(entry.lines ?? []).length === 0 && (
                            <tr><td colSpan={5} className="empty">Sin líneas</td></tr>
                        )}
                        {(entry.lines ?? []).map((line) => (
                            <tr key={line.id}>
                                <td>
                                    <span style={{ fontFamily: 'monospace', fontSize: 12, marginRight: 6 }}>
                                        {line.account?.code}
                                    </span>
                                    {line.account?.name}
                                </td>
                                <td style={{ fontSize: 13 }}>{line.description || <span className="muted">—</span>}</td>
                                <td style={{ fontSize: 13 }}>
                                    {line.cost_center?.name ?? <span className="muted">—</span>}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {Number(line.debit) > 0 ? fmt(line.debit) : <span className="muted">—</span>}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {Number(line.credit) > 0 ? fmt(line.credit) : <span className="muted">—</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                            <td colSpan={3}>Totales</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalDebit)}</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalCredit)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* Acciones */}
            {entry.status === 'borrador' && (
                <div style={{ display: 'flex', gap: 10 }}>
                    <button type="button" className="btn" onClick={handleApprove}>
                        Aprobar asiento
                    </button>
                    <button
                        type="button"
                        style={{ padding: '8px 16px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                        onClick={() => setAnnulModal(true)}
                    >
                        Anular asiento
                    </button>
                </div>
            )}

            {entry.status === 'aprobado' && (
                <div style={{ display: 'flex', gap: 10 }}>
                    <button
                        type="button"
                        style={{ padding: '8px 16px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                        onClick={() => setAnnulModal(true)}
                    >
                        Anular asiento
                    </button>
                </div>
            )}

            {/* Modal anulación */}
            {annulModal && (
                <div className="overlay-layer" role="dialog" aria-modal="true">
                    <button className="overlay-backdrop" type="button" aria-label="Cerrar" onClick={() => setAnnulModal(false)} />
                    <div className="overlay-panel card" style={{ maxWidth: 460 }}>
                        <div className="overlay-header">
                            <h3 style={{ margin: 0 }}>Anular asiento {entry.entry_number}</h3>
                            <button type="button" className="btn secondary" onClick={() => setAnnulModal(false)}>✕</button>
                        </div>
                        <p style={{ fontSize: 13, color: '#6b7280', marginBottom: 12 }}>
                            Se creará un asiento de reversión automático. Ingrese el motivo de anulación.
                        </p>
                        <form onSubmit={handleAnnul}>
                            <label style={{ margin: 0 }}>
                                Motivo <span style={{ color: '#ef4444' }}>*</span>
                                <textarea
                                    value={motivo}
                                    onChange={(e) => setMotivo(e.target.value)}
                                    rows={3}
                                    minLength={5}
                                    maxLength={300}
                                    required
                                    style={{ width: '100%', resize: 'vertical', fontFamily: 'inherit', fontSize: 13 }}
                                    placeholder="Mínimo 5 caracteres…"
                                />
                            </label>
                            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                                <button type="button" className="btn secondary" onClick={() => setAnnulModal(false)}>Cancelar</button>
                                <button
                                    type="submit"
                                    disabled={submitting || motivo.trim().length < 5}
                                    style={{ padding: '8px 16px', background: '#dc2626', color: '#fff', border: 'none', borderRadius: 6, cursor: 'pointer', fontWeight: 600 }}
                                >
                                    {submitting ? 'Anulando…' : 'Confirmar anulación'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
