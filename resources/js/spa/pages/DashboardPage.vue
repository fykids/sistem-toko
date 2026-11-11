<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { fetchDashboard } from '../services/dashboard.js';
import { apiRequest } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const refreshKey = ref(0);
const errorMessage = ref('');
const searchText = ref('');
const startDate = ref('');
const endDate = ref('');
const isDeletingTransaction = ref(false);
const successMessage = ref('');

const currentView = computed(() => route.query.view ?? 'dashboard');
const rows = computed(() => payload.value?.data ?? []);
const pagination = computed(() => payload.value?.meta ?? null);
const canViewSensitiveData = computed(() => payload.value?.permissions?.can_view_sensitive_data ?? false);

function updateQuery(nextValues, replace = false) {
    const query = { ...route.query, ...nextValues };
    delete query.page;

    return (replace ? router.replace : router.push)({ query });
}

function selectView(view) {
    updateQuery({ view });
}

function submitFilters() {
    updateQuery({
        q: searchText.value.trim() || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    });
}

function goToPage(page) {
    router.push({ query: { ...route.query, page: String(page) } });
}

function formatCurrency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

async function deleteTransaction(transaction) {
    if (!window.confirm(`Hapus transaksi ${transaction.plate_number}?`)) {
        return;
    }

    isDeletingTransaction.value = true;
    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await apiRequest(`/api/spa/transactions/${transaction.id}`, { method: 'DELETE' });
        successMessage.value = 'Data transaksi berhasil dihapus.';
        refreshKey.value++;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isDeletingTransaction.value = false;
        isLoading.value = false;
    }
}

watch(
    [() => route.fullPath, refreshKey],
    async (_path, _previousPath, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        isLoading.value = true;
        errorMessage.value = '';
        searchText.value = route.query.q ?? '';
        startDate.value = route.query.start_date ?? '';
        endDate.value = route.query.end_date ?? '';

        try {
            payload.value = await fetchDashboard(
                {
                    view: currentView.value,
                    q: route.query.q,
                    start_date: route.query.start_date,
                    end_date: route.query.end_date,
                    page: route.query.page,
                },
                controller.signal,
            );
        } catch (error) {
            if (error.name !== 'AbortError') {
                errorMessage.value = error.message;
            }
        } finally {
            if (!controller.signal.aborted) {
                isLoading.value = false;
            }
        }
    },
    { immediate: true },
);
</script>

<template>
    <section class="spa-dashboard showroom-content" aria-label="Dashboard">
        <p v-if="successMessage" class="spa-success" role="status">{{ successMessage }}</p>

        <section class="spa-table-panel showroom-inventory" aria-labelledby="dashboard-vehicles-title">
            <header class="toolbar spa-inventory-toolbar">
                <h2 id="dashboard-vehicles-title" class="toolbar-title">Data kendaraan</h2>
                <RouterLink class="btn btn-secondary spa-dashboard-add-action" to="/transactions/create"><span aria-hidden="true">+</span><span>Tambah</span></RouterLink>
            </header>
            <form class="spa-filters" @submit.prevent="submitFilters">
                <label class="spa-search">
                    <span>Cari kendaraan</span>
                    <input v-model="searchText" type="search" placeholder="Plat, merek, model..." :disabled="isLoading">
                </label>
                <label>
                    <span>Dari tanggal</span>
                    <input v-model="startDate" type="date" :disabled="isLoading">
                </label>
                <label>
                    <span>Sampai tanggal</span>
                    <input v-model="endDate" type="date" :disabled="isLoading">
                </label>
                <button type="submit" :disabled="isLoading">Terapkan filter</button>
            </form>
            <div class="spa-tabs" role="group" aria-label="Jenis data kendaraan">
                <button
                    v-for="(label, view) in { dashboard: 'Semua', inventory: 'Stok', sales: 'Terjual', reports: 'Laporan' }"
                    :key="view"
                    type="button"
                    :aria-pressed="currentView === view"
                    :disabled="isLoading"
                    @click="selectView(view)"
                >
                    {{ label }}
                </button>
            </div>
            <div v-if="isLoading" class="spa-loading" role="status" aria-live="polite">
                <span class="spa-spinner" aria-hidden="true"></span>
                Memuat data...
            </div>
            <div v-else-if="errorMessage" class="spa-error" role="alert">
                <p>{{ errorMessage }}</p>
                <button type="button" @click="updateQuery({}, true)">Coba lagi</button>
            </div>
            <template v-else>
                <div class="spa-table-scroll">
                    <table class="data-table transaction-list-table" :class="{ 'transaction-list-table--sensitive': canViewSensitiveData }">
                        <thead>
                            <tr>
                                <th>Kendaraan</th>
                                <th>Status</th>
                                <th>Tanggal beli</th>
                                <th>Pajak</th>
                                <th>Hari</th>
                                <th>Harga jual</th>
                                <th v-if="canViewSensitiveData">Total modal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="transaction in rows" :key="transaction.id">
                                <td data-label="Kendaraan">
                                    <span class="cell-primary">{{ transaction.plate_number }}</span>
                                    <span class="cell-secondary">{{ transaction.brand }} {{ transaction.model }} · {{ transaction.year }}</span>
                                </td>
                                <td data-label="Status"><span class="status-badge" :class="{ pending: transaction.status === 'sold' }">{{ transaction.status === 'sold' ? 'Terjual' : 'Stok' }}</span></td>
                                <td data-label="Tanggal beli">{{ formatDate(transaction.buy_date) }}</td>
                                <td data-label="Pajak">{{ transaction.tax_status.label }}</td>
                                <td data-label="Hari">{{ transaction.showroom_age_status.days ?? '—' }}<template v-if="transaction.showroom_age_status.days !== null"> hari</template></td>
                                <td class="numeric" data-label="Harga jual">{{ formatCurrency(transaction.selling_price) }}</td>
                                <td v-if="canViewSensitiveData" class="numeric" data-label="Total modal">{{ formatCurrency(transaction.total_capital) }}</td>
                                <td data-label="Aksi">
                                    <div class="spa-row-actions row-actions">
                                        <RouterLink class="btn btn-secondary" :to="`/transactions/${transaction.id}`">Detail</RouterLink>
                                        <RouterLink v-if="transaction.can_update" class="btn btn-secondary" :to="`/transactions/${transaction.id}/edit`">Edit</RouterLink>
                                        <button v-if="transaction.can_delete" class="btn btn-danger" type="button" :disabled="isDeletingTransaction" @click="deleteTransaction(transaction)">{{ isDeletingTransaction ? 'Menghapus...' : 'Hapus' }}</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="canViewSensitiveData ? 8 : 7" class="spa-empty">
                                    Belum ada data untuk filter ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="pagination && pagination.last_page > 1" class="spa-pagination">
                    <button type="button" :disabled="isLoading || pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">
                        Sebelumnya
                    </button>
                    <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                    <button type="button" :disabled="isLoading || pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">
                        Berikutnya
                    </button>
                </footer>
            </template>
        </section>
    </section>
</template>
