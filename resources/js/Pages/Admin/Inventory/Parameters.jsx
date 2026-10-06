import { Head, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

export default function InventoryParameters({ salesWarehouseId, warehouses, productTypes }) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        sales_warehouse_id: salesWarehouseId ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.inventory.parameters.save'));
    }

    return (
        <AppLayout>
            <Head title="Parámetros de inventario" />

            <div className="top">
                <h1>Parámetros de inventario</h1>
            </div>

            <div className="card" style={{ maxWidth: 560 }}>
                <form onSubmit={handleSubmit}>
                    <h2 style={{ marginTop: 0, marginBottom: 20, fontSize: 15, fontWeight: 600 }}>
                        Configuración general
                    </h2>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <label style={{ margin: 0 }}>
                            Bodega de ventas
                            <select
                                value={data.sales_warehouse_id}
                                onChange={(e) => setData('sales_warehouse_id', e.target.value)}
                            >
                                <option value="">— Sin bodega predeterminada —</option>
                                {warehouses && warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                            {errors.sales_warehouse_id && (
                                <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.sales_warehouse_id}</span>
                            )}
                            <span style={{ fontSize: 12, color: '#6b7280', marginTop: 4, display: 'block' }}>
                                Bodega desde la cual se realizan las salidas por ventas.
                            </span>
                        </label>

                    </div>

                    <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 24 }}>
                        <button type="submit" className="btn" disabled={processing}>
                            {processing ? 'Guardando...' : 'Guardar parámetros'}
                        </button>
                        {recentlySuccessful && (
                            <span style={{ color: '#16a34a', fontSize: 13 }}>Parámetros guardados.</span>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
