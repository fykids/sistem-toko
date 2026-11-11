import { createRouter, createWebHistory } from 'vue-router';
import DashboardPage from './pages/DashboardPage.vue';
import MarketingCatalogPage from './pages/MarketingCatalogPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import UsersPage from './pages/UsersPage.vue';
import TransactionFormPage from './pages/TransactionFormPage.vue';
import TransactionDetailPage from './pages/TransactionDetailPage.vue';
import JualPage from './pages/JualPage.vue';
import SaleNotesPage from './pages/SaleNotesPage.vue';

const historyBase = window.location.pathname.startsWith('/app/') ? '/app/' : '/';

const router = createRouter({
    history: createWebHistory(historyBase),
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
            redirect: '/dashboard',
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
            path: '/nota-penjualan/:saleNote(\\d+)/edit',
            name: 'sale-note-edit',
            component: SaleNotesPage,
        },
        {
            path: '/nota-penjualan/:saleNote(\\d+)',
            name: 'sale-note-show',
            component: SaleNotesPage,
        },
        {
            path: '/nota-penjualan',
            name: 'sale-note-index',
            component: SaleNotesPage,
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('./pages/LoginPage.vue'),
        },
        {
            path: '/setup-master',
            name: 'setup-master',
            component: () => import('./pages/SetupMasterPage.vue'),
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
    if (window.spaUser && ['login', 'setup-master'].includes(String(to.name))) {
        return window.spaUser.role === 'marketing' ? '/marketing' : '/dashboard';
    }

    if (!window.spaUser && !['login', 'setup-master'].includes(String(to.name))) {
        return window.location.pathname.endsWith('/setup-master') ? '/setup-master' : '/login';
    }
});

export default router;
