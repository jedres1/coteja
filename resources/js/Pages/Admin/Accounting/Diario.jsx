import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = { borrador: 'Borrador', aprobado: 'Aprobado', anulado: 'Anulado' };
const STATUS_STYLE  = {
    borrador: { background: '#fef3c7', color: '#92400e' },
    aprobado: { background: '#f0fdf4', color: '#15803d' },
    anulado:  { background: '#f1f5f9', color: '#64748b' },
};

export default function Diario({ entries, stats, search, status, from, to }) {
    const [searchVal, setSearchVal] = useState(search ?? '');
    const [statusVal, setStatusVal] = useState(status ?? '');
    const [fromVal, setFromVal]     = useState(from ?? '');
    const [toVal, setToVal]         = useState(to ?? '');

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.accounting.diario.index'), {
            search: searchVal || undefined,
            status: statusVal || undefined,
            from:   fromVal   || undefined,
            to:     toVal     || undefined,
        }, { preserveState: true });
    }

    function handleDelete(entry) {
        if (!confirm(`¿Eliminar el borrador ${entry.entry_number}? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.accounting.diario.destroy', entry.id));
    }

    return (
        <AppLayout>
            <Head title="Diario contable" />
            <div className="top">
                <h1>Diario contable</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.accounting.diario.export')} className="btn secondary">Exportar</a>
                    <Link href={route('admin.accounting.diario.create')} className="btn">+ Nueva partida</Link>
                </div>
            </div>

            {/* Estadísticas */}
            {stats && (
                <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
                    {[
                        { label: 'Borradores', value: stats.borrador, color: '#92400e' },
                        { label: 'Aprobados',  value: stats.aprobado, color: '#15803d' },
                        { label: 'Anulados',   value: stats.anulado,  color: '#64748b' },
                        { label: 'Total',      value: stats.total,    color: '#374151' },
                    ].map((s) => (
                        <div key={s.label} className="card" style={{ flex: 1, padding: '10px 14px', minWidth: 100 }}>
                            <div style={{ fontSize: 11, color: '#6b7280' }}>{s.label}</div>
                            <div style={{ fontWeight: 700, fontSize: 18, color: s.color }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            )}

            {/* Filtros */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={applyFilters} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 180px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            placeholder="N° asiento, descripción, referencia…"
                        />
                    </label>
                    <label style={{ margin: 0 }}>
                        Desde
                        <input type="date" value={fromVal} onChange={(e) => setFromVal(e.target.value)} />
                    </label>
                    <label style={{ margin: 0 }}>
                        Hasta
                        <input type="date" value={toVal} onChange={(e) => setToVal(e.target.value)} />
                    </label>
                    <label style={{ margin: 0 }}>
                        Estado
                        <select value={statusVal} onChange={(e) => setStatusVal(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="borrador">Borrador</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </label>
                    <button type="submit" className="btn">Filtrar</button>
                </form>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N° Asiento</th>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th style={{ textAlign: 'right' }}>Débito total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(entries?.data ?? []).length === 0 && (
                            <tr><td colSpan={6} className="empty">Sin registros en el período seleccionado</td></tr>
                        )}
                        {(entries?.data ?? []).map((entry) => (
                            <tr key={entry.id}>
                                <td style={{ fontFamily: 'monospace', fontWeight: 600 }}>{entry.entry_number}</td>
                                <td style={{ whiteSpace: 'nowrap', fontSize: 13 }}>
                                    {entry.entry_date ? String(entry.entry_date).substring(0, 10) : '—'}
                                </td>
                                <td style={{ maxWidth: 260, fontSize: 13 }}>{entry.description}</td>
                                <td>
                                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, ...(STATUS_STYLE[entry.status] ?? {}) }}>
                                        {STATUS_LABELS[entry.status] ?? entry.status}
                                    </span>
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {fmt((entry.lines ?? []).reduce((s, l) => s + Number(l.debit ?? 0), 0))}
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <Link
                                            href={route('admin.accounting.diario.show', entry.id)}
                                            className="btn secondary"
                                            style={{ fontSize: 12, padding: '3px 10px' }}
                                        >
                                            Ver
                                        </Link>
                                        {entry.status === 'borrador' && (
                                            <>
                                                <Link
                                                    href={route('admin.accounting.diario.edit', entry.id)}
                                                    className="btn secondary"
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                >
                                                    Editar
                                                </Link>
                                                <button
                                                    type="button"
                                                    style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                    onClick={() => handleDelete(entry)}
                                                >
                                                    Eliminar
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={entries?.links ?? []} />
            </div>
        </AppLayout>
    );
}
