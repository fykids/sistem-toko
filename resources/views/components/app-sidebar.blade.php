<button class="app-menu-backdrop" type="button" aria-label="Tutup navigasi"
    data-app-menu-backdrop hidden tabindex="-1"></button>
<aside class="app-navigation-drawer" id="app-navigation" aria-label="Navigasi utama"
    data-app-menu-panel aria-hidden="true" inert>
    <div class="app-drawer-header">
        <a class="app-drawer-brand" href="{{ route('dashboard') }}">
            <span class="app-drawer-brand-mark" aria-hidden="true">ST</span>
            <span class="app-drawer-brand-copy">
                <strong>Sistem Toko</strong>
                <span>Showroom mobil</span>
            </span>
        </a>
        <button class="app-drawer-close" type="button" aria-label="Tutup navigasi"
            data-app-menu-close-button>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round">
                <path d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
    </div>

    <p class="app-drawer-section-label">Menu utama</p>
    <nav class="app-drawer-nav" aria-label="Menu utama">
        <a class="app-drawer-link" href="{{ route('dashboard') }}#dashboard"
            @class(['is-active' => request()->routeIs('dashboard', 'transactions.index')])
            @if (request()->routeIs('dashboard', 'transactions.index')) aria-current="page" @endif
            @if (request()->routeIs('dashboard', 'transactions.index')) data-dashboard-nav="dashboard" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="8" height="8" rx="1.5" />
                <rect x="13" y="3" width="8" height="5" rx="1.5" />
                <rect x="13" y="10" width="8" height="11" rx="1.5" />
                <rect x="3" y="13" width="8" height="8" rx="1.5" />
            </svg>
            <span>Dashboard</span>
        </a>
        <a class="app-drawer-link" href="{{ route('dashboard') }}#inventaris"
            @if (request()->routeIs('dashboard', 'transactions.index')) data-dashboard-nav="inventory" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 13 1.6-5.2A2.5 2.5 0 0 1 7 6h10a2.5 2.5 0 0 1 2.4 1.8L21 13" />
                <path d="M4 13h16a1 1 0 0 1 1 1v4H3v-4a1 1 0 0 1 1-1ZM6 18v2m12-2v2M6 10h.01M18 10h.01" />
            </svg>
            <span>Inventaris</span>
        </a>
        <a class="app-drawer-link" href="{{ route('dashboard') }}#penjualan"
            @if (request()->routeIs('dashboard', 'transactions.index')) data-dashboard-nav="sales" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19V5m0 14h16" />
                <path d="m7 15 4-4 3 2 5-6" />
                <path d="M15 7h4v4" />
            </svg>
            <span>Penjualan</span>
        </a>
        @can('viewAny', \App\Models\SaleNote::class)
            <a class="app-drawer-link" href="{{ route('jual.index') }}"
                @class(['is-active' => request()->routeIs('jual.*')])
                @if (request()->routeIs('jual.*')) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19V5m0 14h16" />
                    <path d="M7 15h10M7 11h6M15 7h4v4" />
                </svg>
                <span>Jual</span>
            </a>
        @endcan
        @can('viewAny', \App\Models\SaleNote::class)
            <a class="app-drawer-link" href="{{ route('nota-penjualan.index') }}"
                @class(['is-active' => request()->routeIs('nota-penjualan.*')])
                @if (request()->routeIs('nota-penjualan.*')) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 3h9l4 4v14H6z" />
                    <path d="M14 3v5h5M9 12h7M9 16h7" />
                </svg>
                <span>Nota Penjualan</span>
            </a>
        @endcan
        <a class="app-drawer-link" href="{{ route('dashboard') }}#laporan"
            @if (request()->routeIs('dashboard', 'transactions.index')) data-dashboard-nav="reports" @endif>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 3h10l4 4v14H5z" />
                <path d="M14 3v5h5M8 12h8M8 16h8" />
            </svg>
            <span>Laporan</span>
        </a>
    </nav>

    <div class="app-drawer-divider"></div>
    <p class="app-drawer-section-label">Pengaturan</p>
    <nav class="app-drawer-nav" aria-label="Pengaturan">
        @can('viewAny', \App\Models\User::class)
            <a class="app-drawer-link" href="{{ route('users.index') }}"
                @class(['is-active' => request()->routeIs('users.*')])
                @if (request()->routeIs('users.*')) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="10" cy="7" r="4" />
                    <path d="M20 8v6m3-3h-6" />
                </svg>
                <span>Manajemen Akun</span>
            </a>
        @endcan
        @can('viewSensitiveData', \App\Models\CarTransaction::class)
            <a class="app-drawer-link" href="{{ route('dashboard') }}#database" data-sidebar-database>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="8" ry="3" />
                    <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" />
                </svg>
                <span>Manajemen Database</span>
            </a>
        @endcan
    </nav>

    <div class="app-drawer-footer">
        <span class="app-drawer-status-dot" aria-hidden="true"></span>
        <span>
            <strong>Operasional aktif</strong>
            <span>Data showroom tersimpan</span>
        </span>
    </div>
</aside>
