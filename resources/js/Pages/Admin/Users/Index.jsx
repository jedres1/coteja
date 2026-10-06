import { useState, useEffect } from 'react';
import { Head, useForm, router, Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

const roles = { admin: 'Administrador', customer: 'Cliente', consultant: 'Consultor' };
const roleBadges = { admin: 'role-admin', customer: 'role-customer', consultant: 'role-consultant' };

// ─── UserForm ────────────────────────────────────────────────────────────────

function UserForm({ user, modules, customers, companies, onSuccess, onCancel }) {
    const isEdit = !!user;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name:           user?.name ?? '',
        email:          user?.email ?? '',
        password:       '',
        role:           user?.role ?? 'admin',
        customer_id:    user?.customer?.id ? String(user.customer.id) : '',
        is_active:      user?.is_active !== undefined ? String(Number(user.is_active)) : '1',
        module_accesses: user?.module_accesses ?? [],
        company_ids:    user?.accessibleCompanies?.map((c) => c.id) ?? [],
    });

    const [filteredCompanies, setFilteredCompanies] = useState([]);

    // Derived: show customer fields only when role === 'customer'
    const showCustomerFields = data.role === 'customer';

    // When role changes away from customer, clear customer-specific fields
    useEffect(() => {
        if (data.role !== 'customer') {
            setData((prev) => ({ ...prev, customer_id: '', module_accesses: [], company_ids: [] }));
            setFilteredCompanies([]);
        }
    }, [data.role]);

    // When customer_id changes, filter companies and uncheck companies from other customers
    useEffect(() => {
        if (!data.customer_id) {
            setFilteredCompanies([]);
            setData((prev) => ({ ...prev, company_ids: [] }));
            return;
        }
        const cid = Number(data.customer_id);
        const filtered = companies.filter((c) => c.customer_id === cid);
        setFilteredCompanies(filtered);
        // Uncheck companies that no longer belong to selected customer
        const validIds = filtered.map((c) => c.id);
        setData((prev) => ({
            ...prev,
            company_ids: prev.company_ids.filter((id) => validIds.includes(id)),
        }));
    }, [data.customer_id]);

    function toggleModule(key) {
        setData('module_accesses', data.module_accesses.includes(key)
            ? data.module_accesses.filter((m) => m !== key)
            : [...data.module_accesses, key]);
    }

    function toggleCompany(id) {
        setData('company_ids', data.company_ids.includes(id)
            ? data.company_ids.filter((c) => c !== id)
            : [...data.company_ids, id]);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.users.update', user.id), { onSuccess });
        } else {
            post(route('admin.users.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                {/* Name */}
                <label className="full-width">
                    Nombre
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                {/* Email */}
                <label>
                    Correo electrónico
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    {errors.email && <p className="field-error">{errors.email}</p>}
                </label>

                {/* Password */}
                <label>
                    Contraseña
                    <input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder={isEdit ? 'Dejar en blanco para no cambiar' : ''}
                        autoComplete="new-password"
                    />
                    {errors.password && <p className="field-error">{errors.password}</p>}
                </label>

                {/* Role */}
                <label>
                    Rol
                    <select value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {Object.entries(roles).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.role && <p className="field-error">{errors.role}</p>}
                </label>

                {/* Estado */}
                <label>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    {errors.is_active && <p className="field-error">{errors.is_active}</p>}
                </label>

                {/* Customer fields – visible only when role === 'customer' */}
                {showCustomerFields && (
                    <>
                        <label className="full-width">
                            Cliente vinculado
                            <select
                                value={data.customer_id}
                                onChange={(e) => setData('customer_id', e.target.value)}
                            >
                                <option value="">— Seleccionar cliente —</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={String(c.id)}>{c.name}</option>
                                ))}
                            </select>
                            {errors.customer_id && <p className="field-error">{errors.customer_id}</p>}
                        </label>

                        {/* Module accesses */}
                        <div className="full-width">
                            <p style={{ margin: '0 0 8px', fontSize: 13, fontWeight: 600, color: '#374151' }}>
                                Módulos habilitados
                            </p>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                {Object.entries(modules).map(([key, label]) => (
                                    <label key={key} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, fontWeight: 400 }}>
                                        <input
                                            type="checkbox"
                                            style={{ width: 'auto' }}
                                            checked={data.module_accesses.includes(key)}
                                            onChange={() => toggleModule(key)}
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            {errors.module_accesses && <p className="field-error">{errors.module_accesses}</p>}
                        </div>

                        {/* Company checkboxes – filtered by selected customer */}
                        {data.customer_id && filteredCompanies.length > 0 && (
                            <div className="full-width">
                                <p style={{ margin: '0 0 8px', fontSize: 13, fontWeight: 600, color: '#374151' }}>
                                    Empresas accesibles
                                </p>
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                    {filteredCompanies.map((company) => (
                                        <label key={company.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, fontWeight: 400 }}>
                                            <input
                                                type="checkbox"
                                                style={{ width: 'auto' }}
                                                checked={data.company_ids.includes(company.id)}
                                                onChange={() => toggleCompany(company.id)}
                                            />
                                            {company.business_name}
                                        </label>
                                    ))}
                                </div>
                                {errors.company_ids && <p className="field-error">{errors.company_ids}</p>}
                            </div>
                        )}

                        {data.customer_id && filteredCompanies.length === 0 && (
                            <p className="muted full-width" style={{ margin: 0 }}>
                                Este cliente no tiene empresas registradas.
                            </p>
                        )}
                    </>
                )}
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>
                    Cancelar
                </button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear usuario'}
                </button>
            </div>
        </form>
    );
}

// ─── Index page ───────────────────────────────────────────────────────────────

export default function UsersIndex({ users, search, role, modules, customers, companies }) {
    const { auth } = usePage().props;
    const currentUserId = auth?.user?.id;

    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    // Local state for search form
    const [searchVal, setSearchVal] = useState(search ?? '');
    const [roleVal, setRoleVal] = useState(role ?? '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('admin.users.index'), { search: searchVal, role: roleVal }, { preserveState: true });
    }

    function handleDelete(user) {
        if (!confirm(`¿Eliminar al usuario "${user.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.users.destroy', user.id), {
            onSuccess: () => {},
        });
    }

    const hasFilter = !!(search || role);

    return (
        <AppLayout>
            <Head title="Usuarios y acceso" />

            <style>{`
                .role-admin    { background: #dbeafe; color: #1e40af; }
                .role-customer { background: #dcfce7; color: #166534; }
                .role-consultant { background: #fef3c7; color: #92400e; }
            `}</style>

            <div className="top">
                <h1>Usuarios y acceso</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nuevo usuario
                </button>
            </div>

            {/* Search / filter bar */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 180px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            placeholder="Nombre o correo…"
                        />
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Rol
                        <select value={roleVal} onChange={(e) => setRoleVal(e.target.value)}>
                            <option value="">Todos los roles</option>
                            {Object.entries(roles).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                        <button type="submit" className="btn">Buscar</button>
                        {hasFilter && (
                            <Link
                                href={route('admin.users.index')}
                                className="btn secondary"
                                preserveState={false}
                            >
                                Limpiar
                            </Link>
                        )}
                    </div>
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Cliente vinculado</th>
                            <th>Empresas</th>
                            <th>Módulos</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {users.data.map((user) => (
                            <tr key={user.id}>
                                {/* Usuario */}
                                <td>
                                    <span style={{ fontWeight: 600 }}>{user.name}</span>
                                    <br />
                                    <span className="muted">{user.email}</span>
                                </td>

                                {/* Rol */}
                                <td>
                                    <span className={`badge ${roleBadges[user.role] ?? ''}`}>
                                        {roles[user.role] ?? user.role}
                                    </span>
                                </td>

                                {/* Cliente vinculado */}
                                <td>
                                    {user.customer
                                        ? <span>{user.customer.name}<br /><span className="muted">{user.customer.email}</span></span>
                                        : <span className="muted">—</span>
                                    }
                                </td>

                                {/* Empresas */}
                                <td>
                                    {user.accessibleCompanies?.length > 0
                                        ? user.accessibleCompanies.map((c) => (
                                            <span key={c.id} style={{ display: 'block', fontSize: 13 }}>{c.business_name}</span>
                                        ))
                                        : <span className="muted">—</span>
                                    }
                                </td>

                                {/* Módulos */}
                                <td>
                                    {user.module_accesses?.length > 0
                                        ? user.module_accesses.map((m) => (
                                            <span key={m} style={{ display: 'block', fontSize: 12 }}>
                                                {modules[m] ?? m}
                                            </span>
                                        ))
                                        : <span className="muted">—</span>
                                    }
                                </td>

                                {/* Estado */}
                                <td>
                                    <span className={`badge ${user.is_active ? 'active' : 'suspended'}`}>
                                        {user.is_active ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>

                                {/* Creado */}
                                <td className="muted">
                                    {user.created_at ? user.created_at.substring(0, 10) : '—'}
                                </td>

                                {/* Acción */}
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setEditItem(user)}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm danger"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            disabled={user.id === currentUserId}
                                            title={user.id === currentUserId ? 'No puedes eliminar tu propio usuario' : ''}
                                            onClick={() => handleDelete(user)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={users.links} />
            </div>

            {/* Create modal */}
            {showCreate && (
                <Modal title="Nuevo usuario" onClose={() => setShowCreate(false)}>
                    <UserForm
                        modules={modules}
                        customers={customers}
                        companies={companies}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {/* Edit modal */}
            {editItem && (
                <Modal title={`Editar usuario — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <UserForm
                        user={editItem}
                        modules={modules}
                        customers={customers}
                        companies={companies}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
