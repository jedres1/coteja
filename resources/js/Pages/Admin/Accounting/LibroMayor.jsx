import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function LibroMayor({ entries, accounts, periods, selectedPeriod, selectedAccount }) {
    const [periodId, setPeriodId] = useState(selectedPeriod ?? '');
    const [accountId, setAccountId] = useState(selectedAccount ?? '');

    function applyFilters(newPeriod, newAccount) {
        const params = {};
        if (newPeriod) params.period_id = newPeriod;
        if (newAccount) params.account_id = newAccount;
        router.get(route('admin.accounting.mayor'), params);
    }

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        applyFilters(val, accountId);
    }

    function handleAccountChange(e) {
        const val = e.target.value;
        setAccountId(val);
        applyFilters(periodId, val);
    }

    return (
        <AppLayout>
            <Head title="Libro Mayor" />

            <div className="top">
                <h1>Libro Mayor</h1>
            </div>

            {/* Filtros */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ margin: 0, flex: '0 1 260px' }}>
                        Período
                        <select value={periodId} onChange={handlePeriodChange}>
                            <option value="">— Todos los períodos —</option>
                            {periods.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                    </label>
                    <label style={{ margin: 0, flex: '1 1 300px' }}>
                        Cuenta
                        <select value={accountId} onChange={handleAccountChange}>
                            <option value="">— Todas las cuentas —</option>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} — {a.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                            <th style={{ textAlign: 'right' }}>Saldo corriente</th>
                            <th>Partida</th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.length === 0 && (
                            <tr>
                                <td colSpan={7} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {entries.map((entry, idx) => (
                            <tr key={idx}>
                                <td>
                                    <span style={{ fontFamily: 'monospace', fontSize: 12 }}>{entry.account.code}</span>
                                    <br />
                                    <span style={{ fontSize: 13 }}>{entry.account.name}</span>
                                </td>
                                <td style={{ whiteSpace: 'nowrap' }}>
                                    {entry.date ? entry.date.substring(0, 10) : '—'}
                                </td>
                                <td>{entry.description}</td>
                                <td style={{ textAlign: 'right' }}>
                                    {Number(entry.debit) !== 0 ? fmt(entry.debit) : <span className="muted">—</span>}
                                </td>
                                <td style={{ textAlign: 'right' }}>
                                    {Number(entry.credit) !== 0 ? fmt(entry.credit) : <span className="muted">—</span>}
                                </td>
                                <td style={{ textAlign: 'right', fontWeight: 600 }}>
                                    {fmt(entry.running_balance)}
                                </td>
                                <td>
                                    {entry.journal_id ? (
                                        <Link
                                            href={route('admin.accounting.diario.show', entry.journal_id)}
                                            style={{ fontSize: 13 }}
                                        >
                                            Ver partida
                                        </Link>
                                    ) : (
                                        <span className="muted">—</span>
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
