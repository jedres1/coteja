import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const TYPE_CONFIG = {
    entry: { label: 'Entrada', background: '#f0fdf4', color: '#15803d' },
    exit: { label: 'Salida', background: '#fef2f2', color: '#dc2626' },
};

const emptyMovement = {
    product_id:          '',
    warehouse_id:        '',
    type:                'entry',
    quantity:            '',
    document_type:       'manual',
    purchase_invoice_id: '',
    document_number:     '',
    notes:               '',
};

function MovementForm({ products, warehouses, approvedPurchases, onCancel }) {
    const { data, setData, processing, errors, reset } = useForm({ ...emptyMovement });
    const isPurchaseEntry = data.type === 'entry' && data.document_type === 'purchase';

    function handleSubmit(e) {
        e.preventDefault();
        router.post(route('admin.inventory.movements.store'), data, {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr', gap: 12 }}>
                <label style={{ margin: 0 }}>
                    Producto <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.product_id} onChange={(e) => setData('product_id', e.target.value)} required>
                        <option value="">— Seleccionar producto —</option>
                        {products.map((p) => (
                            <option key={p.id} value={p.id}>{p.code ? `${p.code} - ` : ''}{p.name}</option>
                        ))}
                    </select>
                    {errors.product_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.product_id}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Bodega <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.warehouse_id} onChange={(e) => setData('warehouse_id', e.target.value)} required>
                        <option value="">— Seleccionar bodega —</option>
                        {warehouses.map((w) => (
                            <option key={w.id} value={w.id}>{w.name}</option>
                        ))}
                    </select>
                    {errors.warehouse_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.warehouse_id}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select
                        value={data.type}
                        onChange={(e) => {
                            const t = e.target.value;
                            setData({ ...data, type: t, document_type: t === 'exit' ? 'manual' : data.document_type, purchase_invoice_id: '' });
                        }}
                    >
                        <option value="entry">Entrada</option>
                        <option value="exit">Salida</option>
                    </select>
                    {errors.type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.type}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Cantidad <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="number"
                        value={data.quantity}
                        onChange={(e) => setData('quantity', e.target.value)}
                        min="0"
                        step="0.001"
                        required
                        placeholder="0"
                    />
                    {errors.quantity && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.quantity}</span>}
                </label>

                {data.type === 'entry' && (
                    <label style={{ margin: 0 }}>
                        Origen <span style={{ color: '#ef4444' }}>*</span>
                        <select
                            value={data.document_type}
                            onChange={(e) => setData({ ...data, document_type: e.target.value, purchase_invoice_id: '' })}
                        >
                            <option value="manual">Manual</option>
                            <option value="purchase">Factura de compra</option>
                        </select>
                        {errors.document_type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.document_type}</span>}
                    </label>
                )}

                {isPurchaseEntry ? (
                    <label style={{ margin: 0, gridColumn: 'span 3' }}>
                        Factura de compra <span style={{ color: '#ef4444' }}>*</span>
                        <select value={data.purchase_invoice_id} onChange={(e) => setData('purchase_invoice_id', e.target.value)} required>
                            <option value="">— Seleccionar factura —</option>
                            {(approvedPurchases ?? []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.invoice_number} — {p.supplier?.name ?? '—'}
                                </option>
                            ))}
                        </select>
                        {errors.purchase_invoice_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.purchase_invoice_id}</span>}
                    </label>
                ) : (
                    <label style={{ margin: 0, gridColumn: data.type === 'entry' ? 'span 3' : '1 / -1' }}>
                        Número de documento
                        <input
                            type="text"
                            value={data.document_number}
                            onChange={(e) => setData('document_number', e.target.value)}
                            maxLength={100}
                            placeholder="Número"
                        />
                        {errors.document_number && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.document_number}</span>}
                    </label>
                )}

                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Notas
                    <input
                        type="text"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        maxLength={255}
                        placeholder="Observaciones opcionales"
                    />
                    {errors.notes && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.notes}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Registrando...' : 'Registrar movimiento'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function Movements({ movements, inventoryProducts, warehouses, approvedPurchases }) {
    const products = inventoryProducts ?? [];
    const [showForm, setShowForm] = useState(false);

    return (
        <AppLayout>
            <Head title="Movimientos de inventario" />

            <div className="top">
                <h1>Movimientos de inventario</h1>
            </div>

            {showForm && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>Nuevo movimiento</h2>
                    <MovementForm
                        products={products}
                        warehouses={warehouses}
                        approvedPurchases={approvedPurchases ?? []}
                        onCancel={() => setShowForm(false)}
                    />
                </div>
            )}

            {!showForm && (
                <div style={{ marginBottom: 12 }}>
                    <button type="button" className="btn" onClick={() => setShowForm(true)}>
                        + Nuevo movimiento
                    </button>
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Bodega</th>
                            <th style={{ textAlign: 'right' }}>Cantidad</th>
                            <th style={{ textAlign: 'right' }}>Costo unit.</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th>Documento</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!movements?.data || movements.data.length === 0) && (
                            <tr>
                                <td colSpan={9} className="empty">Sin movimientos registrados</td>
                            </tr>
                        )}
                        {movements?.data?.map((mov) => {
                            const typeConf = TYPE_CONFIG[mov.type] || { label: mov.type, background: '#f3f4f6', color: '#374151' };
                            return (
                                <tr key={mov.id}>
                                    <td style={{ whiteSpace: 'nowrap' }}>{mov.date}</td>
                                    <td>
                                        <span style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            padding: '2px 8px',
                                            borderRadius: 12,
                                            background: typeConf.background,
                                            color: typeConf.color,
                                        }}>
                                            {typeConf.label}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ fontWeight: 500 }}>{mov.product?.name || '—'}</div>
                                        {mov.product?.code && (
                                            <div style={{ fontSize: 12, color: '#6b7280', fontFamily: 'monospace' }}>{mov.product.code}</div>
                                        )}
                                    </td>
                                    <td>{mov.warehouse?.name || '—'}</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {mov.quantity != null ? Number(mov.quantity).toLocaleString('es-SV') : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {mov.unit_cost != null ? fmt(mov.unit_cost) : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>
                                        {mov.total_cost != null ? fmt(mov.total_cost) : '—'}
                                    </td>
                                    <td style={{ fontSize: 13 }}>
                                        {mov.document_type && <span>{mov.document_type}</span>}
                                        {mov.document_number && (
                                            <span style={{ color: '#6b7280' }}> #{mov.document_number}</span>
                                        )}
                                        {!mov.document_type && !mov.document_number && '—'}
                                    </td>
                                    <td style={{ fontSize: 13, color: '#6b7280', maxWidth: 200 }}>
                                        {mov.notes || '—'}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {movements?.links && <Pagination links={movements.links} />}
        </AppLayout>
    );
}
