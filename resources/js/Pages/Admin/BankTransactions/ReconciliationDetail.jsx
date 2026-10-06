import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ReconciliationDetail({ reconciliation, transactions }) {
    const [uploading, setUploading] = useState(false);

    const isOpen = reconciliation.status === 'open';
    const statusLabel = { open: 'Abierta', completed: 'Completada' };
    const statusBadge = { open: 'suspended', completed: 'active' };
    const typeLabel = { credit: 'Crédito', debit: 'Débito' };

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    function handleToggle(transactionId) {
        router.post(route('admin.bank-transactions.reconciliations.toggle', reconciliation.id), {
            transaction_id: transactionId,
        });
    }

    function handleComplete() {
        if (!confirm('¿Marcar esta conciliación como completada? Esta acción no se puede revertir.')) return;
        router.post(route('admin.bank-transactions.reconciliations.complete', reconciliation.id));
    }

    function handleUpload(e) {
        e.preventDefault();
        const form = e.currentTarget;
        const formData = new FormData(form);
        setUploading(true);
        router.post(
            route('admin.bank-transactions.reconciliations.upload', reconciliation.id),
            formData,
            {
                forceFormData: true,
                onFinish: () => setUploading(false),
            }
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
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Nombre
                        </p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{reconciliation.name}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Cuenta
                        </p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{reconciliation.bank_account?.name}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Período
                        </p>
                        <p style={{ margin: 0 }}>{reconciliation.period_start} — {reconciliation.period_end}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Estado
                        </p>
                        <span className={`badge ${statusBadge[reconciliation.status] ?? ''}`}>
                            {statusLabel[reconciliation.status] ?? reconciliation.status}
                        </span>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Saldo en libros
                        </p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{fmt(reconciliation.book_balance)}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Saldo en estado de cuenta
                        </p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{fmt(reconciliation.statement_balance)}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 4px', fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                            Diferencia
                        </p>
                        <p style={{
                            margin: 0,
                            fontWeight: 700,
                            color: Number(reconciliation.difference) === 0 ? '#16a34a' : '#dc2626',
                        }}>
                            {fmt(reconciliation.difference)}
                        </p>
                    </div>
                </div>
            </div>

            {/* File upload (only when open) */}
            {isOpen && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3 style={{ marginTop: 0 }}>Cargar estado de cuenta</h3>
                    <form onSubmit={handleUpload} encType="multipart/form-data" style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <label style={{ flex: '1 1 280px', margin: 0 }}>
                            Archivo (CSV / Excel / PDF)
                            <input type="file" name="statement" accept=".csv,.xlsx,.xls,.pdf" required />
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
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th>Conciliado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin transacciones</td>
                            </tr>
                        )}
                        {transactions.map((t) => (
                            <tr key={t.id}>
                                <td className="muted">{t.date}</td>
                                <td>{t.description}</td>
                                <td style={{ fontWeight: 600, color: t.type === 'debit' ? '#dc2626' : '#16a34a' }}>
                                    {t.type === 'debit' ? '-' : '+'}{
                                        Number(t.amount).toLocaleString('es-SV', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        })
                                    }
                                </td>
                                <td>{typeLabel[t.type] ?? t.type}</td>
                                <td className="muted">{t.reference || '—'}</td>
                                <td>
                                    {isOpen ? (
                                        <button
                                            type="button"
                                            className={`btn btn-sm ${t.is_reconciled ? 'active' : 'secondary'}`}
                                            style={{ fontSize: 12, padding: '4px 10px' }}
                                            onClick={() => handleToggle(t.id)}
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
