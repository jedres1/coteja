import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import { IconDownload } from '@/Components/Icons';

const MESES = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
];

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function FilterBar({ year, month }) {
    const [y, setY] = useState(year);
    const [m, setM] = useState(month);

    function apply() {
        router.get(route('admin.accounting.reportes-fiscales'), { year: y, month: m }, { preserveState: false });
    }

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

    return (
        <div className="card" style={{ marginBottom: 16, display: 'flex', gap: 12, alignItems: 'flex-end', flexWrap: 'wrap' }}>
            <div className="field" style={{ margin: 0 }}>
                <label>Año</label>
                <select value={y} onChange={(e) => setY(Number(e.target.value))}>
                    {years.map((yr) => <option key={yr} value={yr}>{yr}</option>)}
                </select>
            </div>
            <div className="field" style={{ margin: 0 }}>
                <label>Mes</label>
                <select value={m} onChange={(e) => setM(Number(e.target.value))}>
                    {MESES.map((nm, i) => <option key={i + 1} value={i + 1}>{nm}</option>)}
                </select>
            </div>
            <button type="button" className="btn" onClick={apply}>
                Aplicar filtro
            </button>
        </div>
    );
}

function DownloadBtn({ href }) {
    return (
        <a href={href} className="btn secondary" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none' }}>
            <IconDownload />
            Descargar CSV
        </a>
    );
}

function LibroVentas({ ventas, year, month }) {
    const exportUrl = route('admin.accounting.reportes-fiscales.ventas.exportar') + `?year=${year}&month=${month}`;

    const totExento  = ventas.reduce((s, r) => s + Number(r.monto_exento),  0);
    const totNoSuj   = ventas.reduce((s, r) => s + Number(r.monto_no_suj),  0);
    const totGravado = ventas.reduce((s, r) => s + Number(r.monto_gravado), 0);
    const totDebito  = ventas.reduce((s, r) => s + Number(r.debito_fiscal), 0);
    const totTotal   = ventas.reduce((s, r) => s + Number(r.total),         0);

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <div>
                    <strong>F-07 — Libro de Ventas</strong>
                    <span className="muted" style={{ marginLeft: 12, fontSize: 13 }}>
                        {ventas.length} registro{ventas.length !== 1 ? 's' : ''} — {MESES[month - 1]} {year}
                    </span>
                </div>
                <DownloadBtn href={exportUrl} />
            </div>

            <div className="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Fecha</th>
                            <th>Tipo DTE</th>
                            <th>N° Control</th>
                            <th>Receptor</th>
                            <th>NRC</th>
                            <th>NIT/Doc</th>
                            <th style={{ textAlign: 'right' }}>Exento</th>
                            <th style={{ textAlign: 'right' }}>No Sujeto</th>
                            <th style={{ textAlign: 'right' }}>Gravado</th>
                            <th style={{ textAlign: 'right' }}>Déb. Fiscal</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {ventas.length === 0 && (
                            <tr><td colSpan={12} className="empty">Sin ventas registradas en este período</td></tr>
                        )}
                        {ventas.map((r) => (
                            <tr key={r.correlativo}>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.correlativo}</td>
                                <td style={{ fontSize: 12 }}>{r.fecha}</td>
                                <td><span style={{ fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 10, background: '#f3f4f6', color: '#374151' }}>{r.tipo_dte}</span></td>
                                <td style={{ fontFamily: 'monospace', fontSize: 11 }}>{r.numero_control}</td>
                                <td style={{ maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.receptor_nombre}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.receptor_nrc || <span className="muted">—</span>}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.receptor_nit || <span className="muted">—</span>}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_exento)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_no_suj)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_gravado)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.debito_fiscal)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13, fontWeight: 600 }}>{fmt(r.total)}</td>
                            </tr>
                        ))}
                    </tbody>
                    {ventas.length > 0 && (
                        <tfoot>
                            <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                                <td colSpan={7} style={{ textAlign: 'right', paddingRight: 12 }}>Totales</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totExento)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totNoSuj)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totGravado)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totDebito)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totTotal)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </div>
    );
}

function LibroCompras({ compras, year, month }) {
    const exportUrl = route('admin.accounting.reportes-fiscales.compras.exportar') + `?year=${year}&month=${month}`;

    const totExento  = compras.reduce((s, r) => s + Number(r.monto_exento),  0);
    const totNoSuj   = compras.reduce((s, r) => s + Number(r.monto_no_suj),  0);
    const totGravado = compras.reduce((s, r) => s + Number(r.monto_gravado), 0);
    const totCredito = compras.reduce((s, r) => s + Number(r.credito_fiscal),0);
    const totTotal   = compras.reduce((s, r) => s + Number(r.total),         0);

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <div>
                    <strong>F-14 — Libro de Compras</strong>
                    <span className="muted" style={{ marginLeft: 12, fontSize: 13 }}>
                        {compras.length} registro{compras.length !== 1 ? 's' : ''} — {MESES[month - 1]} {year}
                    </span>
                </div>
                <DownloadBtn href={exportUrl} />
            </div>

            <div className="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Fecha</th>
                            <th>Tipo DTE</th>
                            <th>N° Documento</th>
                            <th>Proveedor</th>
                            <th>NRC</th>
                            <th>NIT/Doc</th>
                            <th style={{ textAlign: 'right' }}>Exento</th>
                            <th style={{ textAlign: 'right' }}>No Sujeto</th>
                            <th style={{ textAlign: 'right' }}>Gravado</th>
                            <th style={{ textAlign: 'right' }}>Créd. Fiscal</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {compras.length === 0 && (
                            <tr><td colSpan={12} className="empty">Sin compras registradas en este período</td></tr>
                        )}
                        {compras.map((r) => (
                            <tr key={r.correlativo}>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.correlativo}</td>
                                <td style={{ fontSize: 12 }}>{r.fecha}</td>
                                <td><span style={{ fontSize: 11, fontWeight: 600, padding: '2px 7px', borderRadius: 10, background: '#f3f4f6', color: '#374151' }}>{r.tipo_dte}</span></td>
                                <td style={{ fontFamily: 'monospace', fontSize: 11 }}>{r.numero_control}</td>
                                <td style={{ maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.proveedor_nombre}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.proveedor_nrc || <span className="muted">—</span>}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{r.proveedor_nit || <span className="muted">—</span>}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_exento)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_no_suj)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.monto_gravado)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 12 }}>{fmt(r.credito_fiscal)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13, fontWeight: 600 }}>{fmt(r.total)}</td>
                            </tr>
                        ))}
                    </tbody>
                    {compras.length > 0 && (
                        <tfoot>
                            <tr style={{ fontWeight: 700, background: '#f9fafb' }}>
                                <td colSpan={7} style={{ textAlign: 'right', paddingRight: 12 }}>Totales</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totExento)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totNoSuj)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totGravado)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totCredito)}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(totTotal)}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </div>
    );
}

export default function ReportesFiscales({ ventas, compras, year, month }) {
    const [tab, setTab] = useState('ventas');

    return (
        <AppLayout>
            <Head title="Reportes Fiscales" />

            <div className="top">
                <h1>Reportes Fiscales</h1>
            </div>

            <FilterBar year={year} month={month} />

            <div className="card" style={{ marginBottom: 12, display: 'flex', gap: 8 }}>
                <button
                    type="button"
                    className={`btn ${tab === 'ventas' ? '' : 'secondary'}`}
                    onClick={() => setTab('ventas')}
                >
                    F-07 Libro de Ventas
                </button>
                <button
                    type="button"
                    className={`btn ${tab === 'compras' ? '' : 'secondary'}`}
                    onClick={() => setTab('compras')}
                >
                    F-14 Libro de Compras
                </button>
            </div>

            <div className="card">
                {tab === 'ventas' && <LibroVentas ventas={ventas} year={year} month={month} />}
                {tab === 'compras' && <LibroCompras compras={compras} year={year} month={month} />}
            </div>
        </AppLayout>
    );
}
