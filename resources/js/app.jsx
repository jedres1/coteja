import './bootstrap';
import { createApp as createVueApp } from 'vue';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FacturaElectronicaSV from './views/admin/FacturaElectronicaSV.vue';
import { showToast, postJson, putJson, deleteJson } from './utils';

window.coteja = { showToast, postJson, putJson, deleteJson };
window.vueApps = {};

function mountFacturaSv() {
    const el = document.querySelector('#factura-electronica-sv');
    if (!el) return;
    window.vueApps['#factura-electronica-sv']?.unmount?.();
    const app = createVueApp(FacturaElectronicaSV);
    window.vueApps['#factura-electronica-sv'] = app;
    app.mount(el);
}

// Global overlay-modal close handling (works on both Inertia and Blade pages)
document.addEventListener('click', (event) => {
    const overlayClose = event.target.closest?.('[data-overlay-close]');
    if (overlayClose) {
        overlayClose.closest('details.overlay-modal')?.removeAttribute('open');
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('details.overlay-modal[open]').forEach((modal) => {
        modal.removeAttribute('open');
    });
});

const inertiaEl = document.getElementById('app');

if (inertiaEl) {
    createInertiaApp({
        title: (title) => title ? `${title} | Coteja` : 'Coteja',
        resolve: (name) =>
            resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
        setup({ el, App, props }) {
            createRoot(el).render(<App {...props} />);
        },
    });
} else {
    // Blade page (factura-sv) — mount Vue and wire up internal navigation
    document.addEventListener('DOMContentLoaded', () => {
        mountFacturaSv();

        const isFacturaSvUrl = (url) =>
            url.origin === window.location.origin && url.pathname === '/admin/factura-sv';

        document.addEventListener('click', (event) => {
            const link = event.target.closest?.('.nav a[href]');
            if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.target || link.hasAttribute('download')) return;

            const url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) return;

            if (!document.querySelector('#factura-electronica-sv')) return;
            if (!isFacturaSvUrl(url) || !isFacturaSvUrl(new URL(window.location.href))) return;

            event.preventDefault();
            const view = url.searchParams.get('view') || 'dashboard';
            window.history.pushState({}, '', url);
            window.dispatchEvent(new CustomEvent('coteja:factura-view', { detail: { view } }));
        });

        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);
            if (document.querySelector('#factura-electronica-sv') && isFacturaSvUrl(url)) {
                const view = url.searchParams.get('view') || 'dashboard';
                window.dispatchEvent(new CustomEvent('coteja:factura-view', { detail: { view } }));
            }
        });

        // Blade-page logout confirmation
        document.getElementById('logout-form')?.addEventListener('submit', (event) => {
            if (!confirm('Se va a cerrar la sesión. ¿Desea continuar?')) {
                event.preventDefault();
            }
        });

        // Nav group toggles
        document.querySelectorAll('.nav-toggle').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const group = toggle.closest('.nav-group');
                const isOpen = !group.classList.contains('open');
                document.querySelectorAll('.nav-group.open').forEach((g) => {
                    g.classList.remove('open');
                    g.querySelector('.nav-toggle')?.setAttribute('aria-expanded', 'false');
                });
                group.classList.toggle('open', isOpen);
                toggle.setAttribute('aria-expanded', String(isOpen));
            });
        });

        // Theme toggle
        const storedTheme = localStorage.getItem('coteja-theme');
        const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
        document.body.classList.toggle('dark-mode', storedTheme ? storedTheme === 'dark' : prefersDark);

        const syncThemeIcon = () => {
            const icon = document.getElementById('theme-icon');
            if (icon) icon.textContent = document.body.classList.contains('dark-mode') ? '☀' : '☾';
        };
        syncThemeIcon();

        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('coteja-theme', isDark ? 'dark' : 'light');
            syncThemeIcon();
        });
    });
}
