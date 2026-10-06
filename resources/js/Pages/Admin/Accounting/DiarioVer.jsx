import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = {
    pendiente: 'Pendiente',
    aprobado:  'Aprobado',
    anulado:   'Anulado',
};

const STATUS_BADGE = {
    pendiente: 'badge-warning',
    aprobado:  'active',
    anulado:   'suspended',
};

export default function DiarioVer({ entry, canEdit }) {
    function handleApprove() {
        if (!confirm('¿Aprobar esta partida?')) return;
        router.post(route('admin.accounting.diario.approve', entry.id));
    }

    function handleReject() {
        if (!confirm('¿Anular esta partida? Esta acción no se puede deshacer.')) return;
        router.post(route('admin.accounting.diario.reject', entry.id));
    }

    const totalDebit  = entry.lines?.reduce((s, l) => s + Number(l.debit),  0) ?? Number(entry.total_debit);
    const totalCredit = entry.lines?.reduce((s, l) => s + Number(l.credit), 0) ?? Number(entry.total_credit);
    const balanced    = Math.abs(totalDebit - totalCredit) < 0.01;

    return (
        <AppLayout>
            <Head title={`Partida ${entry.reference}`} />

            {/* Encabezado */}
            <div className="top">
                <div>
                    <h1 style={{ marginBottom: 4 }}>Partida {entry.reference}</h1>
                    <span className={`badge ${STATUS_BADGE[entry.status] ?? ''}`}>
                        {STATUS_LABELS[entry.status] ?? entry.status}
                    </span>
                </div>
                <Link href={route('admin.accounting.diario.index')} className="btn secondary">
                    ← Volver
                </Link>
            </div>

            {/* Detalle de cabecera */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px 24px' }}>
                    <div>
                        <p className="muted" style={{ margin: '0 0 2px', fontSize: 12 }}>Fecha</p>
                        <p style={{ margin: 0, fontWeight: 600 }}>
                            {entry.date ? entry.date.substring(0, 10) : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 2px', fontSize: 12 }}>Referencia</p>
                        <p style={{ margin: 0, fontWeight: 600, fontFamily: 'monospace' }}>{entry.reference}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 2px', fontSize: 12 }}>Descripción</p>
                        <p style={{ margin: 0 }}>{entry.description || '—'}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 2px', fontSize: 12 }}>Total débito</p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{fmt(entry.total_debit)}</p>
                    </div>
                    <div>
                        <p className="muted" style={{ margin: '0 0 2px', fontSize: 12 }}>Total crédito</p>
                        <p style={{ margin: 0, fontWeight: 600 }}>{fmt(entry.total_credit)}</p>
                    </div>
                    {!balanced && (
                        <div>
                            <p style={{ margin: 0, color: '#991b1b', fontWeight: 600, fontSize: 13 }}>
                                Partida desbalanceada
                            </p>
                        </div>
                    )}
                </div>
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
                            <tr>
                                <td colSpan={5} className="empty">Sin líneas</td>
                            </tr>
                        )}
                        {(entry.lines ?? []).map((line) => (
                            <tr key={line.id}>
                                <td>
                                    <span style={{ fontFamily: 'monospace', fontSize: 12, marginRight: 6 }}>
                                        {line.account.code}
                                    </span>
                                    {line.account.name}
                                </td>
                                <td>{line.description || <span className="muted">—</span>}</td>
                                <td>
                                    {line.cost_center?.name
                                        ? line.cost_center.name
                                        : <span className="muted">—</span>
                                    }
                                </td>
                                <td style={{ textAlign: 'right' }}>
                                    {Number(line.debit) !== 0
                                        ? fmt(line.debit)
                                        : <span className="muted">—</span>
                                    }
                                </td>
                                <td style={{ textAlign: 'right' }}>
                                    {Number(line.credit) !== 0
                                        ? fmt(line.credit)
                                        : <span className="muted">—</span>
                                    }
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                            <td colSpan={3}>Totales</td>
                            <td style={{ textAlign: 'right' }}>{fmt(totalDebit)}</td>
                            <td style={{ textAlign: 'right' }}>{fmt(totalCredit)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* Acciones — solo si estado pendiente y canEdit */}
            {entry.status === 'pendiente' && canEdit && (
                <div style={{ display: 'flex', gap: 10 }}>
                    <button type="button" className="btn" onClick={handleApprove}>
                        Aprobar partida
                    </button>
                    <button type="button" className="btn danger" onClick={handleReject}>
                        Anular partida
                    </button>
                </div>
            )}
        </AppLayout>
    );
}
