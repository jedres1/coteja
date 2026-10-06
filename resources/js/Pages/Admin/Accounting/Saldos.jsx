import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function Saldos({ accounts, periods, selectedPeriod }) {
    const [periodId, setPeriodId] = useState(selectedPeriod ?? '');

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        router.get(route('admin.accounting.saldos'), { period_id: val || undefined });
    }

    return (
        <AppLayout>
            <Head title="Saldos por período" />

            <div className="top">
                <h1>Saldos por período</h1>
            </div>

            {/* Filtro de período */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                    <label style={{ margin: 0, flex: '0 1 280px' }}>
                        Período
                        <select value={periodId} onChange={handlePeriodChange}>
                            <option value="">— Todos los períodos —</option>
                            {periods.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} {p.is_open ? '(abierto)' : '(cerrado)'}
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
                            <th>Código</th>
                            <th>Cuenta</th>
                            <th>Tipo</th>
                            <th style={{ textAlign: 'right' }}>Débito</th>
                            <th style={{ textAlign: 'right' }}>Crédito</th>
                            <th style={{ textAlign: 'right' }}>Saldo neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {accounts.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {accounts.map((acc) => (
                            <tr key={acc.id}>
                                <td style={{ fontFamily: 'monospace' }}>{acc.code}</td>
                                <td>{acc.name}</td>
                                <td>{acc.account_type}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(acc.debit_balance)}</td>
                                <td style={{ textAlign: 'right' }}>{fmt(acc.credit_balance)}</td>
                                <td style={{ textAlign: 'right', fontWeight: 600 }}>
                                    {fmt(acc.net_balance)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
