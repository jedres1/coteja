import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function BalanzaComprobacion({ data, periods, selectedPeriod, totals }) {
    const [periodId, setPeriodId] = useState(selectedPeriod ?? '');

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        router.get(route('admin.accounting.balanza'), { period_id: val || undefined });
    }

    return (
        <AppLayout>
            <Head title="Balanza de Comprobación" />

            <div className="top">
                <h1>Balanza de Comprobación</h1>
            </div>

            {/* Filtro de período */}
            <div className="card" style={{ marginBottom: 16 }}>
                <label style={{ margin: 0, flex: '0 1 280px' }}>
                    Período
                    <select value={periodId} onChange={handlePeriodChange}>
                        <option value="">— Seleccionar período —</option>
                        {periods.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th rowSpan={2}>Código</th>
                            <th rowSpan={2}>Cuenta</th>
                            <th colSpan={2} style={{ textAlign: 'center', borderBottom: '1px solid #e5e7eb' }}>
                                Saldo inicial
                            </th>
                            <th colSpan={2} style={{ textAlign: 'center', borderBottom: '1px solid #e5e7eb' }}>
                                Movimientos del período
                            </th>
                            <th colSpan={2} style={{ textAlign: 'center', borderBottom: '1px solid #e5e7eb' }}>
                                Saldo final
                            </th>
                        </tr>
                        <tr>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {data.map((group) => (
                            <>
                                {/* Encabezado de grupo */}
                                <tr key={`group-${group.account_type}`}>
                                    <td
                                        colSpan={8}
                                        style={{
                                            background: '#f3f4f6',
                                            fontWeight: 700,
                                            fontSize: 13,
                                            padding: '6px 12px',
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.05em',
                                        }}
                                    >
                                        {group.account_type}
                                    </td>
                                </tr>
                                {/* Filas de cuentas */}
                                {group.accounts.map((acc) => (
                                    <tr key={`${group.account_type}-${acc.code}`}>
                                        <td style={{ fontFamily: 'monospace' }}>{acc.code}</td>
                                        <td>{acc.name}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.opening_debit)}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.opening_credit)}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.period_debit)}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.period_credit)}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.closing_debit)}</td>
                                        <td style={{ textAlign: 'right' }}>{fmt(acc.closing_credit)}</td>
                                    </tr>
                                ))}
                            </>
                        ))}
                    </tbody>
                    {/* Fila de totales */}
                    {totals && (
                        <tfoot>
                            <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                                <td colSpan={2}>Totales</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.opening_debit)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.opening_credit)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.period_debit)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.period_credit)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.closing_debit)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(totals.closing_credit)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </AppLayout>
    );
}
