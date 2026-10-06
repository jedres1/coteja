import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = {
    pendiente: 'Pendiente',
    aprobado:  'Aprobado',
    anulado:   'Anulado',
};

const STATUS_BADGE = {
    pendiente: 'badge-warning',
    aprobado:  'active',
    anulado:   'suspended',
};

export default function Diario({ entries, filters, periods }) {
    const [search, setSearch]   = useState(filters?.search ?? '');
    const [status, setStatus]   = useState(filters?.status ?? '');
    const [periodId, setPeriodId] = useState(filters?.periodId ?? '');

    function applyFilters(newSearch, newStatus, newPeriod) {
        const params = {};
        if (newSearch)  params.search    = newSearch;
        if (newStatus)  params.status    = newStatus;
        if (newPeriod)  params.period_id = newPeriod;
        router.get(route('admin.accounting.diario.index'), params, { preserveState: true });
    }

    function handleSearch(e) {
        e.preventDefault();
        applyFilters(search, status, periodId);
    }

    function handleStatusChange(e) {
        const val = e.target.value;
        setStatus(val);
        applyFilters(search, val, periodId);
    }

    function handlePeriodChange(e) {
        const val = e.target.value;
        setPeriodId(val);
        applyFilters(search, status, val);
    }

    function handleDelete(entry) {
        if (!confirm(`¿Eliminar la partida "${entry.reference}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.accounting.diario.destroy', entry.id));
    }

    const hasFilters = !!(filters?.search || filters?.status || filters?.periodId);

    return (
        <AppLayout>
            <Head title="Diario contable" />

            <div className="top">
                <h1>Diario contable</h1>
                <Link href={route('admin.accounting.diario.create')} className="btn">
                    + Nueva partida
                </Link>
            </div>

            {/* Filtros */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Referencia o descripción…"
                        />
                    </label>
                    <label style={{ flex: '0 1 180px', margin: 0 }}>
                        Estado
                        <select value={status} onChange={handleStatusChange}>
                            <option value="">— Todos —</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </label>
                    <label style={{ flex: '0 1 220px', margin: 0 }}>
                        Período
                        <select value={periodId} onChange={handlePeriodChange}>
                            <option value="">— Todos los períodos —</option>
                            {periods.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                        <button type="submit" className="btn">Buscar</button>
                        {hasFilters && (
                            <Link href={route('admin.accounting.diario.index')} className="btn secondary">
                                Limpiar
                            </Link>
                        )}
                    </div>
                </form>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th style={{ textAlign: 'right' }}>Débito total</th>
                            <th style={{ textAlign: 'center' }}>Líneas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {entries.data.map((entry) => (
                            <tr key={entry.id}>
                                <td style={{ whiteSpace: 'nowrap' }}>
                                    {entry.date ? entry.date.substring(0, 10) : '—'}
                                </td>
                                <td style={{ fontFamily: 'monospace', fontWeight: 600 }}>
                                    {entry.reference}
                                </td>
                                <td style={{ maxWidth: 240 }}>{entry.description}</td>
                                <td>
                                    <span className={`badge ${STATUS_BADGE[entry.status] ?? ''}`}>
                                        {STATUS_LABELS[entry.status] ?? entry.status}
                                    </span>
                                </td>
                                <td style={{ textAlign: 'right' }}>{fmt(entry.total_debit)}</td>
                                <td style={{ textAlign: 'center' }}>{entry.lines_count ?? '—'}</td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <Link
                                            href={route('admin.accounting.diario.show', entry.id)}
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                        >
                                            Ver
                                        </Link>
                                        {entry.status === 'pendiente' && (
                                            <button
                                                type="button"
                                                className="btn btn-sm danger"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => handleDelete(entry)}
                                            >
                                                Eliminar
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={entries.links} />
            </div>
        </AppLayout>
    );
}
