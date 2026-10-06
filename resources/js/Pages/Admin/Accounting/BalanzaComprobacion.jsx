import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const ACCOUNT_TYPES = { activo: 'Activo', pasivo: 'Pasivo', patrimonio: 'Patrimonio', gasto: 'Gasto', ingreso: 'Ingreso' };
const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

export default function BalanzaComprobacion({
    rows, period, year, month, acumulado, tipo, soloConMovimiento,
    totalSumaDebe, totalSumaHaber, totalSaldoDeudor, totalSaldoAcreedor, availableYears,
}) {
    const [yearVal, setYearVal]   = useState(String(year));
    const [monthVal, setMonthVal] = useState(String(month));
    const [acumVal, setAcumVal]   = useState(!!acumulado);
    const [tipoVal, setTipoVal]   = useState(tipo ?? '');
    const [soloMov, setSoloMov]   = useState(!!soloConMovimiento);

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.balanza-comprobacion'), {
            year:            yearVal,
            month:           monthVal,
            acumulado:       acumVal ? 1 : undefined,
            tipo:            tipoVal || undefined,
            solo_movimiento: soloMov ? 1 : undefined,
        });
    }

    const periodName = period
        ? `${MONTHS[(period.month ?? 1) - 1]} ${period.year}`
        : `${MONTHS[(month ?? 1) - 1]} ${year}`;

    const yearsOpts = availableYears ?? [year];

    return (
        <AppLayout>
            <Head title="Balanza de Comprobación" />
            <div className="top">
                <h1>Balanza de Comprobación</h1>
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
                    <label style={{ margin: 0 }}>
                        Tipo
                        <select value={tipoVal} onChange={(e) => setTipoVal(e.target.value)}>
                            <option value="">Todos</option>
                            {Object.entries(ACCOUNT_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                        </select>
                    </label>
                    <label style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 6, flexDirection: 'row' }}>
                        <input type="checkbox" checked={acumVal} onChange={(e) => setAcumVal(e.target.checked)} style={{ width: 'auto', margin: 0 }} />
                        Acumulado
                    </label>
                    <label style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 6, flexDirection: 'row' }}>
                        <input type="checkbox" checked={soloMov} onChange={(e) => setSoloMov(e.target.checked)} style={{ width: 'auto', margin: 0 }} />
                        Solo con movimiento
                    </label>
                    <button type="submit" className="btn">Consultar</button>
                </form>
                <div style={{ marginTop: 8, fontSize: 13, color: '#6b7280' }}>
                    Período: <strong>{periodName}</strong>
                    {acumVal && <span style={{ marginLeft: 6, fontSize: 12 }}>(acumulado enero → {MONTHS[(month ?? 1) - 1]})</span>}
                </div>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cuenta</th>
                            <th>Tipo</th>
                            <th style={{ textAlign: 'right' }}>Suma debe</th>
                            <th style={{ textAlign: 'right' }}>Suma haber</th>
                            <th style={{ textAlign: 'right' }}>Saldo deudor</th>
                            <th style={{ textAlign: 'right' }}>Saldo acreedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(rows ?? []).length === 0 && (
                            <tr><td colSpan={7} className="empty">Sin registros en el período</td></tr>
                        )}
                        {(rows ?? []).map((r) => (
                            <tr key={r.id} style={!r.tiene_movimiento ? { color: '#9ca3af' } : {}}>
                                <td style={{ fontFamily: 'monospace' }}>{r.code}</td>
                                <td>{r.name}</td>
                                <td style={{ fontSize: 12, color: '#6b7280' }}>{ACCOUNT_TYPES[r.type] ?? r.type}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {r.suma_debe > 0 ? fmt(r.suma_debe) : '—'}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {r.suma_haber > 0 ? fmt(r.suma_haber) : '—'}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {r.saldo_deudor > 0 ? fmt(r.saldo_deudor) : '—'}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {r.saldo_acreedor > 0 ? fmt(r.saldo_acreedor) : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                            <td colSpan={3}>Totales</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalSumaDebe)}</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalSumaHaber)}</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalSaldoDeudor)}</td>
                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totalSaldoAcreedor)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </AppLayout>
    );
}
