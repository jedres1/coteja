export function showToast(msg, ok) {
    let t = document.getElementById('coteja-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'coteja-toast';
        t.style.cssText =
            'position:fixed;top:1.25rem;left:50%;transform:translateX(-50%);z-index:9999;padding:.75rem 1.25rem;' +
            'border-radius:.375rem;color:#fff;font-size:.875rem;display:none;max-width:min(90vw,36rem);' +
            'box-shadow:0 4px 12px rgba(0,0,0,.25);';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._coteja_timer);
    t._coteja_timer = setTimeout(() => (t.style.display = 'none'), 4000);
}

export function formatDate(value) {
    if (!value) return '—';

    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return value;

    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
}

export async function postJson(url, payload = {}, onOk = null) {
    try {
        const res = await window.axios.post(url, payload);
        showToast(res.data.message ?? 'Listo.', true);
        setTimeout(() => (onOk ? onOk() : window.location.reload()), 700);
        return res.data;
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Error de red.', false);
        throw err;
    }
}

export async function putJson(url, payload = {}, onOk = null) {
    try {
        const res = await window.axios.put(url, payload);
        showToast(res.data.message ?? 'Listo.', true);
        setTimeout(() => (onOk ? onOk() : window.location.reload()), 700);
        return res.data;
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Error de red.', false);
        throw err;
    }
}

export async function deleteJson(url, onOk = null) {
    try {
        const res = await window.axios.delete(url);
        showToast(res.data.message ?? 'Eliminado.', true);
        setTimeout(() => (onOk ? onOk() : window.location.reload()), 700);
        return res.data;
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Error de red.', false);
        throw err;
    }
}
