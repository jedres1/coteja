import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const emptyWarehouse = {
    name: '',
    code: '',
    address: '',
    phone: '',
    contact_name: '',
    is_active: '1',
};

function WarehouseForm({ warehouse, onCancel }) {
    const isEditing = !!warehouse;
    const { data, setData, processing, errors, reset } = useForm(
        isEditing
            ? {
                name: warehouse.name || '',
                code: warehouse.code || '',
                address: warehouse.address || '',
                phone: warehouse.phone || '',
                contact_name: warehouse.contact_name || '',
                is_active: warehouse.is_active ? '1' : '0',
            }
            : { ...emptyWarehouse }
    );

    function handleSubmit(e) {
        e.preventDefault();
        const payload = isEditing ? { ...data, id: warehouse.id } : data;
        router.post(route('admin.inventory.warehouses.store'), payload, {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gap: 12 }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={100}
                        placeholder="Nombre de la bodega"
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
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                    {errors.is_active && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.is_active}</span>}
                </label>
                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Dirección
                    <input
                        type="text"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        maxLength={255}
                        placeholder="Dirección física"
                    />
                    {errors.address && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.address}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Teléfono
                    <input
                        type="text"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        maxLength={30}
                        placeholder="Teléfono de contacto"
                    />
                    {errors.phone && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.phone}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Persona de contacto
                    <input
                        type="text"
                        value={data.contact_name}
                        onChange={(e) => setData('contact_name', e.target.value)}
                        maxLength={100}
                        placeholder="Nombre del responsable"
                    />
                    {errors.contact_name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.contact_name}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : isEditing ? 'Actualizar bodega' : 'Guardar bodega'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function Warehouses({ warehouses }) {
    const [showForm, setShowForm] = useState(false);
    const [editingWarehouse, setEditingWarehouse] = useState(null);

    function handleEdit(w) {
        setEditingWarehouse(w);
        setShowForm(true);
    }

    function closeForm() {
        setShowForm(false);
        setEditingWarehouse(null);
    }

    // No delete route exists for warehouses

    return (
        <AppLayout>
            <Head title="Bodegas" />

            <div className="top">
                <h1>Bodegas</h1>
            </div>

            {showForm && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>
                        {editingWarehouse ? 'Editar bodega' : 'Nueva bodega'}
                    </h2>
                    <WarehouseForm warehouse={editingWarehouse} onCancel={closeForm} />
                </div>
            )}

            {!showForm && (
                <div style={{ marginBottom: 12 }}>
                    <button
                        type="button"
                        className="btn"
                        onClick={() => { setEditingWarehouse(null); setShowForm(true); }}
                    >
                        + Nueva bodega
                    </button>
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!warehouses || warehouses.length === 0) && (
                            <tr>
                                <td colSpan={7} className="empty">Sin bodegas registradas</td>
                            </tr>
                        )}
                        {warehouses && warehouses.map((w) => (
                            <tr key={w.id}>
                                <td style={{ fontWeight: 500 }}>{w.name}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 13 }}>{w.code}</td>
                                <td style={{ fontSize: 13, color: '#6b7280', maxWidth: 200 }}>{w.address || '—'}</td>
                                <td style={{ fontSize: 13 }}>{w.phone || '—'}</td>
                                <td style={{ fontSize: 13 }}>{w.contact_name || '—'}</td>
                                <td>
                                    <span style={{
                                        fontSize: 12,
                                        fontWeight: 600,
                                        padding: '2px 8px',
                                        borderRadius: 12,
                                        background: w.is_active ? '#f0fdf4' : '#f3f4f6',
                                        color: w.is_active ? '#15803d' : '#6b7280',
                                    }}>
                                        {w.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn secondary"
                                            style={{ fontSize: 12, padding: '3px 10px' }}
                                            onClick={() => handleEdit(w)}
                                        >
                                            Editar
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
