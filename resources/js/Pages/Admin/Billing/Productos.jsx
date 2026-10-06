import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

const UNIT_OPTIONS = [
    { code: '59', label: 'Unidad' },
    { code: '11', label: 'Kilogramo' },
    { code: '14', label: 'Gramo' },
    { code: '22', label: 'Metro' },
    { code: '23', label: 'Metro cuadrado' },
    { code: '26', label: 'Metro cúbico' },
    { code: '29', label: 'Litro' },
    { code: '58', label: 'Docena' },
    { code: '99', label: 'Otros' },
];

const TYPE_OPTIONS = [
    { value: '1', label: 'Bien' },
    { value: '2', label: 'Servicio' },
    { value: '3', label: 'Ambos', inventoryOnly: true },
    { value: '4', label: 'Otros', inventoryOnly: true },
];

const TYPE_LABEL = { '1': 'Bien', '2': 'Servicio', '3': 'Ambos', '4': 'Otros' };

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

function emptyForm(hasInventory) {
    return {
        id: null,
        code: '',
        description: '',
        type: '2',
        price: '',
        unit: '59',
        isExempt: false,
        notes: '',
        stockQuantity: '',
        minStock: '',
        productTypeId: '',
        _hasInventory: hasInventory,
    };
}

function ProductForm({ initial, productTypes, hasInventory, onClose }) {
    const form = useForm(initial);
    const showStock = ['1', '3'].includes(form.data.type) && hasInventory;

    function handleSubmit(e) {
        e.preventDefault();
        const payload = {
            id: form.data.id || undefined,
            code: form.data.code,
            description: form.data.description,
            type: form.data.type,
            price: form.data.price,
            unit: form.data.unit,
            isExempt: form.data.isExempt,
            notes: form.data.notes || null,
            stockQuantity: showStock && form.data.stockQuantity !== '' ? form.data.stockQuantity : null,
            minStock: showStock && form.data.minStock !== '' ? form.data.minStock : null,
            productTypeId: form.data.productTypeId || null,
        };
        form.transform(() => payload).post(route('admin.factura-sv.productos.guardar'), { onSuccess: onClose });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                <div className="field" style={{ margin: 0 }}>
                    <label>Código <span style={{ color: '#ef4444' }}>*</span></label>
                    <input
                        type="text"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        placeholder="PROD-001"
                        required
                    />
                    {form.errors.code && <p className="field-error">{form.errors.code}</p>}
                </div>

                <div className="field" style={{ margin: 0 }}>
                    <label>Tipo DTE <span style={{ color: '#ef4444' }}>*</span></label>
                    <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {TYPE_OPTIONS.filter((o) => !o.inventoryOnly || hasInventory).map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                    {form.errors.type && <p className="field-error">{form.errors.type}</p>}
                </div>

                <div className="field" style={{ margin: 0, gridColumn: '1 / -1' }}>
                    <label>Descripción <span style={{ color: '#ef4444' }}>*</span></label>
                    <input
                        type="text"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        required
                    />
                    {form.errors.description && <p className="field-error">{form.errors.description}</p>}
                </div>

                <div className="field" style={{ margin: 0 }}>
                    <label>Precio <span style={{ color: '#ef4444' }}>*</span></label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={form.data.price}
                        onChange={(e) => form.setData('price', e.target.value)}
                        required
                    />
                    {form.errors.price && <p className="field-error">{form.errors.price}</p>}
                </div>

                <div className="field" style={{ margin: 0 }}>
                    <label>Unidad <span style={{ color: '#ef4444' }}>*</span></label>
                    <select value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)}>
                        {UNIT_OPTIONS.map((u) => (
                            <option key={u.code} value={u.code}>{u.code} - {u.label}</option>
                        ))}
                    </select>
                    {form.errors.unit && <p className="field-error">{form.errors.unit}</p>}
                </div>

                <div className="field" style={{ margin: 0 }}>
                    <label>IVA</label>
                    <select
                        value={form.data.isExempt ? 'true' : 'false'}
                        onChange={(e) => form.setData('isExempt', e.target.value === 'true')}
                    >
                        <option value="false">Gravado (13%)</option>
                        <option value="true">Exento</option>
                    </select>
                </div>

                {hasInventory && productTypes.length > 0 && (
                    <div className="field" style={{ margin: 0 }}>
                        <label>Tipo de producto</label>
                        <select
                            value={form.data.productTypeId}
                            onChange={(e) => form.setData('productTypeId', e.target.value)}
                        >
                            <option value="">Sin clasificar</option>
                            {productTypes.map((pt) => (
                                <option key={pt.id} value={pt.id}>
                                    {pt.name}{pt.controlsInventory ? ' (stock)' : ''}
                                </option>
                            ))}
                        </select>
                        {form.errors.productTypeId && <p className="field-error">{form.errors.productTypeId}</p>}
                    </div>
                )}

                <div className="field" style={{ margin: 0, gridColumn: '1 / -1' }}>
                    <label>Notas</label>
                    <input
                        type="text"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                </div>

                {showStock && (
                    <>
                        <div className="field" style={{ margin: 0 }}>
                            <label>Stock actual</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={form.data.stockQuantity}
                                onChange={(e) => form.setData('stockQuantity', e.target.value)}
                                placeholder="Ej: 100"
                            />
                            <small style={{ color: '#6b7280', fontSize: 11 }}>Unidades en inventario</small>
                        </div>
                        <div className="field" style={{ margin: 0 }}>
                            <label>Stock mínimo</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={form.data.minStock}
                                onChange={(e) => form.setData('minStock', e.target.value)}
                                placeholder="Ej: 10"
                            />
                            <small style={{ color: '#6b7280', fontSize: 11 }}>Alerta al llegar a este nivel</small>
                        </div>
                    </>
                )}
            </div>

            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar producto'}
                </button>
            </div>
        </form>
    );
}

export default function Productos({ products, productTypes, hasInventory }) {
    const [search, setSearch]       = useState('');
    const [modal, setModal]         = useState(null); // null | { mode: 'create' | 'edit', product?: {} }

    const filtered = products.filter((p) => {
        const q = search.toLowerCase();
        return !q || p.code?.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q);
    });

    function openCreate() {
        setModal({ mode: 'create' });
    }

    function openEdit(product) {
        setModal({ mode: 'edit', product });
    }

    function handleDelete(product) {
        if (!confirm(`¿Eliminar "${product.description}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.factura-sv.billing.productos.destroy', product.id));
    }

    const formInitial = modal?.mode === 'edit' && modal.product
        ? {
            id: modal.product.id,
            code: modal.product.code ?? '',
            description: modal.product.description ?? '',
            type: modal.product.type ?? '2',
            price: modal.product.price ?? '',
            unit: modal.product.unit ?? '59',
            isExempt: modal.product.isExempt ?? false,
            notes: modal.product.notes ?? '',
            stockQuantity: modal.product.stockQuantity ?? '',
            minStock: modal.product.minStock ?? '',
            productTypeId: modal.product.productTypeId ? String(modal.product.productTypeId) : '',
        }
        : emptyForm(hasInventory);

    const lowStock = (p) => hasInventory && ['1', '3'].includes(p.type)
        && p.stockQuantity != null && p.minStock != null
        && Number(p.stockQuantity) <= Number(p.minStock);

    return (
        <AppLayout>
            <Head title="Productos / Servicios" />

            <div className="top">
                <h1>Productos / Servicios</h1>
                <button type="button" className="btn" onClick={openCreate}>
                    Nuevo producto
                </button>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar por código o descripción…"
                    style={{ maxWidth: 320 }}
                />
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Tipo DTE</th>
                            <th style={{ textAlign: 'right' }}>Precio</th>
                            <th>Unidad</th>
                            <th>IVA</th>
                            {hasInventory && <th style={{ textAlign: 'right' }}>Stock</th>}
                            {hasInventory && <th style={{ textAlign: 'right' }}>Mín.</th>}
                            {hasInventory && <th>Tipo</th>}
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={hasInventory ? 10 : 7} className="empty">
                                    {search ? 'Sin resultados para la búsqueda' : 'Sin productos registrados'}
                                </td>
                            </tr>
                        )}
                        {filtered.map((p) => (
                            <tr key={p.id} style={lowStock(p) ? { background: '#fef9c3' } : undefined}>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{p.code}</td>
                                <td>
                                    <div style={{ fontWeight: 500 }}>{p.description}</div>
                                    {p.notes && <div style={{ fontSize: 11, color: '#6b7280' }}>{p.notes}</div>}
                                </td>
                                <td>
                                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10, background: '#f3f4f6', color: '#374151' }}>
                                        {TYPE_LABEL[p.type] ?? p.type}
                                    </span>
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(p.price)}</td>
                                <td style={{ fontSize: 12 }}>
                                    {UNIT_OPTIONS.find((u) => u.code === p.unit)?.label ?? p.unit}
                                </td>
                                <td style={{ fontSize: 12 }}>
                                    {p.isExempt
                                        ? <span style={{ color: '#6b7280' }}>Exento</span>
                                        : <span style={{ color: '#15803d' }}>Gravado</span>}
                                </td>
                                {hasInventory && (
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                        {p.stockQuantity != null ? (
                                            <span style={lowStock(p) ? { color: '#dc2626', fontWeight: 700 } : undefined}>
                                                {p.stockQuantity}
                                                {lowStock(p) && ' ⚠'}
                                            </span>
                                        ) : <span className="muted">—</span>}
                                    </td>
                                )}
                                {hasInventory && (
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                        {p.minStock != null ? p.minStock : <span className="muted">—</span>}
                                    </td>
                                )}
                                {hasInventory && (
                                    <td style={{ fontSize: 12 }}>{p.productTypeName ?? <span className="muted">—</span>}</td>
                                )}
                                <td>
                                    <button
                                        type="button"
                                        className="btn secondary"
                                        style={{ fontSize: 12, padding: '3px 10px' }}
                                        onClick={() => openEdit(p)}
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modal && (
                <Modal
                    title={modal.mode === 'edit' ? `Editar producto` : 'Nuevo producto'}
                    onClose={() => setModal(null)}
                    maxWidth={560}
                >
                    <ProductForm
                        key={modal.mode === 'edit' ? modal.product?.id : 'new'}
                        initial={formInitial}
                        productTypes={productTypes}
                        hasInventory={hasInventory}
                        onClose={() => setModal(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
