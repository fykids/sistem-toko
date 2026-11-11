import * as Turbo from '@hotwired/turbo';
import './transactions/create.js';
import './transactions/jual.js';
import '../css/transactions/leasing.css';
import '../css/transactions/sale-notes.css';
import '../css/transactions/jual.css';
import './dashboard.js';
import './users.js';
import '../css/dashboard.css';

let hasActiveVisit = false;
let sharedUiListeners;
const disabledSubmitButtons = new WeakMap();

const setPageLoading = (isLoading) => {
    const indicator = document.querySelector('[data-page-loading]');
    if (!indicator) {
        return;
    }

    indicator.hidden = !isLoading;
    indicator.setAttribute('aria-hidden', String(!isLoading));
};

document.addEventListener('turbo:visit', () => {
    hasActiveVisit = true;
    setPageLoading(true);
});

document.addEventListener('turbo:load', () => {
    hasActiveVisit = false;
    setPageLoading(false);
    initializeSharedUi();
});

document.addEventListener('turbo:before-cache', () => {
    setPageLoading(false);
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    document.querySelectorAll('[data-database-modal]').forEach((modal) => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    });
    document.querySelectorAll('[data-profile-menu-toggle], [data-sales-menu-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-expanded', 'false');
        const menu = toggle.closest('[data-profile-menu], [data-sales-menu]');
        menu?.querySelector('[data-profile-menu-panel], [data-sales-menu-panel]')?.setAttribute('hidden', '');
    });
    document.querySelectorAll('[data-app-menu-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka navigasi');
    });
    document.querySelectorAll('[data-app-menu-panel]').forEach((menu) => {
        menu.classList.remove('is-visible');
        menu.setAttribute('aria-hidden', 'true');
        menu.inert = true;
    });
    document.querySelectorAll('[data-app-menu-backdrop]').forEach((backdrop) => {
        backdrop.classList.remove('is-visible');
        backdrop.hidden = true;
    });
    document.body.classList.remove(
        'showroom-modal-open',
        'has-app-drawer-open',
        'app-sidebar-collapsed',
    );
});

document.addEventListener('turbo:submit-start', (event) => {
    if (!(event.target instanceof HTMLFormElement)) {
        return;
    }

    const submitButtons = [...event.target.querySelectorAll(
        'button:not([type]), button[type="submit"], input[type="submit"]',
    )];
    disabledSubmitButtons.set(
        event.target,
        submitButtons.map((button) => [button, button.disabled]),
    );
    submitButtons.forEach((button) => {
        button.disabled = true;
    });
    event.target.setAttribute('aria-busy', 'true');
    setPageLoading(true);
}, true);

document.addEventListener('turbo:submit-end', (event) => {
    if (!(event.target instanceof HTMLFormElement)) {
        return;
    }

    disabledSubmitButtons.get(event.target)?.forEach(([button, wasDisabled]) => {
        button.disabled = wasDisabled;
    });
    disabledSubmitButtons.delete(event.target);
    event.target.removeAttribute('aria-busy');
    window.setTimeout(() => {
        if (!hasActiveVisit) {
            setPageLoading(false);
        }
    }, 0);
}, true);

document.addEventListener('turbo:fetch-request-error', () => {
    hasActiveVisit = false;
    setPageLoading(false);
});

function initializeSharedUi() {
    sharedUiListeners?.abort();
    sharedUiListeners = new AbortController();

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const closeButton = toast.querySelector('[data-toast-close]');
        let dismissTimeout;

        const dismiss = () => {
            if (toast.dataset.dismissed === 'true') {
                return;
            }

            toast.dataset.dismissed = 'true';
            window.clearTimeout(dismissTimeout);
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 220);
        };

        closeButton?.addEventListener('click', dismiss, { once: true });

        window.requestAnimationFrame(() => toast.classList.add('is-visible'));
        dismissTimeout = window.setTimeout(dismiss, 3500);
    });

    document.querySelectorAll('[data-profile-menu]').forEach((profileMenu) => {
        const toggle = profileMenu.querySelector('[data-profile-menu-toggle]');
        const panel = profileMenu.querySelector('[data-profile-menu-panel]');

        if (!toggle || !panel) {
            return;
        }

        const closeMenu = (restoreFocus = false) => {
            toggle.setAttribute('aria-expanded', 'false');
            panel.hidden = true;

            if (restoreFocus) {
                toggle.focus();
            }
        };

        toggle.addEventListener('click', (event) => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            panel.hidden = isOpen;

            if (!isOpen && event.detail === 0) {
                panel.querySelector('[role="menuitem"]')?.focus();
            }
        });

        profileMenu.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[role="menuitem"]')) {
                closeMenu();
            }
        });

        profileMenu.querySelector('[data-confirm-logout]')?.addEventListener('click', (event) => {
            if (!window.confirm('Apakah Anda yakin ingin logout?')) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        profileMenu.addEventListener('focusout', (event) => {
            if (!(event.relatedTarget instanceof Node) || !profileMenu.contains(event.relatedTarget)) {
                closeMenu();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (event.target instanceof Node && !profileMenu.contains(event.target)) {
                closeMenu();
            }
        }, { signal: sharedUiListeners.signal });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                event.stopImmediatePropagation();
                closeMenu(true);
                return;
            }

            if (toggle.getAttribute('aria-expanded') !== 'true') {
                return;
            }

            const menuItems = [...panel.querySelectorAll('[role="menuitem"]')];
            const currentIndex = menuItems.indexOf(document.activeElement);

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const nextIndex = event.key === 'ArrowDown'
                    ? (currentIndex + 1) % menuItems.length
                    : (currentIndex - 1 + menuItems.length) % menuItems.length;
                menuItems[nextIndex]?.focus();
            } else if (event.key === 'Home') {
                event.preventDefault();
                menuItems[0]?.focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                menuItems.at(-1)?.focus();
            }
        }, { signal: sharedUiListeners.signal });
    });

    document.querySelectorAll('[data-app-menu-toggle]').forEach((toggle) => {
        const topbar = toggle.closest('.showroom-topbar');
        const menu = topbar?.parentElement?.querySelector('[data-app-menu-panel]');
        const backdrop = topbar?.parentElement?.querySelector('[data-app-menu-backdrop]');
        const closeButton = menu?.querySelector('[data-app-menu-close-button]');
        let closeTimeout;

        if (!topbar || !menu || !backdrop || !closeButton) {
            return;
        }

        const appShell = toggle.closest('.page-shell--app');
        const desktopAppShell = window.matchMedia('(min-width: 821px)');

        const closeMenu = (restoreFocus = false) => {
            window.clearTimeout(closeTimeout);
            topbar.classList.remove('is-menu-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Buka navigasi');
            menu.setAttribute('aria-hidden', 'true');
            menu.inert = true;
            document.body.classList.remove('has-app-drawer-open');
            menu.classList.remove('is-visible');
            backdrop.classList.remove('is-visible');
            closeTimeout = window.setTimeout(() => {
                backdrop.hidden = true;
            }, 180);

            if (restoreFocus) {
                toggle.focus();
            }
        };

        const openMenu = () => {
            window.clearTimeout(closeTimeout);
            backdrop.hidden = false;
            menu.inert = false;
            menu.setAttribute('aria-hidden', 'false');
            document.body.classList.add('has-app-drawer-open');
            window.requestAnimationFrame(() => {
                topbar.classList.add('is-menu-open');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Tutup navigasi');
                menu.classList.add('is-visible');
                backdrop.classList.add('is-visible');
            });
            closeButton.focus();
        };

        const setAppSidebarCollapsed = (isCollapsed) => {
            document.body.classList.toggle('app-sidebar-collapsed', isCollapsed);
            toggle.setAttribute('aria-expanded', String(!isCollapsed));
            toggle.setAttribute('aria-label', isCollapsed ? 'Buka navigasi' : 'Kecilkan navigasi');
            menu.inert = isCollapsed;
            menu.setAttribute('aria-hidden', String(isCollapsed));
        };

        if (appShell && desktopAppShell.matches) {
            setAppSidebarCollapsed(false);
        }

        toggle.addEventListener('click', () => {
            if (appShell && desktopAppShell.matches) {
                setAppSidebarCollapsed(!document.body.classList.contains('app-sidebar-collapsed'));
                return;
            }

            if (toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu();
                return;
            }

            openMenu();
        });

        backdrop.addEventListener('click', () => closeMenu(true));
        closeButton.addEventListener('click', () => closeMenu(true));
        menu.addEventListener('click', (event) => {
            if (
                event.target instanceof Element
                && event.target.closest('a')
                && !(appShell && desktopAppShell.matches)
            ) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                if (appShell && desktopAppShell.matches) {
                    setAppSidebarCollapsed(true);
                    toggle.focus();
                } else {
                    closeMenu(true);
                }

                return;
            }

            if (
                event.key === 'Tab'
                && toggle.getAttribute('aria-expanded') === 'true'
                && !(appShell && desktopAppShell.matches)
            ) {
                const focusableItems = [...menu.querySelectorAll('a[href], button:not(:disabled)')];
                const firstItem = focusableItems[0];
                const lastItem = focusableItems.at(-1);

                if (event.shiftKey && document.activeElement === firstItem) {
                    event.preventDefault();
                    lastItem?.focus();
                } else if (!event.shiftKey && document.activeElement === lastItem) {
                    event.preventDefault();
                    firstItem?.focus();
                }
            }
        }, { signal: sharedUiListeners.signal });

        desktopAppShell.addEventListener('change', (event) => {
            if (!appShell) {
                return;
            }

            closeMenu();
            document.body.classList.remove('app-sidebar-collapsed');
            if (event.matches) {
                setAppSidebarCollapsed(false);
            } else {
                menu.inert = true;
                menu.setAttribute('aria-hidden', 'true');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Buka navigasi');
            }
        }, { signal: sharedUiListeners.signal });
    });

    document.querySelectorAll('[data-sales-menu-toggle]').forEach((toggle) => {
        const group = toggle.closest('[data-sales-menu]');
        const panel = group?.querySelector('[data-sales-menu-panel]');

        if (!group || !panel) {
            return;
        }

        const closeMenu = (restoreFocus = false) => {
            toggle.setAttribute('aria-expanded', 'false');
            panel.hidden = true;

            if (restoreFocus) {
                toggle.focus();
            }
        };

        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(!isExpanded));
            panel.hidden = isExpanded;
        });

        group.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu(true);
            }
        });

        document.addEventListener('click', (event) => {
            if (event.target instanceof Node && !group.contains(event.target)) {
                closeMenu();
            }
        }, { signal: sharedUiListeners.signal });
    });
}

Turbo.start();
