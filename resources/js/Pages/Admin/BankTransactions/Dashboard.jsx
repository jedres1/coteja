import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

const fmt = (n) =>
    n != null ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';

const STATUS_LABEL = { abierta: 'Abierta', en_firme: 'En firme', anulada: 'Anulada' };
const STATUS_COLOR = {
    abierta:  { background: '#fef3c7', color: '#92400e' },
    en_firme: { background: '#f0fdf4', color: '#15803d' },
};

// ─── Stat card ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, sub, color = 'blue' }) {
    return (
        <div className={`stat-card c-${color}`}>
            <div className="s-label">{label}</div>
            <div className="s-value">{value}</div>
            {sub && <div className="s-sub">{sub}</div>}
        </div>
    );
}

// ─── Opening reconciliation modal ──────────────────────────────────────────────

function AperturaModal({ account, onClose }) {
    const [step, setStep]           = useState('form');  // form | preview
    const [preview, setPreview]     = useState(null);
    const [previewing, setPreviewing] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        cut_date:          '',
        statement_balance: '',
        transit_ids:       [],
        reference_act:     '',
    });

    async function handlePreview(e) {
        e.preventDefault();
        setPreviewing(true);
        try {
            const res = await fetch(
                route('admin.bank-transactions.reconciliations.opening-preview', account.id),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        cut_date:          data.cut_date,
                        statement_balance: data.statement_balance,
                        transit_ids:       data.transit_ids,
                    }),
                }
            );
            const json = await res.json();
            setPreview(json);
            setStep('preview');
        } catch (_) {}
        finally { setPreviewing(false); }
    }

    function handleConfirm(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.reconciliations.opening-store', account.id), {
            onSuccess: onClose,
        });
    }

    const diff = preview ? (preview.adjustment_needed ?? 0) : 0;

    return (
        <Modal title={`Conciliación de apertura — ${account.name}`} onClose={onClose} maxWidth={520}>
            {step === 'form' && (
                <form onSubmit={handlePreview}>
                    <p style={{ color: '#6b7280', fontSize: 13, marginTop: 0 }}>
                        La apertura da por conciliado todo el historial hasta la fecha de corte,
                        dejando pendientes solo las partidas en tránsito que indiques.
                    </p>
                    <div className="form-grid">
                        <label>
                            Fecha de corte <span style={{ color: '#ef4444' }}>*</span>
                            <input
                                type="date" required
                                value={data.cut_date}
                                onChange={(e) => setData('cut_date', e.target.value)}
                            />
                            {errors.cut_date && <p className="field-error">{errors.cut_date}</p>}
                        </label>
                        <label>
                            Saldo según banco <span style={{ color: '#ef4444' }}>*</span>
                            <input
                                type="number" step="0.01" required placeholder="0.00"
                                value={data.statement_balance}
                                onChange={(e) => setData('statement_balance', e.target.value)}
                            />
                            {errors.statement_balance && <p className="field-error">{errors.statement_balance}</p>}
                        </label>
                        <label style={{ gridColumn: '1 / -1' }}>
                            Referencia de acta (si hay ajuste)
                            <input
                                type="text" maxLength={100} placeholder="Acta N° …"
                                value={data.reference_act}
                                onChange={(e) => setData('reference_act', e.target.value)}
                            />
                            {errors.reference_act && <p className="field-error">{errors.reference_act}</p>}
                        </label>
                    </div>
                    <p style={{ fontSize: 12, color: '#6b7280', margin: '8px 0 16px' }}>
                        Los movimientos en tránsito se podrán seleccionar después de previsualizar.
                    </p>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button type="submit" className="btn" disabled={previewing}>
                            {previewing ? 'Calculando…' : 'Previsualizar'}
                        </button>
                        <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                    </div>
                </form>
            )}

            {step === 'preview' && preview && (
                <form onSubmit={handleConfirm}>
                    <div style={{ display: 'grid', gap: 8, marginBottom: 16 }}>
                        <PreviewRow label="Saldo en libros al corte"  value={fmt(preview.book_balance)} />
                        <PreviewRow label="(−) Partidas en tránsito" value={fmt(preview.transit_sum)} muted />
                        <PreviewRow label="Saldo conciliado"         value={fmt(preview.reconciled_balance)} bold />
                        <PreviewRow label="Saldo según banco"        value={fmt(preview.statement_balance)} bold />
                        <div style={{
                            padding: '10px 14px', borderRadius: 8,
                            background: Math.abs(diff) < 0.001 ? '#f0fdf4' : '#fef3c7',
                            border: `1.5px solid ${Math.abs(diff) < 0.001 ? '#bbf7d0' : '#fde68a'}`,
                        }}>
                            <span style={{ fontWeight: 600, fontSize: 13 }}>Ajuste requerido: </span>
                            <span style={{ fontFamily: 'monospace', fontWeight: 700, color: Math.abs(diff) < 0.001 ? '#16a34a' : '#92400e' }}>
                                {fmt(diff)} {Math.abs(diff) < 0.001 ? '✓' : '— requiere acta aprobada'}
                            </span>
                        </div>
                    </div>
                    {errors.recon && <p className="field-error">{errors.recon}</p>}
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button type="submit" className="btn" disabled={processing}>
                            {processing ? 'Creando apertura…' : 'Crear apertura'}
                        </button>
                        <button type="button" className="btn secondary" onClick={() => setStep('form')}>
                            ← Ajustar datos
                        </button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

function PreviewRow({ label, value, bold, muted }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 0', borderBottom: '1px solid var(--border)' }}>
            <span style={{ fontSize: 13, color: muted ? '#9ca3af' : '#374151' }}>{label}</span>
            <span style={{ fontFamily: 'monospace', fontWeight: bold ? 700 : 400 }}>{value}</span>
        </div>
    );
}

// ─── Main page ─────────────────────────────────────────────────────────────────

export default function BankingDashboard({ stats, accounts, openReconciliations }) {
    const [aperturaFor, setAperturaFor] = useState(null);

    const needsApertura = (accounts ?? []).filter((a) => !a.has_firm_reconciliation && a.is_active);

    return (
        <AppLayout>
            <Head title="Control de bancos" />

            <div className="section-top">
                <div>
                    <h1>Control de bancos</h1>
                    <p className="muted">Resumen de cuentas bancarias y conciliaciones</p>
                </div>
                <Link href={route('admin.bank-transactions.accounts')} className="btn secondary">
                    Gestionar cuentas
                </Link>
            </div>

            {/* Stats */}
            <div className="stats-grid" style={{ marginBottom: 24 }}>
                <StatCard label="Cuentas activas"       value={stats.active_accounts}      color="blue" />
                <StatCard label="Conciliaciones abiertas" value={stats.open_reconciliations} color={stats.open_reconciliations > 0 ? 'amber' : 'gray'} />
                <StatCard label="Conciliadas en firme"  value={stats.firm_reconciliations}  color="green" sub="en total" />
                <StatCard label="Sin apertura"          value={needsApertura.length}        color={needsApertura.length > 0 ? 'amber' : 'gray'} sub="cuentas activas" />
            </div>

            {/* Quick links */}
            <div className="action-grid" style={{ marginBottom: 24 }}>
                <Link href={route('admin.bank-transactions.transactions')} className="action-card">
                    <span className="ac-icon">💳</span>
                    <span className="ac-label">Transacciones</span>
                    <span className="ac-sub">Registrar movimientos</span>
                </Link>
                <Link href={route('admin.bank-transactions.reconciliations')} className="action-card">
                    <span className="ac-icon">📋</span>
                    <span className="ac-label">Conciliaciones</span>
                    <span className="ac-sub">Gestionar conciliaciones</span>
                </Link>
                <Link href={route('admin.bank-transactions.accounts')} className="action-card">
                    <span className="ac-icon">🏦</span>
                    <span className="ac-label">Cuentas bancarias</span>
                    <span className="ac-sub">Administrar cuentas</span>
                </Link>
            </div>

            {/* Alert: accounts needing apertura */}
            {needsApertura.length > 0 && (
                <div className="notice" style={{ marginBottom: 20, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8, padding: '14px 18px' }}>
                    <strong>⚠ {needsApertura.length} cuenta{needsApertura.length !== 1 ? 's' : ''} sin conciliación de apertura.</strong>
                    {' '}Inicia la apertura para comenzar a conciliar.
                    <div style={{ marginTop: 10, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        {needsApertura.map((a) => (
                            <button
                                key={a.id} type="button" className="btn secondary"
                                style={{ fontSize: 12 }}
                                onClick={() => setAperturaFor(a)}
                            >
                                Apertura — {a.name}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Open reconciliations */}
            {openReconciliations?.length > 0 && (
                <div className="card" style={{ marginBottom: 20 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                        <h3 style={{ margin: 0, fontSize: 14 }}>Conciliaciones abiertas</h3>
                        <Link href={route('admin.bank-transactions.reconciliations')} style={{ fontSize: 12, color: 'var(--brand)' }}>Ver todas →</Link>
                    </div>
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cuenta</th>
                                    <th>Período</th>
                                    <th>Fecha de corte</th>
                                    <th style={{ textAlign: 'right' }}>Saldo banco</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {openReconciliations.map((r) => (
                                    <tr key={r.id}>
                                        <td style={{ fontWeight: 500 }}>{r.bank_account?.name ?? '—'}</td>
                                        <td className="muted">{r.period_label}</td>
                                        <td className="muted">{r.cut_date ?? '—'}</td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(r.statement_balance)}</td>
                                        <td>
                                            <Link
                                                href={route('admin.bank-transactions.reconciliations.show', r.id)}
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '3px 10px' }}
                                            >
                                                Continuar →
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Accounts summary */}
            <div className="card">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h3 style={{ margin: 0, fontSize: 14 }}>Cuentas bancarias</h3>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Cuenta</th>
                                <th>Banco</th>
                                <th>Moneda</th>
                                <th style={{ textAlign: 'right' }}>Saldo en libros</th>
                                <th>Último corte</th>
                                <th>Conciliación</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(!accounts?.length) && (
                                <tr><td colSpan={6} className="empty">Sin cuentas registradas</td></tr>
                            )}
                            {(accounts ?? []).map((a) => (
                                <tr key={a.id} style={{ opacity: a.is_active ? 1 : 0.4 }}>
                                    <td style={{ fontWeight: 500 }}>
                                        {a.name}
                                        {!a.is_active && <span style={{ marginLeft: 6, fontSize: 10, color: '#9ca3af' }}>inactiva</span>}
                                    </td>
                                    <td className="muted">{a.bank_name}</td>
                                    <td className="muted">{a.currency}</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>
                                        {fmt(a.book_balance)}
                                    </td>
                                    <td className="muted">{a.last_reconciliation_date ?? '—'}</td>
                                    <td>
                                        {a.open_reconciliation_id ? (
                                            <Link
                                                href={route('admin.bank-transactions.reconciliations.show', a.open_reconciliation_id)}
                                                style={{ fontSize: 12 }}
                                            >
                                                <span style={{ ...STATUS_COLOR.abierta, fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10 }}>
                                                    Abierta
                                                </span>
                                            </Link>
                                        ) : !a.has_firm_reconciliation && a.is_active ? (
                                            <button
                                                type="button"
                                                className="btn secondary"
                                                style={{ fontSize: 11, padding: '2px 8px', color: '#92400e', borderColor: '#fde68a' }}
                                                onClick={() => setAperturaFor(a)}
                                            >
                                                Iniciar apertura
                                            </button>
                                        ) : (
                                            <span style={{ ...STATUS_COLOR.en_firme, fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10 }}>
                                                Al día
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {aperturaFor && (
                <AperturaModal account={aperturaFor} onClose={() => setAperturaFor(null)} />
            )}
        </AppLayout>
    );
}
