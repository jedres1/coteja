import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const STATUS_LABEL = { open: 'Abierta', completado: 'Completada' };
const STATUS_BADGE = { open: 'suspended', completado: 'active' };
const TYPE_LABEL   = { credit: 'Crédito', debit: 'Débito', deposito: 'Depósito', pago: 'Pago', transferencia: 'Transferencia', cheque: 'Cheque', otro: 'Otro' };

const fmt = (n) =>
    n != null
        ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '—';

export default function ReconciliationDetail({ reconciliation, transactions }) {
    const [uploading, setUploading] = useState(false);

    const isOpen    = reconciliation.status === 'open';
    const periodStr = MONTHS[(reconciliation.period_month ?? 1) - 1] + ' ' + reconciliation.period_year;

    function handleToggle(rawId, source, currentlyReconciled) {
        router.post(route('admin.bank-transactions.reconciliations.toggle', reconciliation.id), {
            tx_id:      rawId,
            tx_source:  source,
            reconciled: !currentlyReconciled,
        });
    }

    function handleComplete() {
        if (!confirm('¿Marcar esta conciliación como completada? Esta acción no se puede revertir.')) return;
        router.post(route('admin.bank-transactions.reconciliations.complete', reconciliation.id));
    }

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

    return (
        <AppLayout>
            <Head title="Detalle de conciliación" />

            <div className="top">
                <div>
                    <Link
                        href={route('admin.bank-transactions.reconciliations')}
                        className="btn secondary"
                        style={{ marginBottom: 8 }}
                    >
                        ← Volver
                    </Link>
                    <h1>Detalle de conciliación</h1>
                </div>
                {isOpen && (
                    <button className="btn" type="button" onClick={handleComplete}>
                        Completar conciliación
                    </button>
                )}
            </div>

            {/* Summary card */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16 }}>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Cuenta</p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{reconciliation.bank_account?.name ?? '—'}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Período</p>
                        <p style={{ margin: 0 }}>{periodStr}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Estado</p>
                        <span className={`badge ${STATUS_BADGE[reconciliation.status] ?? ''}`}>
                            {STATUS_LABEL[reconciliation.status] ?? reconciliation.status}
                        </span>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Saldo estado de cuenta</p>
                        <p style={{ margin: 0, fontWeight: 600, fontFamily: 'monospace' }}>{fmt(reconciliation.statement_balance)}</p>
                    </div>
                    {reconciliation.notes && (
                        <div style={{ gridColumn: '1 / -1' }}>
                            <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Notas</p>
                            <p style={{ margin: 0, fontSize: 13 }}>{reconciliation.notes}</p>
                        </div>
                    )}
                </div>
            </div>

            {/* File upload (only when open) */}
            {isOpen && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3 style={{ marginTop: 0 }}>Cargar estado de cuenta</h3>
                    <form onSubmit={handleUpload} encType="multipart/form-data" style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <label style={{ flex: '1 1 280px', margin: 0 }}>
                            Archivo (PDF / JPG / PNG)
                            <input type="file" name="statement_file" accept=".pdf,.jpg,.jpeg,.png" required />
                        </label>
                        <label style={{ margin: 0 }}>
                            Saldo del estado
                            <input type="number" name="statement_balance" step="0.01" placeholder="0.00" />
                        </label>
                        <button type="submit" className="btn" disabled={uploading}>
                            {uploading ? 'Cargando…' : 'Cargar archivo'}
                        </button>
                    </form>
                </div>
            )}

            {/* Transactions table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción / Referencia</th>
                            <th>Origen</th>
                            <th>Tipo</th>
                            <th style={{ textAlign: 'right' }}>Monto</th>
                            <th>Conciliado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(transactions ?? []).length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin transacciones en este período</td>
                            </tr>
                        )}
                        {(transactions ?? []).map((t) => (
                            <tr key={`${t.source}_${t.raw_id}`}>
                                <td className="muted" style={{ whiteSpace: 'nowrap' }}>{t.date}</td>
                                <td>
                                    <div>{t.document_label && <span style={{ fontSize: 11, color: '#6b7280', marginRight: 4 }}>[{t.document_label}]</span>}{t.document}</div>
                                    {t.reference && <div style={{ fontSize: 12, color: '#6b7280' }}>{t.reference}</div>}
                                </td>
                                <td style={{ fontSize: 12 }}>{t.source}</td>
                                <td>{TYPE_LABEL[t.type] ?? t.type}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600, color: t.type === 'debit' || t.type === 'pago' ? '#dc2626' : '#16a34a' }}>
                                    {fmt(t.amount)}
                                </td>
                                <td>
                                    {isOpen ? (
                                        <button
                                            type="button"
                                            className={`btn ${t.is_reconciled ? '' : 'secondary'}`}
                                            style={{ fontSize: 12, padding: '4px 10px' }}
                                            onClick={() => handleToggle(t.raw_id, t.source, t.is_reconciled)}
                                        >
                                            {t.is_reconciled ? 'Conciliado ✓' : 'Marcar'}
                                        </button>
                                    ) : (
                                        <span className={`badge ${t.is_reconciled ? 'active' : 'suspended'}`}>
                                            {t.is_reconciled ? 'Conciliado' : 'Pendiente'}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
