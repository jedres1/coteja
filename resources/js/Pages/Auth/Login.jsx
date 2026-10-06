import { Head, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function Login({ errors: serverErrors }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login.store'));
    };

    return (
        <>
            <Head title="Ingresar" />
            <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: '24px' }}>
                <form onSubmit={submit} className="card" style={{ width: 'min(420px,100%)' }}>
                    <img
                        src="/images/facturacion-electron-logo.png"
                        alt="CONSULTING AND TECH JANDRES"
                        width="168"
                        height="168"
                        style={{ width: '168px', maxWidth: '100%', height: 'auto', margin: '0 auto 14px', display: 'block' }}
                    />
                    <p className="muted" style={{ margin: '0 0 18px' }}>Consulting and Tech Jandres.</p>

                    {errors.email && <div className="errors">{errors.email}</div>}

                    <div className="form-grid" style={{ gridTemplateColumns: '1fr' }}>
                        <label>
                            Correo
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoFocus
                            />
                        </label>
                        <label>
                            Clave
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                        </label>
                        <button className="btn" disabled={processing}>Ingresar</button>
                    </div>
                </form>
            </div>
        </>
    );
}
