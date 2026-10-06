import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function SectionBlock({ section, rows }) {
    const total = (rows ?? []).reduce((s, r) => s + Number(r.saldo), 0);
    return (
        <div style={{ marginBottom: 16 }}>
            <div style={{ fontWeight: 700, fontSize: 13, borderBottom: '1px solid #e5e7eb', paddingBottom: 4, marginBottom: 6 }}>
                {section.code} — {section.name}
            </div>
            {(rows ?? []).map((r) => (
                <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0 3px 12px', fontSize: 13 }}>
                    <span>
                        <span style={{ fontFamily: 'monospace', color: '#6b7280', marginRight: 6 }}>{r.code}</span>
                        {r.name}
                    </span>
                    <span style={{ fontFamily: 'monospace', fontWeight: 500, whiteSpace: 'nowrap', marginLeft: 8 }}>{fmt(r.saldo)}</span>
                </div>
            ))}
            <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e5e7eb', paddingTop: 4, marginTop: 4, fontWeight: 700, fontSize: 13 }}>
                <span>Total {section.name}</span>
                <span style={{ fontFamily: 'monospace' }}>{fmt(total)}</span>
            </div>
        </div>
    );
}

export default function BalanceGeneral({ rows, bySection, sections, period, year, month, availableYears }) {
    const [yearVal, setYearVal]   = useState(String(year));
    const [monthVal, setMonthVal] = useState(String(month));

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.balance-general'), { year: yearVal, month: monthVal });
    }

    const activoSections  = (sections ?? []).filter((s) => s.type === 'activo');
    const pasivoSections  = (sections ?? []).filter((s) => s.type === 'pasivo');
    const patriSections   = (sections ?? []).filter((s) => s.type === 'patrimonio');

    const totalActivo = (rows ?? []).filter((r) => r.type === 'activo').reduce((s, r) => s + Number(r.saldo), 0);
    const totalPasivo = (rows ?? []).filter((r) => r.type === 'pasivo').reduce((s, r) => s + Number(r.saldo), 0);
    const totalPatri  = (rows ?? []).filter((r) => r.type === 'patrimonio').reduce((s, r) => s + Number(r.saldo), 0);

    const periodName = period
        ? `${MONTHS[(period.month ?? 1) - 1]} ${period.year}`
        : `${MONTHS[(month ?? 1) - 1]} ${year}`;

    const yearsOpts = availableYears ?? [year];

    return (
        <AppLayout>
            <Head title="Balance General" />
            <div className="top">
                <h1>Balance General</h1>
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
                    <button type="submit" className="btn">Consultar</button>
                </form>
                <div style={{ marginTop: 8, fontSize: 13, color: '#6b7280' }}>
                    Saldos acumulados al cierre de: <strong>{periodName}</strong>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, alignItems: 'start' }}>
                {/* Activos */}
                <div className="card">
                    <h3 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                        Activos
                    </h3>
                    {activoSections.length === 0
                        ? <p className="empty">Sin cuentas de activo con saldo</p>
                        : activoSections.map((s) => (
                            <SectionBlock key={s.id} section={s} rows={(bySection ?? {})[s.code] ?? []} />
                        ))
                    }
                    <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid #374151', paddingTop: 8, marginTop: 4, fontWeight: 700, fontSize: 14 }}>
                        <span>Total Activos</span>
                        <span style={{ fontFamily: 'monospace' }}>{fmt(totalActivo)}</span>
                    </div>
                </div>

                {/* Pasivos + Patrimonio */}
                <div>
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h3 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                            Pasivos
                        </h3>
                        {pasivoSections.length === 0
                            ? <p className="empty">Sin cuentas de pasivo con saldo</p>
                            : pasivoSections.map((s) => (
                                <SectionBlock key={s.id} section={s} rows={(bySection ?? {})[s.code] ?? []} />
                            ))
                        }
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid #374151', paddingTop: 8, marginTop: 4, fontWeight: 700, fontSize: 14 }}>
                            <span>Total Pasivos</span>
                            <span style={{ fontFamily: 'monospace' }}>{fmt(totalPasivo)}</span>
                        </div>
                    </div>

                    <div className="card">
                        <h3 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                            Patrimonio
                        </h3>
                        {patriSections.length === 0
                            ? <p className="empty">Sin cuentas de patrimonio con saldo</p>
                            : patriSections.map((s) => (
                                <SectionBlock key={s.id} section={s} rows={(bySection ?? {})[s.code] ?? []} />
                            ))
                        }
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid #374151', paddingTop: 8, marginTop: 4, fontWeight: 700, fontSize: 14 }}>
                            <span>Total Patrimonio</span>
                            <span style={{ fontFamily: 'monospace' }}>{fmt(totalPatri)}</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', borderTop: '2px solid #374151', paddingTop: 8, marginTop: 8, fontWeight: 700, fontSize: 14 }}>
                            <span>Total Pasivos + Patrimonio</span>
                            <span style={{ fontFamily: 'monospace' }}>{fmt(totalPasivo + totalPatri)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
