import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function GenerarForm({ defaultYear }) {
    const [yearVal, setYearVal]   = useState(defaultYear);
    const [generating, setGenerating] = useState(false);

    function handleSubmit(e) {
        e.preventDefault();
        if (!confirm(`¿Generar los 12 períodos para el ejercicio ${yearVal}?`)) return;
        setGenerating(true);
        router.post(route('admin.accounting.periodos.generar'), { year: yearVal }, {
            onFinish: () => setGenerating(false),
        });
    }

    return (
        <div className="card" style={{ marginBottom: 20 }}>
            <h3 style={{ margin: '0 0 12px', fontSize: 15, fontWeight: 600 }}>Generar ejercicio contable</h3>
            <form onSubmit={handleSubmit} style={{ display: 'flex', gap: 12, alignItems: 'flex-end' }}>
                <label style={{ margin: 0 }}>
                    Año
                    <input
                        type="number"
                        value={yearVal}
                        onChange={(e) => setYearVal(Number(e.target.value))}
                        min={2020}
                        max={2100}
                        required
                        style={{ width: 110 }}
                    />
                </label>
                <button type="submit" className="btn" disabled={generating}>
                    {generating ? 'Generando…' : 'Generar'}
                </button>
            </form>
        </div>
    );
}

export default function Periodos({ yearPeriod, periods, year, availableYears }) {
    function handleYearChange(e) {
        router.get(route('admin.accounting.periodos'), { year: e.target.value });
    }

    function handleOpenYear() {
        if (!yearPeriod || !confirm(`¿Abrir el ejercicio ${year}?`)) return;
        router.post(route('admin.accounting.periodos.anios.abrir', yearPeriod.id));
    }

    function handleCloseYear() {
        if (!yearPeriod || !confirm(`¿Cerrar el ejercicio ${year}? Esto cerrará todos sus períodos abiertos.`)) return;
        router.post(route('admin.accounting.periodos.anios.cerrar', yearPeriod.id));
    }

    function handleOpenPeriod(period) {
        router.post(route('admin.accounting.periodos.abrir', period.id));
    }

    function handleClosePeriod(period) {
        if (!confirm(`¿Cerrar ${MONTHS[period.month - 1]} ${year}?`)) return;
        router.post(route('admin.accounting.periodos.cerrar', period.id));
    }

    const yearOpen = yearPeriod?.is_open ?? false;

    return (
        <AppLayout>
            <Head title="Períodos contables" />
            <div className="top">
                <h1>Períodos contables</h1>
            </div>

            <GenerarForm defaultYear={year} />

            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
                    <label style={{ margin: 0 }}>
                        Ejercicio
                        <select value={year} onChange={handleYearChange}>
                            {(availableYears ?? []).map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </label>

                    {yearPeriod ? (
                        <>
                            <span className={`badge ${yearOpen ? 'active' : 'suspended'}`}>
                                {yearOpen ? 'Ejercicio abierto' : 'Ejercicio cerrado'}
                            </span>
                            {yearOpen ? (
                                <button
                                    type="button"
                                    style={{ fontSize: 13, padding: '6px 12px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                    onClick={handleCloseYear}
                                >
                                    Cerrar ejercicio
                                </button>
                            ) : (
                                <button type="button" className="btn" style={{ fontSize: 13, padding: '6px 12px' }} onClick={handleOpenYear}>
                                    Abrir ejercicio
                                </button>
                            )}
                        </>
                    ) : (
                        <span style={{ fontSize: 13, color: '#6b7280' }}>
                            El ejercicio {year} no tiene períodos. Use el formulario de arriba para generarlos.
                        </span>
                    )}
                </div>
            </div>

            {(periods ?? []).length > 0 && (
                <div className="card table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.map((period) => (
                                <tr key={period.id}>
                                    <td style={{ fontWeight: 500 }}>
                                        {MONTHS[(period.month ?? 1) - 1] ?? `Mes ${period.month}`}
                                    </td>
                                    <td>
                                        <span className={`badge ${period.is_open ? 'active' : 'suspended'}`}>
                                            {period.is_open ? 'Abierto' : 'Cerrado'}
                                        </span>
                                    </td>
                                    <td>
                                        {!period.is_open ? (
                                            <button
                                                type="button"
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '4px 10px' }}
                                                onClick={() => handleOpenPeriod(period)}
                                                disabled={!yearOpen}
                                                title={!yearOpen ? 'Abra primero el ejercicio' : undefined}
                                            >
                                                Abrir
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                style={{ fontSize: 12, padding: '4px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                onClick={() => handleClosePeriod(period)}
                                            >
                                                Cerrar
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
