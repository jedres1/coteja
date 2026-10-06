import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const emptyProductType = {
    name: '',
    code: '',
    controls_inventory: '1',
    is_active: '1',
};

function ProductTypeForm({ productType, onCancel }) {
    const isEditing = !!productType;
    const { data, setData, processing, errors, reset } = useForm(
        isEditing
            ? {
                name: productType.name || '',
                code: productType.code || '',
                controls_inventory: productType.controls_inventory ? '1' : '0',
                is_active: productType.is_active ? '1' : '0',
            }
            : { ...emptyProductType }
    );

    function handleSubmit(e) {
        e.preventDefault();
        const payload = isEditing ? { ...data, id: productType.id } : data;
        router.post(route('admin.inventory.product-types.store'), payload, {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr', gap: 12, alignItems: 'start' }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={100}
                        placeholder="Nombre del tipo"
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Código <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        required
                        maxLength={20}
                        placeholder="Código único"
                    />
                    {errors.code && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.code}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Controla inventario
                    <select
                        value={data.controls_inventory}
                        onChange={(e) => setData('controls_inventory', e.target.value)}
                    >
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                    {errors.controls_inventory && (
                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.controls_inventory}</span>
                    )}
                </label>
                <label style={{ margin: 0 }}>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    {errors.is_active && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.is_active}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : isEditing ? 'Actualizar tipo' : 'Guardar tipo'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function ProductTypes({ productTypes }) {
    const [showForm, setShowForm] = useState(false);
    const [editingType, setEditingType] = useState(null);

    function handleEdit(pt) {
        setEditingType(pt);
        setShowForm(true);
    }

    function closeForm() {
        setShowForm(false);
        setEditingType(null);
    }

    function handleDelete(id) {
        if (!confirm('¿Eliminar este tipo de producto?')) return;
        router.delete(route('admin.inventory.product-types.destroy', id));
    }

    return (
        <AppLayout>
            <Head title="Tipos de producto" />

            <div className="top">
                <h1>Tipos de producto</h1>
            </div>

            {showForm && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>
                        {editingType ? 'Editar tipo de producto' : 'Nuevo tipo de producto'}
                    </h2>
                    <ProductTypeForm productType={editingType} onCancel={closeForm} />
                </div>
            )}

            {!showForm && (
                <div style={{ marginBottom: 12 }}>
                    <button
                        type="button"
                        className="btn"
                        onClick={() => { setEditingType(null); setShowForm(true); }}
                    >
                        + Nuevo tipo
                    </button>
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Inventario</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!productTypes || productTypes.length === 0) && (
                            <tr>
                                <td colSpan={5} className="empty">Sin tipos de producto registrados</td>
                            </tr>
                        )}
                        {productTypes && productTypes.map((pt) => (
                            <tr key={pt.id}>
                                <td style={{ fontWeight: 500 }}>{pt.name}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 13 }}>{pt.code}</td>
                                <td>
                                    <span style={{
                                        fontSize: 12,
                                        fontWeight: 600,
                                        padding: '2px 8px',
                                        borderRadius: 12,
                                        background: pt.controls_inventory ? '#eff6ff' : '#f3f4f6',
                                        color: pt.controls_inventory ? '#1d4ed8' : '#6b7280',
                                    }}>
                                        {pt.controls_inventory ? 'Sí' : 'No'}
                                    </span>
                                </td>
                                <td>
                                    <span style={{
                                        fontSize: 12,
                                        fontWeight: 600,
                                        padding: '2px 8px',
                                        borderRadius: 12,
                                        background: pt.is_active ? '#f0fdf4' : '#f3f4f6',
                                        color: pt.is_active ? '#15803d' : '#6b7280',
                                    }}>
                                        {pt.is_active ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn secondary"
                                            style={{ fontSize: 12, padding: '3px 10px' }}
                                            onClick={() => handleEdit(pt)}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                            onClick={() => handleDelete(pt.id)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
