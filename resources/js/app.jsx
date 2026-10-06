import './bootstrap';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { showToast, postJson, putJson, deleteJson } from './utils';

window.coteja = { showToast, postJson, putJson, deleteJson };

// Global overlay-modal close handling
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

createInertiaApp({
    title: (title) => title ? `${title} | Coteja` : 'Coteja',
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
