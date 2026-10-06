import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function AccountList({ rows }) {
    return (
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
        </table>
    );
}

export default function EstadoResultados({ revenues, expenses, totals, periods, selectedPeriod }) {
    const [periodId, setPeriodId] = useState(selectedPeriod ?? '');

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        router.get(route('admin.accounting.estado-resultados'), { period_id: val || undefined });
    }

    const netPositive = Number(totals.net_income) >= 0;

    return (
        <AppLayout>
            <Head title="Estado de Resultados" />

            <div className="top">
                <h1>Estado de Resultados</h1>
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

            <div className="card" style={{ maxWidth: 720 }}>
                {/* Ingresos */}
                <div style={{ marginBottom: 24 }}>
                    <h3 style={{
                        margin: '0 0 8px',
                        fontSize: 14,
                        fontWeight: 700,
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                        color: '#166534',
                        borderBottom: '2px solid #dcfce7',
                        paddingBottom: 6,
                    }}>
                        Ingresos
                    </h3>
                    <AccountList rows={revenues} />
                    <div style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        fontWeight: 700,
                        fontSize: 13,
                        borderTop: '1px solid #e5e7eb',
                        paddingTop: 6,
                        marginTop: 4,
                    }}>
                        <span>Total Ingresos</span>
                        <span>{fmt(totals.revenues)}</span>
                    </div>
                </div>

                {/* Gastos */}
                <div style={{ marginBottom: 24 }}>
                    <h3 style={{
                        margin: '0 0 8px',
                        fontSize: 14,
                        fontWeight: 700,
                        textTransform: 'uppercase',
                        letterSpacing: '0.05em',
                        color: '#991b1b',
                        borderBottom: '2px solid #fee2e2',
                        paddingBottom: 6,
                    }}>
                        Gastos
                    </h3>
                    <AccountList rows={expenses} />
                    <div style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        fontWeight: 700,
                        fontSize: 13,
                        borderTop: '1px solid #e5e7eb',
                        paddingTop: 6,
                        marginTop: 4,
                    }}>
                        <span>Total Gastos</span>
                        <span>{fmt(totals.expenses)}</span>
                    </div>
                </div>

                {/* Resultado neto */}
                <div style={{
                    borderTop: '2px solid #374151',
                    paddingTop: 12,
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                }}>
                    <span style={{ fontWeight: 700, fontSize: 15 }}>
                        {netPositive ? 'Utilidad neta' : 'Pérdida neta'}
                    </span>
                    <span style={{
                        fontWeight: 700,
                        fontSize: 16,
                        color: netPositive ? '#166534' : '#991b1b',
                    }}>
                        {fmt(totals.net_income)}
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
