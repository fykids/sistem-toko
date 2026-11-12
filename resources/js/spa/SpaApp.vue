<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import router from './router.js';
import { apiRequest } from './services/api.js';

const route = useRoute();
const vueRouter = useRouter();
const isNavigating = ref(false);
const spaUser = ref(window.spaUser && sessionStorage.getItem('apiToken') ? window.spaUser : null);
const isLoggingOut = ref(false);
const isDrawerOpen = ref(false);
const isProfileMenuOpen = ref(false);
function isSidebarActive(menu) {
    const routeName = String(route.name ?? '');

    if (menu === 'dashboard') {
        return routeName === 'dashboard';
    }

    if (menu === 'transactions') {
        return routeName === 'transactions' || routeName.startsWith('transaction-');
    }

    if (menu === 'sales') {
        return routeName.startsWith('jual-') && routeName !== 'jual-create';
    }

    if (menu === 'sale-create') {
        return routeName === 'jual-create';
    }

    if (menu === 'sales-report') {
        return routeName === 'sales-report';
    }

    if (menu === 'sales-receipts') {
        return routeName === 'sales-receipts';
    }

    if (menu === 'showroom-expenses') {
        return routeName === 'showroom-expenses';
    }

    if (menu === 'marketing') {
        return ['marketing-stock', 'marketing-vehicle'].includes(routeName);
    }

    if (menu === 'users') {
        return routeName === 'users';
    }

    return menu === 'settings' && routeName === 'profile';
}
const pageShellClasses = computed(() => {
    const classes = {
        'page-shell--dashboard': ['dashboard', 'transactions'].includes(String(route.name)),
    };

    if (route.path.startsWith('/transactions')) {
        classes['page-shell--transaction-form'] = route.name !== 'transaction-show';
        classes['page-shell--transaction-form-entry'] = ['transaction-create', 'transaction-edit'].includes(String(route.name));
    }

    if (route.path.startsWith('/jual')) {
        classes['page-shell--jual'] = true;
        if (['jual-create', 'jual-edit'].includes(String(route.name))) {
            classes['page-shell--transaction-form'] = true;
            classes['page-shell--transaction-form-entry'] = true;
            classes['page-shell--jual-entry'] = true;
        }
    }

    return classes;
});
const pageTitle = computed(() => ({
    dashboard: 'Dashboard',
    transactions: 'Inventaris',
    users: 'Manajemen Akun',
    profile: 'Pengaturan Akun',
    'marketing-stock': 'Katalog Stok',
    'transaction-create': 'Transaksi Baru',
    'transaction-edit': 'Edit Transaksi',
    'transaction-show': 'Detail Transaksi',
    'jual-index': 'Transaksi Jual',
    'jual-create': 'Input Jual',
    'jual-edit': 'Edit Jual',
    'jual-show': 'Detail Jual',
    'jual-payment': 'Catat Pelunasan',
    'sales-report': 'Laporan Penjualan',
    'sales-receipts': 'Nota Penjualan',
    'showroom-expenses': 'Pengeluaran Showroom',
})[String(route.name)] ?? 'Akun');
const breadcrumb = computed(() => route.path.startsWith('/jual')
    ? { label: 'Jual', to: '/jual' }
    : route.path.startsWith('/transactions')
        ? { label: 'Transaksi', to: '/transactions' }
        : { label: 'Showroom', to: '/dashboard' });

router.beforeEach(() => {
    isNavigating.value = true;
    isDrawerOpen.value = false;
    isProfileMenuOpen.value = false;
});

router.afterEach(() => {
    isNavigating.value = false;
});

router.onError(() => {
    isNavigating.value = false;
});

async function logout() {
    isLoggingOut.value = true;
    try {
        await apiRequest('/api/spa/logout', { method: 'POST' });
        sessionStorage.removeItem('apiToken');
        sessionStorage.removeItem('spaUser');
        window.spaUser = null;
        spaUser.value = null;
        await vueRouter.push('/login');
    } catch (error) {
        window.alert(error.message);
    } finally {
        isLoggingOut.value = false;
    }
}
</script>

<template>
    <div v-if="spaUser" class="page-shell page-shell--app" :class="pageShellClasses">
        <button class="app-menu-backdrop" type="button" aria-label="Tutup navigasi" :hidden="!isDrawerOpen" tabindex="-1" @click="isDrawerOpen = false"></button>
        <header class="showroom-topbar w-full max-w-full min-w-0" :class="{ 'is-menu-open': isDrawerOpen }">
            <div class="showroom-topbar-inner w-full max-w-full min-w-0">
                <button class="app-menu-toggle" type="button" aria-label="Buka navigasi" :aria-expanded="isDrawerOpen" aria-controls="app-navigation" @click="isDrawerOpen = !isDrawerOpen">
                    <svg class="app-menu-icon app-menu-icon--open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <svg class="app-menu-icon app-menu-icon--close" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
                <nav class="showroom-topbar-context min-w-0" aria-label="Breadcrumb">
                    <RouterLink :to="breadcrumb.to">{{ breadcrumb.label }}</RouterLink><span aria-hidden="true">/</span><strong>{{ pageTitle }}</strong>
                </nav>
                <div class="showroom-topbar-account min-w-0 max-w-full">
                    <button class="profile-menu-trigger" type="button" aria-haspopup="menu" :aria-expanded="isProfileMenuOpen" @click="isProfileMenuOpen = !isProfileMenuOpen">
                        <span class="session-user"><strong>{{ spaUser.name }}</strong><span class="session-role" :class="`session-role--${spaUser.role}`">{{ spaUser.role }}</span></span>
                        <svg class="profile-menu-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m7 10 5 5 5-5" /></svg>
                    </button>
                    <div v-if="isProfileMenuOpen" class="profile-menu-panel" role="menu">
                        <div class="profile-menu-identity"><strong>{{ spaUser.name }}</strong><span>{{ spaUser.username }}</span></div>
                        <div class="profile-menu-divider" aria-hidden="true"></div>
                        <RouterLink class="profile-menu-item" to="/settings" role="menuitem">Pengaturan Akun</RouterLink>
                        <button class="profile-menu-item profile-menu-item--danger" type="button" role="menuitem" :disabled="isLoggingOut" @click="logout">{{ isLoggingOut ? 'Keluar...' : 'Logout' }}</button>
                    </div>
                </div>
            </div>
        </header>
        <aside id="app-navigation" class="app-navigation-drawer" :class="{ 'is-visible': isDrawerOpen }" aria-label="Navigasi utama">
            <div class="app-drawer-header">
                <RouterLink class="app-drawer-brand" :to="spaUser.role === 'marketing' ? '/marketing' : '/dashboard'">
                    <span class="app-drawer-brand-mark" aria-hidden="true">ST</span><span class="app-drawer-brand-copy"><strong>Sistem Toko</strong><span>Showroom mobil</span></span>
                </RouterLink>
                <button class="app-drawer-close" type="button" aria-label="Tutup navigasi" @click="isDrawerOpen = false">×</button>
            </div>
            <p class="app-drawer-section-label">Menu utama</p>
            <nav class="app-drawer-nav" aria-label="Menu utama">
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('dashboard') }" to="/dashboard"><span aria-hidden="true">▦</span>Dashboard</RouterLink>
                <RouterLink v-if="spaUser.role === 'marketing'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('marketing') }" to="/marketing"><span aria-hidden="true">▣</span>Katalog stok</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('transactions') }" to="/transactions"><span aria-hidden="true">▤</span>Inventaris</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('sales') }" to="/jual"><span aria-hidden="true">↗</span>Transaksi jual</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-sublink" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('sale-create') }" to="/jual/create">Input / Transaksi Jual</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('showroom-expenses') }" to="/pengeluaran"><span aria-hidden="true">↘</span>Pengeluaran Showroom</RouterLink>
            </nav>
            <template v-if="spaUser.role !== 'marketing'">
                <div class="app-drawer-divider"></div>
                <p class="app-drawer-section-label">LAPORAN</p>
                <nav class="app-drawer-nav" aria-label="Laporan">
                    <RouterLink class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('sales-report') }" to="/laporan-penjualan"><span aria-hidden="true">▤</span>Laporan Penjualan</RouterLink>
                    <RouterLink class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('sales-receipts') }" to="/nota-penjualan"><span aria-hidden="true">▧</span>Nota Penjualan</RouterLink>
                </nav>
            </template>
            <div class="app-drawer-divider"></div>
            <p class="app-drawer-section-label">Pengaturan</p>
            <nav class="app-drawer-nav" aria-label="Pengaturan">
                <RouterLink v-if="spaUser.role === 'master'" class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('users') }" to="/users"><span aria-hidden="true">♙</span>Manajemen Akun</RouterLink>
                <RouterLink class="app-drawer-link" active-class="router-link-state" exact-active-class="router-link-state" :class="{ 'is-active': isSidebarActive('settings') }" to="/settings"><span aria-hidden="true">⚙</span>Pengaturan Akun</RouterLink>
            </nav>
            <div class="app-drawer-footer"><span class="app-drawer-status-dot" aria-hidden="true"></span><span><strong>Operasional aktif</strong><span>Data showroom tersimpan</span></span></div>
        </aside>
        <div v-if="isNavigating" class="spa-route-loading" role="status" aria-live="polite">Memuat halaman...</div>
        <main class="spa-main min-w-0 flex-1 flex flex-col overflow-x-hidden w-full max-w-full" :class="{ 'spa-main--content-page': ['dashboard', 'transactions', 'sales-report', 'sales-receipts', 'showroom-expenses'].includes(String(route.name)) || String(route.name).startsWith('transaction-') || String(route.name).startsWith('jual-') }">
            <RouterView :key="route.name ?? route.path" />
        </main>
    </div>
    <div v-else class="spa-shell">
        <RouterView :key="route.name ?? route.path" />
    </div>
</template>
