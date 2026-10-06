import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const ACCOUNT_TYPES = { activo: 'Activo', pasivo: 'Pasivo', patrimonio: 'Patrimonio', gasto: 'Gasto', ingreso: 'Ingreso' };
const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function Saldos({ balances, summaryByType, accounts, period, year, month, accountId, acumulado, allPeriods }) {
    const [yearVal, setYearVal]       = useState(String(year));
    const [monthVal, setMonthVal]     = useState(String(month));
    const [accountVal, setAccountVal] = useState(accountId ? String(accountId) : '');
    const [acumVal, setAcumVal]       = useState(!!acumulado);

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.saldos'), {
            year:       yearVal,
            month:      monthVal,
            account_id: accountVal || undefined,
            acumulado:  acumVal ? 1 : undefined,
        });
    }

    const periodName = period
        ? `${MONTHS[(period.month ?? 1) - 1]} ${period.year}`
        : `${MONTHS[(month ?? 1) - 1]} ${year}`;

    const availableYears = [...new Set((allPeriods ?? []).map((p) => p.year))].sort((a, b) => b - a);
    if (!availableYears.includes(Number(yearVal))) availableYears.unshift(Number(yearVal));

    return (
        <AppLayout>
            <Head title="Saldos de cuentas" />
            <div className="top">
                <h1>Saldos de cuentas</h1>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={applyFilters} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ margin: 0 }}>
                        Año
                        <input
                            type="number"
                            value={yearVal}
                            onChange={(e) => setYearVal(e.target.value)}
                            min={2020}
                            max={2100}
                            style={{ width: 100 }}
                        />
                    </label>
                    <label style={{ margin: 0 }}>
                        Mes
                        <select value={monthVal} onChange={(e) => setMonthVal(e.target.value)}>
                            {MONTHS.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
                        </select>
                    </label>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Cuenta
                        <select value={accountVal} onChange={(e) => setAccountVal(e.target.value)}>
                            <option value="">Todas las cuentas</option>
                            {(accounts ?? []).map((a) => (
                                <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                            ))}
                        </select>
                    </label>
                    <label style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 6, flexDirection: 'row' }}>
                        <input
                            type="checkbox"
                            checked={acumVal}
                            onChange={(e) => setAcumVal(e.target.checked)}
                            style={{ width: 'auto', margin: 0 }}
                        />
                        Acumulado
                    </label>
                    <button type="submit" className="btn">Consultar</button>
                </form>
                <div style={{ marginTop: 8, fontSize: 13, color: '#6b7280' }}>
                    Período: <strong>{periodName}</strong>
                    {period && (
                        <span
                            style={{
                                marginLeft: 8, fontSize: 11, fontWeight: 600, padding: '1px 7px', borderRadius: 10,
                                background: period.is_open ? '#f0fdf4' : '#f1f5f9',
                                color: period.is_open ? '#15803d' : '#64748b',
                            }}
                        >
                            {period.is_open ? 'Abierto' : 'Cerrado'}
                        </span>
                    )}
                </div>
            </div>

            {/* Resumen por tipo */}
            {summaryByType && Object.keys(summaryByType).length > 0 && (
                <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
                    {Object.entries(summaryByType).map(([tipo, s]) => (
                        <div key={tipo} className="card" style={{ flex: '1 1 120px', padding: '10px 14px' }}>
                            <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'capitalize' }}>{ACCOUNT_TYPES[tipo] ?? tipo}</div>
                            <div style={{ fontSize: 11, color: '#6b7280' }}>Saldo</div>
                            <div style={{ fontWeight: 700, fontFamily: 'monospace', fontSize: 15 }}>{fmt(s.saldo)}</div>
                        </div>
                    ))}
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cuenta</th>
                            <th>Tipo</th>
                            <th style={{ textAlign: 'right' }}>Total débito</th>
                            <th style={{ textAlign: 'right' }}>Total crédito</th>
                            <th style={{ textAlign: 'right' }}>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(balances ?? []).length === 0 && (
                            <tr><td colSpan={6} className="empty">Sin movimientos en el período seleccionado</td></tr>
                        )}
                        {(balances ?? []).map((acc) => (
                            <tr key={acc.id}>
                                <td style={{ fontFamily: 'monospace' }}>{acc.code}</td>
                                <td>{acc.name}</td>
                                <td style={{ fontSize: 12, color: '#6b7280' }}>{ACCOUNT_TYPES[acc.type] ?? acc.type}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(acc.total_debit)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(acc.total_credit)}</td>
                                <td style={{
                                    textAlign: 'right', fontFamily: 'monospace', fontWeight: 600,
                                    color: Number(acc.saldo) >= 0 ? '#15803d' : '#dc2626',
                                }}>
                                    {fmt(acc.saldo)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
