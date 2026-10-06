import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function LibroMayor({ ledger, allAccounts, period, year, month, acumulado, accountId, startDate, endDate, availableYears }) {
    const [yearVal, setYearVal]       = useState(String(year));
    const [monthVal, setMonthVal]     = useState(String(month));
    const [accountVal, setAccountVal] = useState(accountId ? String(accountId) : '');
    const [acumVal, setAcumVal]       = useState(!!acumulado);

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.libro-mayor'), {
            year:       yearVal,
            month:      monthVal,
            account_id: accountVal || undefined,
            acumulado:  acumVal ? 1 : undefined,
        });
    }

    const periodName = period
        ? `${MONTHS[(period.month ?? 1) - 1]} ${period.year}`
        : `${MONTHS[(month ?? 1) - 1]} ${year}`;

    const yearsOpts = availableYears ?? [year];

    return (
        <AppLayout>
            <Head title="Libro Mayor" />
            <div className="top">
                <h1>Libro Mayor</h1>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={applyFilters} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ margin: 0 }}>
                        Año
                        <select value={yearVal} onChange={(e) => setYearVal(e.target.value)}>
                            {yearsOpts.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                    </label>
                    <label style={{ margin: 0 }}>
                        Mes
                        <select value={monthVal} onChange={(e) => setMonthVal(e.target.value)}>
                            {MONTHS.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
                        </select>
                    </label>
                    <label style={{ flex: '1 1 220px', margin: 0 }}>
                        Cuenta
                        <select value={accountVal} onChange={(e) => setAccountVal(e.target.value)}>
                            <option value="">Todas las cuentas con movimiento</option>
                            {(allAccounts ?? []).map((a) => (
                                <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                            ))}
                        </select>
                    </label>
                    <label style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 6, flexDirection: 'row' }}>
                        <input type="checkbox" checked={acumVal} onChange={(e) => setAcumVal(e.target.checked)} style={{ width: 'auto', margin: 0 }} />
                        Acumulado
                    </label>
                    <button type="submit" className="btn">Consultar</button>
                </form>
                <div style={{ marginTop: 8, fontSize: 13, color: '#6b7280' }}>
                    Período: <strong>{periodName}</strong>
                    {startDate && endDate && <span style={{ marginLeft: 6 }}>({startDate} → {endDate})</span>}
                </div>
            </div>

            {(ledger ?? []).length === 0 && (
                <div className="card">
                    <p className="empty">Sin movimientos en el período seleccionado.</p>
                </div>
            )}

            {(ledger ?? []).map((item, idx) => (
                <div key={idx} className="card" style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10, flexWrap: 'wrap', gap: 8 }}>
                        <div>
                            <span style={{ fontFamily: 'monospace', fontWeight: 700, marginRight: 8 }}>{item.account?.code}</span>
                            <span style={{ fontWeight: 600 }}>{item.account?.name}</span>
                        </div>
                        <div style={{ display: 'flex', gap: 20, fontSize: 13, flexWrap: 'wrap' }}>
                            <span>Saldo inicial: <strong style={{ fontFamily: 'monospace' }}>{fmt(item.saldo_inicial)}</strong></span>
                            <span>Total debe: <strong style={{ fontFamily: 'monospace' }}>{fmt(item.total_debe)}</strong></span>
                            <span>Total haber: <strong style={{ fontFamily: 'monospace' }}>{fmt(item.total_haber)}</strong></span>
                            <span>Saldo final: <strong style={{ fontFamily: 'monospace' }}>{fmt(item.saldo_final)}</strong></span>
                        </div>
                    </div>
                    <div className="table-scroll">
                        <table style={{ minWidth: 700 }}>
                            <thead>
                                <tr>
                                    <th>N° Asiento</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th style={{ textAlign: 'right' }}>Debe</th>
                                    <th style={{ textAlign: 'right' }}>Haber</th>
                                    <th style={{ textAlign: 'right' }}>Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(item.lines ?? []).length === 0 && (
                                    <tr><td colSpan={6} className="empty">Sin movimientos</td></tr>
                                )}
                                {(item.lines ?? []).map((line, li) => (
                                    <tr key={li}>
                                        <td>
                                            <Link
                                                href={route('admin.accounting.diario.show', line.entry_id)}
                                                style={{ fontFamily: 'monospace', fontSize: 12 }}
                                            >
                                                {line.entry_number}
                                            </Link>
                                        </td>
                                        <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>
                                            {line.entry_date ? String(line.entry_date).substring(0, 10) : '—'}
                                        </td>
                                        <td style={{ fontSize: 13 }}>{line.description ?? line.source_document ?? '—'}</td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                            {Number(line.debe) > 0 ? fmt(line.debe) : '—'}
                                        </td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                            {Number(line.haber) > 0 ? fmt(line.haber) : '—'}
                                        </td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>
                                            {fmt(line.saldo)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ))}
        </AppLayout>
    );
}
