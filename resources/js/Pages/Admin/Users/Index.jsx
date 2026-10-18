import { useState, useEffect } from 'react';
import { Head, useForm, router, Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { IconEdit, IconTrash } from '@/Components/Icons';

const roles = { admin: 'Administrador', inquilino: 'Inquilino' };
const roleBadges = { admin: 'role-admin', inquilino: 'role-inquilino' };

// ─── UserForm ────────────────────────────────────────────────────────────────

function UserForm({ user, modules, companies, onSuccess, onCancel }) {
    const isEdit = !!user;

    const { data, setData, post, put, processing, errors } = useForm({
        name:        user?.name ?? '',
        email:       user?.email ?? '',
        password:    '',
        role:        user?.role ?? 'admin',
        is_active:   user?.is_active !== undefined ? String(Number(user.is_active)) : '1',
        company_ids: user?.accessibleCompanies?.map((c) => c.id) ?? [],
    });

    const showInquilinoFields = data.role === 'inquilino';

    useEffect(() => {
        if (data.role === 'admin') {
            setData((prev) => ({ ...prev, company_ids: [] }));
        }
    }, [data.role]);

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

                <label>
                    Rol
                    <select value={data.role} onChange={(e) => setData('role', e.target.value)}>
                        {Object.entries(roles).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.role && <p className="field-error">{errors.role}</p>}
                </label>

                <label>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    {errors.is_active && <p className="field-error">{errors.is_active}</p>}
                </label>

                {showInquilinoFields && (
                    <div className="full-width">
                        <p style={{ margin: '0 0 8px', fontSize: 13, fontWeight: 600, color: '#374151' }}>
                            Empresas accesibles <span style={{ color: 'red' }}>*</span>
                        </p>
                        {companies.length === 0 ? (
                            <p className="muted" style={{ margin: 0 }}>No hay empresas registradas.</p>
                        ) : (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                {companies.map((company) => (
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
                        )}
                        {errors.company_ids && <p className="field-error">{errors.company_ids}</p>}
                    </div>
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

export default function UsersIndex({ users, search, role, modules, companies }) {
    const { auth } = usePage().props;
    const currentUserId = auth?.user?.id;

    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    const [searchVal, setSearchVal] = useState(search ?? '');
    const [roleVal, setRoleVal] = useState(role ?? '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('admin.users.index'), { search: searchVal, role: roleVal }, { preserveState: true });
    }

    function handleDelete(user) {
        if (!confirm(`¿Eliminar al usuario "${user.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.users.destroy', user.id));
    }

    const hasFilter = !!(search || role);

    return (
        <AppLayout>
            <Head title="Usuarios y acceso" />

            <style>{`
                .role-admin     { background: #dbeafe; color: #1e40af; }
                .role-inquilino { background: #f3e8ff; color: #6b21a8; }
            `}</style>

            <div className="top">
                <h1>Usuarios y acceso</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nuevo usuario
                </button>
            </div>

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
                            <Link href={route('admin.users.index')} className="btn secondary" preserveState={false}>
                                Limpiar
                            </Link>
                        )}
                    </div>
                </form>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Empresas accesibles</th>
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
                                <td>
                                    <span style={{ fontWeight: 600 }}>{user.name}</span>
                                    <br />
                                    <span className="muted">{user.email}</span>
                                </td>

                                <td>
                                    <span className={`badge ${roleBadges[user.role] ?? ''}`}>
                                        {roles[user.role] ?? user.role}
                                    </span>
                                </td>

                                <td>
                                    {user.role === 'admin'
                                        ? <span style={{ fontSize: 12, color: '#6b7280', fontStyle: 'italic' }}>Todas</span>
                                        : user.accessibleCompanies?.length > 0
                                            ? user.accessibleCompanies.map((c) => (
                                                <span key={c.id} style={{ display: 'block', fontSize: 13 }}>{c.business_name}</span>
                                            ))
                                            : <span className="muted">—</span>
                                    }
                                </td>

                                <td>
                                    {user.role === 'admin'
                                        ? <span style={{ fontSize: 12, color: '#6b7280', fontStyle: 'italic' }}>Todos</span>
                                        : user.customer?.module_accesses?.length > 0
                                            ? user.customer.module_accesses.map((m) => (
                                                <span key={m} style={{ display: 'block', fontSize: 12 }}>
                                                    {modules[m] ?? m}
                                                </span>
                                            ))
                                            : <span className="muted">—</span>
                                    }
                                </td>

                                <td>
                                    <span className={`badge ${user.is_active ? 'active' : 'suspended'}`}>
                                        {user.is_active ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>

                                <td className="muted">
                                    {user.created_at ? user.created_at.substring(0, 10) : '—'}
                                </td>

                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn secondary btn-icon"
                                            title="Editar"
                                            onClick={() => setEditItem(user)}
                                        >
                                            <IconEdit />
                                        </button>
                                        <button
                                            type="button"
                                            className="btn danger btn-icon"
                                            disabled={user.id === currentUserId}
                                            title={user.id === currentUserId ? 'No puedes eliminar tu propio usuario' : 'Eliminar'}
                                            onClick={() => handleDelete(user)}
                                        >
                                            <IconTrash />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={users.links} />
            </div>

            {showCreate && (
                <Modal title="Nuevo usuario" onClose={() => setShowCreate(false)}>
                    <UserForm
                        modules={modules}
                        companies={companies}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title={`Editar usuario — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <UserForm
                        user={editItem}
                        modules={modules}
                        companies={companies}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
