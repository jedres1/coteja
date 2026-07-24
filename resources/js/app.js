import './bootstrap';
import { createApp } from 'vue';
import FacturaElectronicaSV from './views/admin/FacturaElectronicaSV.vue';

window.vueApps = {};

function mountVueComponent(selector, component) {
	const el = document.querySelector(selector);
	if (el) {
		window.vueApps[selector]?.unmount?.();
		window.vueApps[selector] = createApp(component).mount(el);
	}
}

window.mountVueComponent = mountVueComponent;

function unmountVueComponents() {
	Object.values(window.vueApps).forEach((app) => app?.unmount?.());
	window.vueApps = {};
}

function executeEmbeddedScripts(container) {
	container.querySelectorAll('script').forEach((script) => {
		const replacement = document.createElement('script');
		Array.from(script.attributes).forEach((attribute) => {
			replacement.setAttribute(attribute.name, attribute.value);
		});
		replacement.textContent = script.textContent;
		script.replaceWith(replacement);
	});
}

function updateNavigationState(url) {
	const target = new URL(url, window.location.origin);
	const links = document.querySelectorAll('.nav a[href]');

	links.forEach((link) => {
		const linkUrl = new URL(link.href, window.location.origin);
		link.classList.toggle(
			'active-link',
			linkUrl.pathname === target.pathname && linkUrl.search === target.search
		);
	});

	const activeLink = document.querySelector('.nav a.active-link');
	if (!activeLink) return;

	document.querySelectorAll('.nav-group.open').forEach((group) => {
		group.classList.remove('open');
		group.querySelector('.nav-toggle')?.setAttribute('aria-expanded', 'false');
	});

	const group = activeLink.closest('.nav-group');
	if (group) {
		group.classList.add('open');
		group.querySelector('.nav-toggle')?.setAttribute('aria-expanded', 'true');
	}
}

function isFacturaSvUrl(url) {
	return url.origin === window.location.origin && url.pathname === '/admin/factura-sv';
}

function navigateFacturaView(url, push = true) {
	const view = url.searchParams.get('view') || 'dashboard';
	if (push) {
		window.history.pushState({}, '', url);
	}
	updateNavigationState(url);
	window.dispatchEvent(new CustomEvent('coteja:factura-view', { detail: { view } }));
}

async function navigateWithPjax(url, push = true) {
	document.body.classList.add('is-navigating');

	try {
		const response = await fetch(url, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'X-Coteja-PJAX': 'true',
			},
		});
		const html = await response.text();
		const nextDocument = new DOMParser().parseFromString(html, 'text/html');
		const nextMain = nextDocument.querySelector('.main');
		const currentMain = document.querySelector('.main');

		if (!response.ok || !nextMain || !currentMain) {
			window.location.href = url;
			return;
		}

		unmountVueComponents();
		currentMain.innerHTML = nextMain.innerHTML;
		executeEmbeddedScripts(currentMain);
		document.title = nextDocument.title;

		if (push) {
			window.history.pushState({}, '', url);
		}

		updateNavigationState(url);
		mountVueComponent('#factura-electronica-sv', FacturaElectronicaSV);
		currentMain.scrollTo?.({ top: 0, behavior: 'auto' });
		window.scrollTo?.({ top: 0, behavior: 'auto' });
	} catch (error) {
		window.location.href = url;
	} finally {
		document.body.classList.remove('is-navigating');
	}
}

document.addEventListener('DOMContentLoaded', () => {
    mountVueComponent('#factura-electronica-sv', FacturaElectronicaSV);

    document.querySelectorAll('[data-overlay-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('details.overlay-modal')?.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('details.overlay-modal[open]').forEach((modal) => {
            modal.removeAttribute('open');
        });
    });

	document.addEventListener('click', (event) => {
		const overlayClose = event.target.closest?.('[data-overlay-close]');
		if (overlayClose) {
			overlayClose.closest('details.overlay-modal')?.removeAttribute('open');
			return;
		}

		const link = event.target.closest?.('.nav a[href]');
		if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		if (link.target || link.hasAttribute('download')) return;

		const url = new URL(link.href, window.location.origin);
		if (url.origin !== window.location.origin) return;

		event.preventDefault();

		if (document.querySelector('#factura-electronica-sv') && isFacturaSvUrl(url) && isFacturaSvUrl(new URL(window.location.href))) {
			navigateFacturaView(url);
			return;
		}

		navigateWithPjax(url);
	});

	window.addEventListener('popstate', () => {
		const url = new URL(window.location.href);
		if (document.querySelector('#factura-electronica-sv') && isFacturaSvUrl(url)) {
			navigateFacturaView(url, false);
			return;
		}

		navigateWithPjax(url, false);
	});
});
