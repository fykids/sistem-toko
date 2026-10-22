const layout = document.querySelector('[data-showroom-layout]');

if (layout) {
    const databaseModal = document.querySelector('[data-database-modal]');
    const databaseSidebarLinks = document.querySelectorAll('[data-sidebar-database]');
    const databaseCloseButtons = document.querySelectorAll('[data-database-close]');
    const modalDialog = databaseModal?.querySelector('[role="dialog"]');
    const fileInput = document.getElementById('transaction-backup');
    const dropzone = document.querySelector('[data-dropzone]');
    const fileName = document.querySelector('[data-file-name]');
    const transactionSearch = document.getElementById('transaction-search');
    const transactionRows = [...document.querySelectorAll('#transaction-rows tr[data-status]')];
    const filterButtons = [...document.querySelectorAll('[data-dashboard-filters] [data-filter]')];
    const viewTitles = {
        dashboard: 'Dashboard',
        inventory: 'Inventaris',
        sales: 'Penjualan',
        reports: 'Laporan',
    };
    const listTitles = {
        dashboard: 'Ringkasan kendaraan',
        inventory: 'Kendaraan dalam stok',
        sales: 'Riwayat penjualan',
        reports: 'Rincian kendaraan terjual',
    };
    const listDescriptions = {
        dashboard: 'Semua kendaraan tercatat',
        inventory: 'Kendaraan yang masih tersedia di showroom',
        sales: 'Kendaraan dengan tanggal penjualan',
        reports: 'Daftar transaksi yang dihitung dalam laporan',
    };
    let modalTrigger = null;

    const filterTransactions = (view) => {
        const query = transactionSearch.value.trim().toLowerCase();
        let visibleCount = 0;
        const statusFilter = view === 'inventory' ? 'stock' : ['sales', 'reports'].includes(view) ? 'sold' : null;

        transactionRows.forEach((row) => {
            const matchesQuery = row.dataset.search.includes(query);
            const matchesStatus = !statusFilter || row.dataset.status === statusFilter;
            row.hidden = !(matchesQuery && matchesStatus);
            visibleCount += row.hidden ? 0 : 1;
        });

        const emptyState = document.querySelector('[data-empty-state]');
        emptyState.hidden = visibleCount > 0;
        document.querySelector('[data-empty-title]').textContent = {
            dashboard: 'Belum ada kendaraan',
            inventory: 'Stok kendaraan kosong',
            sales: 'Belum ada kendaraan terjual',
            reports: 'Belum ada data penjualan',
        }[view];
        document.querySelector('[data-empty-description]').textContent = query
            ? 'Coba ubah kata kunci pencarian.'
            : 'Data akan muncul di sini setelah transaksi dicatat.';
        document.getElementById('transaction-count').textContent =
            `Menampilkan ${visibleCount} kendaraan`;
    };

    const setDashboardView = (view, updateHash = true) => {
        if (!Object.hasOwn(viewTitles, view)) {
            view = 'dashboard';
        }

        layout.dataset.showroomView = view;
        layout.querySelectorAll('[data-dashboard-page]').forEach((panel) => {
            panel.hidden = panel.dataset.dashboardPage !== view;
        });

        layout.querySelectorAll('[data-dashboard-nav]').forEach((link) => {
            const isSelected = link.dataset.dashboardNav === view;
            link.classList.toggle('is-active', isSelected);
            if (isSelected) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        document.querySelector('[data-current-view-title]').textContent = viewTitles[view];
        document.getElementById('transaction-list-title').textContent = listTitles[view];
        document.querySelector('[data-list-description]').textContent = listDescriptions[view];
        document.querySelector('[data-dashboard-filters]').hidden = view !== 'dashboard';
        filterButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.filter === view));
        });
        filterTransactions(view);

        if (updateHash && window.location.hash !== `#${view === 'sales' ? 'penjualan' : view}`) {
            window.history.replaceState(null, '', `#${view === 'sales' ? 'penjualan' : view}`);
        }
    };

    layout.querySelectorAll('[data-dashboard-nav]').forEach((link) => {
        link.addEventListener('click', () => {
            setDashboardView(link.dataset.dashboardNav);
        });
    });

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => setDashboardView(button.dataset.filter));
    });

    transactionSearch.addEventListener('input', () => filterTransactions(layout.dataset.showroomView));

    const viewFromHash = () => {
        const view = {
            '#dashboard': 'dashboard',
            '#inventaris': 'inventory',
            '#penjualan': 'sales',
            '#laporan': 'reports',
        }[window.location.hash] ?? 'dashboard';
        setDashboardView(view, false);
    };

    window.addEventListener('hashchange', viewFromHash);
    viewFromHash();

    if (databaseModal && modalDialog) {
        const closeDatabaseModal = () => {
            databaseModal.hidden = true;
            databaseModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('showroom-modal-open');
            const focusTarget = modalTrigger?.closest('[data-app-menu-panel]')
                && window.matchMedia('(max-width: 820px)').matches
                ? document.querySelector('[data-app-menu-toggle]')
                : modalTrigger;
            focusTarget?.focus();
        };

        const openDatabaseModal = (trigger) => {
            modalTrigger = trigger;
            databaseModal.hidden = false;
            databaseModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('showroom-modal-open');
            modalDialog.querySelector('[data-database-close]').focus();
        };

        databaseSidebarLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (new URL(link.href).pathname !== window.location.pathname) {
                    return;
                }

                event.preventDefault();
                openDatabaseModal(link);
            });
        });

        if (window.location.hash === '#database') {
            document.querySelector('[data-sidebar-database]')?.click();
        }

        databaseCloseButtons.forEach((button) => {
            button.addEventListener('click', closeDatabaseModal);
        });

        databaseModal.addEventListener('click', (event) => {
            if (event.target === databaseModal) {
                closeDatabaseModal();
            }
        });

        databaseModal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDatabaseModal();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusableElements = [...modalDialog.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled])',
            )];
            const firstElement = focusableElements[0];
            const lastElement = focusableElements.at(-1);

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        });
    }

    if (fileInput && dropzone && fileName) {
        fileInput.addEventListener('change', () => {
            const selectedFile = fileInput.files[0];
            const isSupported = selectedFile ? /\.(sqlite|db)$/i.test(selectedFile.name) : true;

            dropzone.classList.toggle('is-invalid', !isSupported);
            fileInput.setCustomValidity(isSupported ? '' : 'Pilih file backup dengan format .sqlite atau .db.');
            fileName.textContent = selectedFile
                ? (isSupported ? selectedFile.name : 'Format tidak didukung. Gunakan .sqlite atau .db.')
                : 'Belum ada file dipilih';
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            const droppedFile = event.dataTransfer.files[0];

            if (!droppedFile) {
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(droppedFile);
            fileInput.files = transfer.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
}
