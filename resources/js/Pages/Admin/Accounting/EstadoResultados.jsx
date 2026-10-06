import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function SectionBlock({ section, rows }) {
    const total = (rows ?? []).reduce((s, r) => s + Number(r.saldo), 0);
    return (
        <div style={{ marginBottom: 12 }}>
            <div style={{ fontWeight: 600, fontSize: 13, borderBottom: '1px solid #e5e7eb', paddingBottom: 4, marginBottom: 6 }}>
                {section.code} — {section.name}
            </div>
            {(rows ?? []).map((r) => (
                <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0 3px 12px', fontSize: 13 }}>
                    <span>
                        <span style={{ fontFamily: 'monospace', color: '#6b7280', marginRight: 6 }}>{r.code}</span>
                        {r.name}
                    </span>
                    <span style={{ fontFamily: 'monospace', whiteSpace: 'nowrap', marginLeft: 8 }}>{fmt(r.saldo)}</span>
                </div>
            ))}
            <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e5e7eb', paddingTop: 4, marginTop: 4, fontWeight: 600, fontSize: 13 }}>
                <span>Subtotal {section.name}</span>
                <span style={{ fontFamily: 'monospace' }}>{fmt(total)}</span>
            </div>
        </div>
    );
}

export default function EstadoResultados({
    rows, bySection, sections, totalIngresos, totalGastos, utilidad,
    period, year, month, acumulado, availableYears,
}) {
    const [yearVal, setYearVal]   = useState(String(year));
    const [monthVal, setMonthVal] = useState(String(month));
    const [acumVal, setAcumVal]   = useState(!!acumulado);

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.estado-resultados'), {
            year:      yearVal,
            month:     monthVal,
            acumulado: acumVal ? 1 : undefined,
        });
    }

    const ingresoSections = (sections ?? []).filter((s) => s.type === 'ingreso');
    const gastoSections   = (sections ?? []).filter((s) => s.type === 'gasto');
    const netPositive     = Number(utilidad ?? 0) >= 0;

    const periodName = period
        ? `${MONTHS[(period.month ?? 1) - 1]} ${period.year}`
        : `${MONTHS[(month ?? 1) - 1]} ${year}`;

    const yearsOpts = availableYears ?? [year];

    return (
        <AppLayout>
            <Head title="Estado de Resultados" />
            <div className="top">
                <h1>Estado de Resultados</h1>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={applyFilters} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
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
                    <label style={{ margin: 0, display: 'flex', alignItems: 'center', gap: 6, flexDirection: 'row' }}>
                        <input type="checkbox" checked={acumVal} onChange={(e) => setAcumVal(e.target.checked)} style={{ width: 'auto', margin: 0 }} />
                        Acumulado
                    </label>
                    <button type="submit" className="btn">Consultar</button>
                </form>
                <div style={{ marginTop: 8, fontSize: 13, color: '#6b7280' }}>
                    Período: <strong>{periodName}</strong>
                    {acumVal && <span style={{ marginLeft: 6, fontSize: 12 }}>(acumulado)</span>}
                </div>
            </div>

            <div className="card" style={{ maxWidth: 800 }}>
                {/* Ingresos */}
                <div style={{ marginBottom: 24 }}>
                    <h3 style={{
                        margin: '0 0 10px', fontSize: 14, fontWeight: 700,
                        textTransform: 'uppercase', letterSpacing: '0.05em',
                        color: '#166534', borderBottom: '2px solid #dcfce7', paddingBottom: 6,
                    }}>
                        Ingresos
                    </h3>
                    {ingresoSections.length === 0
                        ? <p className="empty">Sin ingresos registrados</p>
                        : ingresoSections.map((s) => <SectionBlock key={s.id} section={s} rows={(bySection ?? {})[s.code] ?? []} />)
                    }
                    <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e5e7eb', paddingTop: 6, fontWeight: 700, fontSize: 14 }}>
                        <span>Total Ingresos</span>
                        <span style={{ fontFamily: 'monospace', color: '#166534' }}>{fmt(totalIngresos)}</span>
                    </div>
                </div>

                {/* Gastos */}
                <div style={{ marginBottom: 24 }}>
                    <h3 style={{
                        margin: '0 0 10px', fontSize: 14, fontWeight: 700,
                        textTransform: 'uppercase', letterSpacing: '0.05em',
                        color: '#991b1b', borderBottom: '2px solid #fee2e2', paddingBottom: 6,
                    }}>
                        Gastos
                    </h3>
                    {gastoSections.length === 0
                        ? <p className="empty">Sin gastos registrados</p>
                        : gastoSections.map((s) => <SectionBlock key={s.id} section={s} rows={(bySection ?? {})[s.code] ?? []} />)
                    }
                    <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e5e7eb', paddingTop: 6, fontWeight: 700, fontSize: 14 }}>
                        <span>Total Gastos</span>
                        <span style={{ fontFamily: 'monospace', color: '#991b1b' }}>{fmt(totalGastos)}</span>
                    </div>
                </div>

                {/* Resultado */}
                <div style={{ borderTop: '2px solid #374151', paddingTop: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontWeight: 700, fontSize: 15 }}>
                        {netPositive ? 'Utilidad del período' : 'Pérdida del período'}
                    </span>
                    <span style={{ fontWeight: 700, fontSize: 17, fontFamily: 'monospace', color: netPositive ? '#166534' : '#991b1b' }}>
                        {fmt(utilidad)}
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
