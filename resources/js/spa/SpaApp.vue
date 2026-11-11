<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import router from './router.js';
import { apiRequest } from './services/api.js';

const route = useRoute();
const vueRouter = useRouter();
const isNavigating = ref(false);
const spaUser = ref(window.spaUser);
const isLoggingOut = ref(false);
const isDrawerOpen = ref(false);
const isProfileMenuOpen = ref(false);
const pageShellClasses = computed(() => {
    const classes = {
        'page-shell--dashboard': route.name === 'dashboard',
    };

    if (route.path.startsWith('/transactions')) {
        classes['page-shell--transaction-form'] = route.name !== 'transaction-show';
        classes['page-shell--transaction-form-entry'] = ['transaction-create', 'transaction-edit'].includes(String(route.name));
        classes['page-shell--receipt'] = route.name === 'transaction-show';
    }

    if (route.path.startsWith('/jual')) {
        classes['page-shell--jual'] = true;
        if (['jual-create', 'jual-edit'].includes(String(route.name))) {
            classes['page-shell--transaction-form'] = true;
            classes['page-shell--transaction-form-entry'] = true;
            classes['page-shell--jual-entry'] = true;
        }
    }

    if (route.path.startsWith('/nota-penjualan')) {
        classes['page-shell--sale-note'] = route.name !== 'sale-note-edit';
        classes['page-shell--sale-note-form'] = route.name === 'sale-note-edit';
    }

    return classes;
});
const pageTitle = computed(() => ({
    dashboard: 'Dashboard',
    users: 'Manajemen Akun',
    profile: 'Pengaturan Akun',
    'marketing-stock': 'Katalog Stok',
    'transaction-create': 'Transaksi Baru',
    'transaction-edit': 'Edit Transaksi',
    'transaction-show': 'Detail Transaksi',
    'jual-index': 'Laporan Penjualan',
    'jual-create': 'Input Jual',
    'jual-edit': 'Edit Jual',
    'jual-show': 'Detail Jual',
    'jual-payment': 'Catat Pelunasan',
    'sale-note-index': 'Daftar Nota Penjualan',
    'sale-note-edit': 'Edit Nota Penjualan',
    'sale-note-show': 'Detail Nota Penjualan',
})[String(route.name)] ?? 'Akun');
const breadcrumb = computed(() => route.path.startsWith('/jual')
    ? { label: 'Jual', to: '/jual' }
    : route.path.startsWith('/nota-penjualan')
        ? { label: 'Nota Penjualan', to: '/nota-penjualan' }
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
        <header class="showroom-topbar" :class="{ 'is-menu-open': isDrawerOpen }">
            <div class="showroom-topbar-inner">
                <button class="app-menu-toggle" type="button" aria-label="Buka navigasi" :aria-expanded="isDrawerOpen" aria-controls="app-navigation" @click="isDrawerOpen = !isDrawerOpen">
                    <svg class="app-menu-icon app-menu-icon--open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <svg class="app-menu-icon app-menu-icon--close" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
                <nav class="showroom-topbar-context" aria-label="Breadcrumb">
                    <RouterLink :to="breadcrumb.to">{{ breadcrumb.label }}</RouterLink><span aria-hidden="true">/</span><strong>{{ pageTitle }}</strong>
                </nav>
                <div class="showroom-topbar-account">
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
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="is-active" to="/dashboard"><span aria-hidden="true">▦</span>Dashboard</RouterLink>
                <RouterLink v-if="spaUser.role === 'marketing'" class="app-drawer-link" active-class="is-active" to="/marketing"><span aria-hidden="true">▣</span>Katalog stok</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="is-active" to="/transactions"><span aria-hidden="true">▤</span>Inventaris</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-link" active-class="is-active" to="/jual"><span aria-hidden="true">↗</span>Transaksi jual</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-sublink" active-class="is-active" to="/jual/create">Input / Transaksi Jual</RouterLink>
                <RouterLink v-if="spaUser.role !== 'marketing'" class="app-drawer-sublink" active-class="is-active" to="/nota-penjualan">Laporan Penjualan</RouterLink>
            </nav>
            <div class="app-drawer-divider"></div>
            <p class="app-drawer-section-label">Pengaturan</p>
            <nav class="app-drawer-nav" aria-label="Pengaturan">
                <RouterLink v-if="spaUser.role === 'master'" class="app-drawer-link" active-class="is-active" to="/users"><span aria-hidden="true">♙</span>Manajemen Akun</RouterLink>
                <RouterLink class="app-drawer-link" active-class="is-active" to="/settings"><span aria-hidden="true">⚙</span>Pengaturan Akun</RouterLink>
            </nav>
            <div class="app-drawer-footer"><span class="app-drawer-status-dot" aria-hidden="true"></span><span><strong>Operasional aktif</strong><span>Data showroom tersimpan</span></span></div>
        </aside>
        <div v-if="isNavigating" class="spa-route-loading" role="status" aria-live="polite">Memuat halaman...</div>
        <main class="spa-main" :class="{ 'spa-main--content-page': route.name === 'dashboard' || String(route.name).startsWith('transaction-') || String(route.name).startsWith('jual-') || String(route.name).startsWith('sale-note-') }">
            <RouterView :key="route.name ?? route.path" />
        </main>
    </div>
    <div v-else class="spa-shell">
        <RouterView :key="route.name ?? route.path" />
    </div>
</template>
