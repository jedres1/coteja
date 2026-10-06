import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function AccountSection({ title, rows, total }) {
    return (
        <div style={{ marginBottom: 24 }}>
            <h3 style={{ margin: '0 0 8px', fontSize: 15, fontWeight: 700, borderBottom: '2px solid #e5e7eb', paddingBottom: 6 }}>
                {title}
            </h3>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={2} className="empty">Sin cuentas</td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr key={row.code}>
                            <td style={{ padding: '4px 0', fontSize: 13 }}>
                                <span style={{ fontFamily: 'monospace', marginRight: 8, color: '#6b7280' }}>{row.code}</span>
                                {row.name}
                            </td>
                            <td style={{ textAlign: 'right', padding: '4px 0', fontSize: 13, whiteSpace: 'nowrap' }}>
                                {fmt(row.balance)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr style={{ fontWeight: 700, borderTop: '1px solid #e5e7eb' }}>
                        <td style={{ padding: '6px 0', fontSize: 13 }}>Total {title}</td>
                        <td style={{ textAlign: 'right', padding: '6px 0', fontSize: 14 }}>{fmt(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

export default function BalanceGeneral({ assets, liabilities, equity, totals, periods, selectedPeriod }) {
    const [periodId, setPeriodId] = useState(selectedPeriod ?? '');

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        router.get(route('admin.accounting.balance-general'), { period_id: val || undefined });
    }

    return (
        <AppLayout>
            <Head title="Balance General" />

            <div className="top">
                <h1>Balance General</h1>
            </div>

            {/* Filtro de período */}
            <div className="card" style={{ marginBottom: 16 }}>
                <label style={{ margin: 0, flex: '0 1 280px' }}>
                    Período
                    <select value={periodId} onChange={handlePeriodChange}>
                        <option value="">— Seleccionar período —</option>
                        {periods.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                </label>
            </div>

            {/* Cuerpo en dos columnas */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, alignItems: 'start' }}>
                {/* Columna izquierda — Activos */}
                <div className="card">
                    <AccountSection title="Activos" rows={assets} total={totals.assets} />
                </div>

                {/* Columna derecha — Pasivos + Patrimonio */}
                <div>
                    <div className="card" style={{ marginBottom: 16 }}>
                        <AccountSection title="Pasivos" rows={liabilities} total={totals.liabilities} />
                    </div>
                    <div className="card">
                        <AccountSection title="Patrimonio" rows={equity} total={totals.equity} />
                        <div style={{
                            borderTop: '2px solid #374151',
                            paddingTop: 8,
                            marginTop: 4,
                            display: 'flex',
                            justifyContent: 'space-between',
                            fontWeight: 700,
                            fontSize: 14,
                        }}>
                            <span>Total Pasivos + Patrimonio</span>
                            <span>{fmt(Number(totals.liabilities) + Number(totals.equity))}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
