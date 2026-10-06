import { useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

// ─── GenerarForm ──────────────────────────────────────────────────────────────

function GenerarForm() {
    const { data, setData, post, processing, errors } = useForm({
        start_year: new Date().getFullYear(),
        years_count: 1,
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.accounting.periodos.generar'));
    }

    return (
        <div className="card" style={{ marginBottom: 24 }}>
            <h3 style={{ margin: '0 0 14px', fontSize: 15 }}>Generar períodos</h3>
            <form onSubmit={handleSubmit}>
                <div className="form-grid">
                    <label>
                        Año de inicio
                        <input
                            type="number"
                            value={data.start_year}
                            onChange={(e) => setData('start_year', Number(e.target.value))}
                            min={2000}
                            max={2100}
                            required
                        />
                        {errors.start_year && <p className="field-error">{errors.start_year}</p>}
                    </label>
                    <label>
                        Cantidad de años
                        <input
                            type="number"
                            value={data.years_count}
                            onChange={(e) => setData('years_count', Number(e.target.value))}
                            min={1}
                            max={10}
                            required
                        />
                        {errors.years_count && <p className="field-error">{errors.years_count}</p>}
                    </label>
                </div>
                <div className="form-actions" style={{ marginTop: 14 }}>
                    <button type="submit" className="btn" disabled={processing}>
                        {processing ? 'Generando…' : 'Generar períodos'}
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── YearBlock ────────────────────────────────────────────────────────────────

function YearBlock({ yearPeriod }) {
    function handleOpenYear() {
        if (!confirm(`¿Abrir el año ${yearPeriod.year}?`)) return;
        router.post(route('admin.accounting.periodos.anios.abrir', yearPeriod.id));
    }

    function handleCloseYear() {
        if (!confirm(`¿Cerrar el año ${yearPeriod.year}? Esta acción cerrará todos sus períodos abiertos.`)) return;
        router.post(route('admin.accounting.periodos.anios.cerrar', yearPeriod.id));
    }

    function handleOpenPeriod(periodId) {
        router.post(route('admin.accounting.periodos.abrir', periodId));
    }

    function handleClosePeriod(periodId) {
        if (!confirm('¿Cerrar este período?')) return;
        router.post(route('admin.accounting.periodos.cerrar', periodId));
    }

    return (
        <div className="card" style={{ marginBottom: 16 }}>
            {/* Encabezado del año */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <div>
                    <span style={{ fontWeight: 700, fontSize: 16 }}>Año {yearPeriod.year}</span>
                    <span
                        className={`badge ${yearPeriod.is_open ? 'active' : 'suspended'}`}
                        style={{ marginLeft: 10 }}
                    >
                        {yearPeriod.is_open ? 'Abierto' : 'Cerrado'}
                    </span>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    {!yearPeriod.is_open && (
                        <button type="button" className="btn btn-sm" style={{ fontSize: 13, padding: '6px 12px' }} onClick={handleOpenYear}>
                            Abrir año
                        </button>
                    )}
                    {yearPeriod.is_open && (
                        <button type="button" className="btn btn-sm danger" style={{ fontSize: 13, padding: '6px 12px' }} onClick={handleCloseYear}>
                            Cerrar año
                        </button>
                    )}
                </div>
            </div>

            {/* Tabla de meses */}
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    {(yearPeriod.periods ?? []).map((period) => (
                        <tr key={period.id}>
                            <td>{MONTHS[(period.month ?? 1) - 1] ?? `Mes ${period.month}`}</td>
                            <td>
                                <span className={`badge ${period.is_open ? 'active' : 'suspended'}`}>
                                    {period.is_open ? 'Abierto' : 'Cerrado'}
                                </span>
                            </td>
                            <td>
                                {!period.is_open ? (
                                    <button
                                        type="button"
                                        className="btn btn-sm"
                                        style={{ fontSize: 12, padding: '4px 10px' }}
                                        onClick={() => handleOpenPeriod(period.id)}
                                    >
                                        Abrir
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className="btn btn-sm danger"
                                        style={{ fontSize: 12, padding: '4px 10px' }}
                                        onClick={() => handleClosePeriod(period.id)}
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
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Periodos({ yearPeriods }) {
    return (
        <AppLayout>
            <Head title="Períodos contables" />

            <div className="top">
                <h1>Períodos contables</h1>
            </div>

            <GenerarForm />

            {yearPeriods.length === 0 && (
                <div className="card">
                    <p className="empty">No hay períodos generados. Use el formulario anterior para crearlos.</p>
                </div>
            )}

            {yearPeriods.map((yp) => (
                <YearBlock key={yp.id} yearPeriod={yp} />
            ))}
        </AppLayout>
    );
}
