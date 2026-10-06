export function showToast(msg, ok) {
    let t = document.getElementById('coteja-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'coteja-toast';
        t.style.cssText =
            'position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;padding:.75rem 1.25rem;' +
            'border-radius:.375rem;color:#fff;font-size:.875rem;display:none;max-width:20rem;' +
            'box-shadow:0 4px 12px rgba(0,0,0,.25);';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = ok ? '#166534' : '#991b1b';
    t.style.display = 'block';
    clearTimeout(t._coteja_timer);
    t._coteja_timer = setTimeout(() => (t.style.display = 'none'), 4000);
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
