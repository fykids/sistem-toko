import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import MarketingCatalogPage from './pages/MarketingCatalogPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import UsersPage from './pages/UsersPage.vue';
import TransactionFormPage from './pages/TransactionFormPage.vue';
import TransactionDetailPage from './pages/TransactionDetailPage.vue';
import JualPage from './pages/JualPage.vue';
import LaporanPenjualanPage from './pages/LaporanPenjualanPage.vue';
import NotaPenjualanPage from './pages/NotaPenjualanPage.vue';
import PengeluaranShowroomPage from './pages/PengeluaranShowroomPage.vue';

const router = createRouter({
    history: createWebHistory('/app/'),
    routes: [
        {
            path: '/',
            redirect: () => window.spaUser?.role === 'marketing' ? '/marketing' : '/dashboard',
        },
        {
            path: '/dashboard',
            name: 'dashboard',
            component: DashboardPage,
        },
        {
            path: '/users',
            name: 'users',
            component: UsersPage,
        },
        {
            path: '/settings',
            name: 'profile',
            component: ProfilePage,
        },
        {
            path: '/marketing',
            name: 'marketing-stock',
            component: MarketingCatalogPage,
        },
        {
            path: '/marketing/:transaction',
            name: 'marketing-vehicle',
            component: MarketingCatalogPage,
        },
        {
            path: '/transactions/create',
            name: 'transaction-create',
            component: TransactionFormPage,
        },
        {
            path: '/transactions/:transaction/edit',
            name: 'transaction-edit',
            component: TransactionFormPage,
        },
        {
            path: '/transactions/:transaction',
            name: 'transaction-show',
            component: TransactionDetailPage,
        },
        {
            path: '/transactions',
            name: 'transactions',
            component: DashboardPage,
        },
        {
            path: '/jual/create',
            name: 'jual-create',
            component: JualPage,
        },
        {
            path: '/jual/:buyer/edit',
            name: 'jual-edit',
            component: JualPage,
        },
        {
            path: '/jual/:buyer/pelunasan',
            name: 'jual-payment',
            component: JualPage,
        },
        {
            path: '/jual/:buyer',
            name: 'jual-show',
            component: JualPage,
        },
        {
            path: '/jual',
            name: 'jual-index',
            component: JualPage,
        },
        {
            path: '/laporan-penjualan',
            name: 'sales-report',
            component: LaporanPenjualanPage,
        },
        {
            path: '/nota-penjualan',
            name: 'sales-receipts',
            component: NotaPenjualanPage,
        },
        {
            path: '/pengeluaran',
            name: 'showroom-expenses',
            component: PengeluaranShowroomPage,
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('./pages/LoginPage.vue'),
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: () => window.spaUser
                ? (window.spaUser.role === 'marketing' ? '/marketing' : '/dashboard')
                : '/login',
        },
    ],
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach((to) => {
    const hasApiToken = Boolean(sessionStorage.getItem('apiToken'));

    if (!hasApiToken) {
        window.spaUser = null;
    }

    if (window.spaUser && hasApiToken && to.name === 'login') {
        return window.spaUser.role === 'marketing' ? '/marketing' : '/dashboard';
    }

    if ((!window.spaUser || !hasApiToken) && to.name !== 'login') {
        return '/login';
    }
});

export default router;
