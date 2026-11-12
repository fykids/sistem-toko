<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const report = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');
const showFinancialDetails = ref(false);
const expandedSaleId = ref(null);
const sortMode = ref('date-desc');
const currentPage = ref(1);
const pageSize = ref(10);
const filters = reactive({
    search: '',
    start_date: '',
    end_date: '',
    payment_status: 'all',
    payment_method: 'all',
});

const rows = computed(() => report.value?.data ?? []);
const summary = computed(() => report.value?.summary ?? {});
const canViewSensitiveData = computed(() => report.value?.can_view_sensitive_data ?? false);
const sortedRows = computed(() => [...rows.value].sort((left, right) => {
    if (sortMode.value === 'date-asc') {
        return String(left.car_transaction.sell_date ?? '')
            .localeCompare(String(right.car_transaction.sell_date ?? ''));
    }
    if (sortMode.value === 'price-desc') {
        return Number(right.car_transaction.selling_price ?? 0)
            - Number(left.car_transaction.selling_price ?? 0);
    }
    if (sortMode.value === 'profit-desc') {
        return Number(right.car_transaction.net_profit ?? 0)
            - Number(left.car_transaction.net_profit ?? 0);
    }

    return String(right.car_transaction.sell_date ?? '')
        .localeCompare(String(left.car_transaction.sell_date ?? ''));
}));
const totalPages = computed(() => Math.max(1, Math.ceil(sortedRows.value.length / pageSize.value)));
const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;

    return sortedRows.value.slice(start, start + pageSize.value);
});
const rowStart = computed(() => sortedRows.value.length === 0
    ? 0
    : (currentPage.value - 1) * pageSize.value + 1);
const rowEnd = computed(() => Math.min(currentPage.value * pageSize.value, sortedRows.value.length));
const grandTotals = computed(() => sortedRows.value.reduce((totals, sale) => {
    const vehicle = sale.car_transaction;
    totals.sellingPrice += Number(vehicle.selling_price ?? 0);
    totals.totalPaid += Number(sale.total_paid ?? 0);
    totals.remaining += Math.max(0, Number(vehicle.remaining_payment ?? 0));
    if (canViewSensitiveData.value) {
        totals.netProfit += Number(vehicle.net_profit ?? 0);
        totals.totalCapital += Number(vehicle.total_capital ?? 0);
        totals.totalCommissions += Number(vehicle.commissions?.total ?? 0);
    }

    return totals;
}, {
    sellingPrice: 0,
    totalPaid: 0,
    remaining: 0,
    netProfit: 0,
    totalCapital: 0,
    totalCommissions: 0,
}));
const columnCount = computed(() => 9
    + (canViewSensitiveData.value ? 1 : 0)
    + (showFinancialDetails.value ? 2 + (canViewSensitiveData.value ? 2 : 0) : 0));
const activePeriodLabel = computed(() => {
    const startDate = typeof route.query.start_date === 'string' ? route.query.start_date : '';
    const endDate = typeof route.query.end_date === 'string' ? route.query.end_date : '';

    if (startDate && endDate) {
        return `${formatDate(startDate)} – ${formatDate(endDate)}`;
    }
    if (startDate) {
        return `Sejak ${formatDate(startDate)}`;
    }
    if (endDate) {
        return `Sampai ${formatDate(endDate)}`;
    }

    return 'Semua waktu';
});
const reportPrintDate = formatDate(new Date().toISOString().slice(0, 10));

function formatCurrency(value) {
    const amount = Number(value ?? 0);
    const roundedAmount = Number.isFinite(amount) ? Math.round(amount) : 0;
    const normalizedAmount = roundedAmount === 0 ? 0 : roundedAmount;

    return `Rp ${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    }).format(normalizedAmount)}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function paymentStatusLabel(status) {
    return status === 'lunas' ? 'Lunas' : 'Belum Lunas';
}

function paymentMethodLabel(method) {
    return method === 'leasing' ? 'Kredit / Leasing' : 'Cash / Tunai';
}

function printReport() {
    window.print();
}

function applyFilters() {
    const query = {};

    for (const key of ['search', 'start_date', 'end_date']) {
        const value = filters[key].trim();
        if (value) {
            query[key] = value;
        }
    }
    if (filters.payment_status !== 'all') {
        query.payment_status = filters.payment_status;
    }
    if (filters.payment_method !== 'all') {
        query.payment_method = filters.payment_method;
    }

    currentPage.value = 1;
    router.replace({ query });
}

function dateInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function applyQuickPreset(preset) {
    const today = new Date();
    const endDate = dateInputValue(today);
    let startDate = '';
    let selectedEndDate = '';

    if (preset === 'today') {
        startDate = endDate;
        selectedEndDate = endDate;
    } else if (preset === '7-days') {
        const firstDate = new Date(today);
        firstDate.setDate(firstDate.getDate() - 6);
        startDate = dateInputValue(firstDate);
        selectedEndDate = endDate;
    } else if (preset === 'month') {
        startDate = dateInputValue(new Date(today.getFullYear(), today.getMonth(), 1));
        selectedEndDate = endDate;
    } else if (preset === 'year') {
        startDate = dateInputValue(new Date(today.getFullYear(), 0, 1));
        selectedEndDate = endDate;
    }

    filters.start_date = startDate;
    filters.end_date = selectedEndDate;
    applyFilters();
}

function resetFilters() {
    filters.search = '';
    filters.start_date = '';
    filters.end_date = '';
    filters.payment_status = 'all';
    filters.payment_method = 'all';
    applyFilters();
}

function toggleExpanded(saleId) {
    expandedSaleId.value = expandedSaleId.value === saleId ? null : saleId;
}

function commissionValue(sale, field) {
    return Number(sale.car_transaction.commissions?.[field] ?? 0);
}

function csvCell(value) {
    const text = String(value ?? '');
    const safeText = typeof value === 'string' && /^[=+@\-]/.test(text.trim())
        ? `'${text}`
        : text;

    return `"${safeText.replaceAll('"', '""')}"`;
}

function exportCsv() {
    const headers = [
        'Nomor Nota',
        'Tanggal Jual',
        'Plat Nomor',
        'Merek',
        'Model',
        'Tahun',
        'Warna',
        'Konsumen',
        'No. HP',
        'Modal Beli',
        'Total Modal',
        'Harga Jual',
        'DP',
        'Pelunasan',
        'Pencairan Leasing',
        'Total Dibayar',
        'Sisa Pembayaran',
        'KR Jual',
        'OP Survei',
        'Fee CMO',
        'Total Komisi',
        'Metode',
        'Status',
        'Laba Bersih',
    ];
    const records = rows.value.map((sale) => [
        sale.receipt_number,
        sale.car_transaction.sell_date,
        sale.car_transaction.plate_number,
        sale.car_transaction.brand,
        sale.car_transaction.model,
        sale.car_transaction.year,
        sale.car_transaction.color,
        sale.nama_konsumen,
        sale.buyer_phone,
        canViewSensitiveData.value ? sale.car_transaction.buy_price : '',
        canViewSensitiveData.value ? sale.car_transaction.total_capital : '',
        sale.car_transaction.selling_price,
        sale.car_transaction.down_payment,
        sale.car_transaction.settlement_amount,
        sale.car_transaction.leasing_disbursement,
        sale.total_paid,
        sale.car_transaction.remaining_payment,
        canViewSensitiveData.value ? sale.car_transaction.commissions?.kr_jual : '',
        canViewSensitiveData.value ? sale.car_transaction.commissions?.op_survei : '',
        canViewSensitiveData.value ? sale.car_transaction.commissions?.fee_cmo : '',
        canViewSensitiveData.value ? sale.car_transaction.commissions?.total : '',
        paymentMethodLabel(sale.payment_method),
        paymentStatusLabel(sale.payment_status),
        canViewSensitiveData.value ? sale.car_transaction.net_profit : '',
    ]);
    const content = [headers, ...records]
        .map((record) => record.map(csvCell).join(','))
        .join('\r\n');
    const file = new Blob([`\uFEFF${content}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');

    link.href = url;
    link.download = `laporan-penjualan-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

watch(sortedRows, () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }
    expandedSaleId.value = null;
});

watch(
    () => route.fullPath,
    async (_path, _previousPath, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        isLoading.value = true;
        errorMessage.value = '';
        filters.search = typeof route.query.search === 'string' ? route.query.search : '';
        filters.start_date = typeof route.query.start_date === 'string' ? route.query.start_date : '';
        filters.end_date = typeof route.query.end_date === 'string' ? route.query.end_date : '';
        filters.payment_status = typeof route.query.payment_status === 'string'
            ? route.query.payment_status
            : 'all';
        filters.payment_method = typeof route.query.payment_method === 'string'
            ? route.query.payment_method
            : 'all';
        currentPage.value = 1;

        const query = new URLSearchParams();
        for (const key of ['search', 'start_date', 'end_date', 'payment_status', 'payment_method']) {
            if (route.query[key]) {
                query.set(key, String(route.query[key]));
            }
        }

        try {
            const queryString = query.toString();
            report.value = await apiRequest(
                `/api/spa/laporan-penjualan${queryString ? `?${queryString}` : ''}`,
                { signal: controller.signal },
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
    <section class="sales-report-page showroom-content w-full max-w-full min-w-0 space-y-4 overflow-x-hidden px-4 py-4 sm:px-6 lg:px-8">
        <header class="report-page-heading flex min-w-0 flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Laporan</p>
                <h1 class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl">Laporan Penjualan</h1>
                <p class="mt-1 text-sm text-slate-500">Periode laporan: <span class="font-medium text-slate-700">{{ activePeriodLabel }}</span></p>
            </div>
            <div class="report-actions flex flex-wrap gap-2">
                <button class="btn btn-secondary" type="button" @click="resetFilters">
                    Reset Filter
                </button>
                <button class="btn btn-secondary" type="button" :disabled="isLoading || rows.length === 0" @click="exportCsv">
                    Export Excel-compatible CSV
                </button>
                <button class="btn btn-accent" type="button" :disabled="isLoading || rows.length === 0" @click="printReport">
                    Export PDF / Cetak
                </button>
            </div>
        </header>

        <section class="report-filters min-w-0 space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filter laporan">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-semibold text-slate-500">Periode cepat</span>
                <button class="quick-period" type="button" @click="applyQuickPreset('today')">Hari Ini</button>
                <button class="quick-period" type="button" @click="applyQuickPreset('7-days')">7 Hari Terakhir</button>
                <button class="quick-period" type="button" @click="applyQuickPreset('month')">Bulan Ini</button>
                <button class="quick-period" type="button" @click="applyQuickPreset('year')">Tahun Ini</button>
                <button class="quick-period" type="button" @click="applyQuickPreset('all')">Semua Waktu</button>
            </div>

            <form class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-12" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5 text-xs font-medium text-slate-600 lg:col-span-3">
                    Cari penjualan
                    <input v-model="filters.search" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900" type="search" maxlength="100" placeholder="Nama, plat, merek/model">
                </label>
                <label class="grid min-w-0 gap-1.5 text-xs font-medium text-slate-600 lg:col-span-2">
                    Dari tanggal
                    <input v-model="filters.start_date" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900" type="date">
                </label>
                <label class="grid min-w-0 gap-1.5 text-xs font-medium text-slate-600 lg:col-span-2">
                    Sampai tanggal
                    <input v-model="filters.end_date" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900" type="date">
                </label>
                <label class="grid min-w-0 gap-1.5 text-xs font-medium text-slate-600 lg:col-span-2">
                    Status pembayaran
                    <select v-model="filters.payment_status" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                        <option value="all">Semua</option>
                        <option value="lunas">Lunas</option>
                        <option value="belum_lunas">Belum Lunas</option>
                    </select>
                </label>
                <label class="grid min-w-0 gap-1.5 text-xs font-medium text-slate-600 lg:col-span-2">
                    Metode / skema
                    <select v-model="filters.payment_method" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                        <option value="all">Semua</option>
                        <option value="cash">Cash / Tunai</option>
                        <option value="leasing">Kredit / Leasing</option>
                    </select>
                </label>
                <div class="flex min-w-0 items-end lg:col-span-1">
                    <button class="btn btn-accent w-full justify-center px-2" type="submit" :disabled="isLoading">Terapkan</button>
                </div>
            </form>
        </section>

        <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
        <div v-if="isLoading" class="spa-loading" role="status">
            <span class="spa-spinner" aria-hidden="true"></span>
            Memuat laporan penjualan...
        </div>
        <template v-else-if="report">
            <div class="report-print-heading">
                <p class="text-xs font-bold uppercase tracking-[0.2em]">Cempaka Jaya Mobilindo</p>
                <h2>Laporan Penjualan</h2>
                <p>Periode: {{ activePeriodLabel }} <span class="print-separator">|</span> Dicetak {{ reportPrintDate }}</p>
            </div>
            <section class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan penjualan">
                <article class="kpi-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-xs font-medium text-slate-500">Total Unit Terjual</h2>
                            <p class="mt-2 text-lg font-bold tabular-nums text-slate-900 sm:text-xl">{{ Number(summary.total_units ?? 0).toLocaleString('id-ID') }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                <span class="font-medium text-emerald-700">{{ Number(summary.paid_units ?? 0).toLocaleString('id-ID') }} lunas</span>
                                <span class="px-1 text-slate-300">·</span>
                                <span class="font-medium text-amber-700">{{ Number(summary.unpaid_units ?? 0).toLocaleString('id-ID') }} belum lunas</span>
                            </p>
                        </div>
                        <span class="kpi-icon bg-emerald-50 text-emerald-700" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 13.5h18l-1.6-5a2 2 0 0 0-1.9-1.4H6.5A2 2 0 0 0 4.6 8.5L3 13.5Z"/><path d="M5 13.5v4m14-4v4M7 17.5h10M7 17.5v1m10-1v1M6.5 10h11"/></svg>
                        </span>
                    </div>
                </article>
                <article class="kpi-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-xs font-medium text-slate-500">Total Omzet Penjualan</h2>
                            <p class="mt-2 break-words text-lg font-bold tabular-nums text-slate-900 sm:text-xl">{{ formatCurrency(summary.total_omzet) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Total nilai harga jual bruto</p>
                        </div>
                        <span class="kpi-icon bg-blue-50 text-blue-700" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5m0 14h16"/><path d="m7 15 3-4 3 2 5-6"/><path d="M15 7h3v3"/></svg>
                        </span>
                    </div>
                </article>
                <article class="kpi-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-xs font-medium text-slate-500">Dana Masuk &amp; Piutang</h2>
                            <p class="mt-2 break-words text-lg font-bold tabular-nums text-slate-900 sm:text-xl">{{ formatCurrency(summary.total_paid) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Sudah dibayar</p>
                            <p class="mt-2 text-xs font-semibold tabular-nums text-amber-700">Sisa piutang {{ formatCurrency(summary.total_receivable) }}</p>
                        </div>
                        <span class="kpi-icon bg-amber-50 text-amber-700" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M3.5 9h17M7 14h4"/></svg>
                        </span>
                    </div>
                </article>
                <article class="kpi-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-xs font-medium text-slate-500">Total Laba Bersih</h2>
                            <p class="mt-2 break-words text-lg font-bold tabular-nums sm:text-xl" :class="Number(summary.total_net_profit ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                {{ canViewSensitiveData ? formatCurrency(summary.total_net_profit) : 'Tidak tersedia' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ canViewSensitiveData ? 'Laba / rugi bersih periode ini' : 'Hanya tersedia untuk Master' }}</p>
                        </div>
                        <span class="kpi-icon bg-violet-50 text-violet-700" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18h16M6 15l4-4 3 2 5-6"/><path d="M15 7h3v3"/></svg>
                        </span>
                    </div>
                </article>
            </section>

            <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="sales-report-table-title">
                <header class="table-toolbar flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <h2 id="sales-report-table-title" class="text-base font-semibold text-slate-900">Rekap Penjualan</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ sortedRows.length.toLocaleString('id-ID') }} transaksi ditemukan</p>
                    </div>
                    <div class="table-controls flex min-w-0 flex-wrap items-center gap-2">
                        <div class="flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Mode tampilan tabel">
                            <button class="table-mode" :class="{ 'table-mode-active': !showFinancialDetails }" type="button" :aria-pressed="!showFinancialDetails" @click="showFinancialDetails = false">
                                Tampilan Ringkas
                            </button>
                            <button class="table-mode" :class="{ 'table-mode-active': showFinancialDetails }" type="button" :aria-pressed="showFinancialDetails" @click="showFinancialDetails = true">
                                Tampilan Detail Finansial
                            </button>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            Urutkan
                            <select v-model="sortMode" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs text-slate-800">
                                <option value="date-desc">Tanggal terbaru</option>
                                <option value="date-asc">Tanggal terlama</option>
                                <option value="price-desc">Harga jual tertinggi</option>
                                <option v-if="canViewSensitiveData" value="profit-desc">Laba tertinggi</option>
                            </select>
                        </label>
                    </div>
                </header>
                <div class="report-table-scroll w-full min-w-0 overflow-x-auto">
                    <table class="report-table screen-report-table min-w-[1100px] w-full border-collapse text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
                            <tr>
                                <th class="w-9 px-2 py-2.5"><span class="sr-only">Detail</span></th>
                                <th class="whitespace-nowrap px-3 py-2.5">Tanggal Jual</th>
                                <th class="px-3 py-2.5">Unit Kendaraan</th>
                                <th class="px-3 py-2.5">Konsumen</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-right">Harga Jual</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-right">Total Dibayar</th>
                                <th class="whitespace-nowrap px-3 py-2.5 text-right">Sisa Pembayaran</th>
                                <th class="whitespace-nowrap px-3 py-2.5">Status</th>
                                <template v-if="showFinancialDetails">
                                    <th v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-2.5 text-right">Modal Beli</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">DP</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Pencairan Leasing</th>
                                    <th v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-2.5 text-right">Total Komisi</th>
                                </template>
                                <th v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-2.5 text-right">Laba Bersih</th>
                                <th class="report-row-actions px-3 py-2.5">Aksi</th>
                            </tr>
                        </thead>
                        <tbody v-if="paginatedRows.length" class="divide-y divide-slate-100">
                            <template v-for="sale in paginatedRows" :key="sale.id">
                                <tr class="report-data-row" :class="{ 'report-data-row-expanded': expandedSaleId === sale.id }" @click="toggleExpanded(sale.id)">
                                    <td class="px-2 py-3 text-center">
                                        <button class="expand-button" type="button" :aria-expanded="expandedSaleId === sale.id" :aria-label="expandedSaleId === sale.id ? 'Tutup detail transaksi' : 'Buka detail transaksi'" @click.stop="toggleExpanded(sale.id)">
                                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" :class="{ 'rotate-180': expandedSaleId === sale.id }"><path d="m5 7.5 5 5 5-5"/></svg>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ formatDate(sale.car_transaction.sell_date) }}</td>
                                    <td class="min-w-44 px-3 py-3">
                                        <strong class="block text-sm font-semibold text-slate-900">{{ sale.car_transaction.plate_number }}</strong>
                                        <span class="text-xs text-slate-500">{{ sale.car_transaction.brand }} {{ sale.car_transaction.model }} · {{ sale.car_transaction.year }} · {{ sale.car_transaction.color }}</span>
                                        <span class="mt-1 block text-[11px] text-slate-400">{{ sale.receipt_number }}</span>
                                    </td>
                                    <td class="min-w-40 px-3 py-3">
                                        <strong class="block font-medium text-slate-800">{{ sale.nama_konsumen }}</strong>
                                        <span class="text-xs text-slate-500">{{ sale.buyer_phone || 'No. HP tidak tersedia' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.car_transaction.selling_price) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.total_paid) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums" :class="Number(sale.car_transaction.remaining_payment ?? 0) > 0 ? 'font-semibold text-amber-700' : 'text-slate-500'">
                                        {{ formatCurrency(sale.car_transaction.remaining_payment) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="status-badge" :class="{ pending: sale.payment_status !== 'lunas' }">
                                            {{ paymentStatusLabel(sale.payment_status) }}
                                        </span>
                                    </td>
                                    <template v-if="showFinancialDetails">
                                        <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.car_transaction.buy_price) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.car_transaction.down_payment) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.car_transaction.leasing_disbursement) }}</td>
                                        <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(sale.car_transaction.commissions?.total) }}</td>
                                    </template>
                                    <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums" :class="Number(sale.car_transaction.net_profit ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                        {{ formatCurrency(sale.car_transaction.net_profit) }}
                                    </td>
                                    <td class="report-row-actions px-3 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            <RouterLink class="btn btn-secondary" :to="`/transactions/${sale.car_transaction.id}`" @click.stop>Detail</RouterLink>
                                            <RouterLink class="btn btn-secondary" :to="{ path: '/nota-penjualan', query: { nota: String(sale.id) } }" @click.stop>Nota</RouterLink>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedSaleId === sale.id" class="expanded-row">
                                    <td :colspan="columnCount" class="px-4 py-3">
                                        <div class="expanded-details grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <div class="detail-tile">
                                                <p class="detail-label">Skema Pembayaran</p>
                                                <p class="detail-value">{{ paymentMethodLabel(sale.payment_method) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">DP {{ formatCurrency(sale.car_transaction.down_payment) }} · Pelunasan {{ formatCurrency(sale.car_transaction.settlement_amount) }} · Leasing {{ formatCurrency(sale.car_transaction.leasing_disbursement) }}</p>
                                            </div>
                                            <div class="detail-tile">
                                                <p class="detail-label">Kontak Konsumen</p>
                                                <p class="detail-value">{{ sale.buyer_phone || 'Tidak tersedia' }}</p>
                                            </div>
                                            <div v-if="canViewSensitiveData" class="detail-tile">
                                                <p class="detail-label">Komisi Penjualan</p>
                                                <p class="detail-value">KR Jual {{ formatCurrency(commissionValue(sale, 'kr_jual')) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">OP Survei {{ formatCurrency(commissionValue(sale, 'op_survei')) }} · Fee CMO {{ formatCurrency(commissionValue(sale, 'fee_cmo')) }}</p>
                                            </div>
                                            <div v-if="canViewSensitiveData" class="detail-tile">
                                                <p class="detail-label">Modal &amp; Biaya Unit</p>
                                                <p class="detail-value">{{ formatCurrency(sale.car_transaction.total_capital) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Modal dan biaya unit terkait transaksi</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td class="px-4 py-8 text-center text-sm text-slate-500" :colspan="columnCount">
                                    Tidak ada transaksi penjualan untuk filter ini.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="sortedRows.length" class="border-t-2 border-slate-200 bg-slate-50 text-xs font-bold text-slate-700">
                            <tr>
                                <td class="px-3 py-3" :colspan="4">Grand Total · {{ sortedRows.length.toLocaleString('id-ID') }} transaksi</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(grandTotals.sellingPrice) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(grandTotals.totalPaid) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(grandTotals.remaining) }}</td>
                                <td></td>
                                <template v-if="showFinancialDetails">
                                    <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(grandTotals.totalCapital) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right tabular-nums">{{ formatCurrency(grandTotals.totalCommissions) }}</td>
                                </template>
                                <td v-if="canViewSensitiveData" class="whitespace-nowrap px-3 py-3 text-right tabular-nums" :class="grandTotals.netProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatCurrency(grandTotals.netProfit) }}</td>
                                <td class="report-row-actions"></td>
                            </tr>
                        </tfoot>
                    </table>

                    <table class="report-table report-print-table w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th>Tanggal Jual</th>
                                <th>Nomor Nota</th>
                                <th>Unit Kendaraan</th>
                                <th>Konsumen</th>
                                <th>No. HP</th>
                                <th class="text-right">Harga Jual</th>
                                <th class="text-right">DP</th>
                                <th class="text-right">Pelunasan</th>
                                <th class="text-right">Pencairan Leasing</th>
                                <th class="text-right">Total Dibayar</th>
                                <th class="text-right">Sisa</th>
                                <th>Status</th>
                                <th v-if="canViewSensitiveData" class="text-right">Modal</th>
                                <th v-if="canViewSensitiveData" class="text-right">Komisi</th>
                                <th v-if="canViewSensitiveData" class="text-right">Laba Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in sortedRows" :key="`print-${sale.id}`">
                                <td>{{ formatDate(sale.car_transaction.sell_date) }}</td>
                                <td>{{ sale.receipt_number }}</td>
                                <td>{{ sale.car_transaction.plate_number }} · {{ sale.car_transaction.brand }} {{ sale.car_transaction.model }} · {{ sale.car_transaction.year }} · {{ sale.car_transaction.color }}</td>
                                <td>{{ sale.nama_konsumen }}</td>
                                <td>{{ sale.buyer_phone || '—' }}</td>
                                <td class="text-right">{{ formatCurrency(sale.car_transaction.selling_price) }}</td>
                                <td class="text-right">{{ formatCurrency(sale.car_transaction.down_payment) }}</td>
                                <td class="text-right">{{ formatCurrency(sale.car_transaction.settlement_amount) }}</td>
                                <td class="text-right">{{ formatCurrency(sale.car_transaction.leasing_disbursement) }}</td>
                                <td class="text-right">{{ formatCurrency(sale.total_paid) }}</td>
                                <td class="text-right">{{ formatCurrency(sale.car_transaction.remaining_payment) }}</td>
                                <td>{{ paymentStatusLabel(sale.payment_status) }}</td>
                                <td v-if="canViewSensitiveData" class="text-right">{{ formatCurrency(sale.car_transaction.total_capital) }}</td>
                                <td v-if="canViewSensitiveData" class="text-right">{{ formatCurrency(sale.car_transaction.commissions?.total) }}</td>
                                <td v-if="canViewSensitiveData" class="text-right">{{ formatCurrency(sale.car_transaction.net_profit) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5"><strong>Grand Total · {{ sortedRows.length.toLocaleString('id-ID') }} transaksi</strong></td>
                                <td class="text-right"><strong>{{ formatCurrency(grandTotals.sellingPrice) }}</strong></td>
                                <td colspan="3"></td>
                                <td class="text-right"><strong>{{ formatCurrency(grandTotals.totalPaid) }}</strong></td>
                                <td class="text-right"><strong>{{ formatCurrency(grandTotals.remaining) }}</strong></td>
                                <td></td>
                                <td v-if="canViewSensitiveData" class="text-right"><strong>{{ formatCurrency(grandTotals.totalCapital) }}</strong></td>
                                <td v-if="canViewSensitiveData" class="text-right"><strong>{{ formatCurrency(grandTotals.totalCommissions) }}</strong></td>
                                <td v-if="canViewSensitiveData" class="text-right"><strong>{{ formatCurrency(grandTotals.netProfit) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <footer class="pagination-controls flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span>Menampilkan {{ rowStart }}–{{ rowEnd }} dari {{ sortedRows.length }}</span>
                        <label class="flex items-center gap-2">
                            Baris
                            <select v-model.number="pageSize" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-800">
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="pagination-button" type="button" :disabled="currentPage <= 1" @click="currentPage -= 1">Sebelumnya</button>
                        <span class="min-w-16 text-center text-xs text-slate-500">Halaman {{ currentPage }} / {{ totalPages }}</span>
                        <button class="pagination-button" type="button" :disabled="currentPage >= totalPages" @click="currentPage += 1">Berikutnya</button>
                    </div>
                </footer>
            </section>
        </template>
    </section>
</template>

<style scoped>
.kpi-card {
    min-width: 0;
    border: 1px solid rgb(226 232 240);
    border-radius: 1rem;
    background: white;
    padding: 1rem;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.kpi-icon {
    display: inline-flex;
    width: 2.5rem;
    height: 2.5rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
}

.kpi-icon svg {
    width: 1.25rem;
    height: 1.25rem;
}

.quick-period,
.pagination-button {
    border: 1px solid rgb(226 232 240);
    border-radius: 0.5rem;
    background: white;
    padding: 0.45rem 0.7rem;
    color: rgb(71 85 105);
    font-size: 0.75rem;
    font-weight: 600;
    transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease;
}

.quick-period:hover,
.pagination-button:not(:disabled):hover {
    border-color: rgb(16 185 129);
    background: rgb(236 253 245);
    color: rgb(4 120 87);
}

.pagination-button:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.table-mode {
    border-radius: 0.4rem;
    padding: 0.45rem 0.65rem;
    color: rgb(100 116 139);
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}

.table-mode-active {
    background: white;
    color: rgb(15 23 42);
    box-shadow: 0 1px 3px rgb(15 23 42 / 0.12);
}

.report-table th,
.report-table td {
    border-bottom: 1px solid rgb(241 245 249);
    vertical-align: top;
}

.report-data-row {
    cursor: pointer;
    transition: background-color 120ms ease;
}

.report-data-row:hover,
.report-data-row-expanded {
    background: rgb(248 250 252);
}

.expand-button {
    display: inline-flex;
    width: 1.75rem;
    height: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    color: rgb(100 116 139);
}

.expand-button:hover {
    background: rgb(226 232 240);
    color: rgb(15 23 42);
}

.expand-button svg {
    width: 1rem;
    height: 1rem;
    transition: transform 120ms ease;
}

.expanded-row {
    background: rgb(248 250 252);
}

.detail-tile {
    min-width: 0;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.75rem;
    background: white;
    padding: 0.75rem;
}

.detail-label {
    color: rgb(100 116 139);
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.detail-value {
    margin-top: 0.3rem;
    overflow-wrap: anywhere;
    color: rgb(30 41 59);
    font-size: 0.8rem;
    font-weight: 600;
}

.report-print-heading,
.report-print-table {
    display: none;
}

@media print {
    @page {
        size: landscape;
        margin: 12mm;
    }

    :global(.showroom-topbar),
    :global(.app-navigation-drawer),
    :global(.app-menu-backdrop),
    :global(.spa-route-loading) {
        display: none !important;
    }

    :global(.page-shell--app) {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    :global(.page-shell--app > .spa-main) {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
    }

    .sales-report-page {
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
        color: #0f172a !important;
        font-family: Arial, sans-serif !important;
    }

    .report-actions,
    .report-filters,
    .report-row-actions,
    .table-controls,
    .pagination-controls,
    .screen-report-table {
        display: none !important;
    }

    .report-page-heading {
        display: none !important;
    }

    .report-print-heading {
        display: block !important;
        margin-bottom: 1rem;
        border-bottom: 2px solid #047857;
        padding-bottom: 0.75rem;
        text-align: center;
    }

    .report-print-heading h2 {
        margin: 0.25rem 0;
        font-size: 20px;
        font-weight: 700;
    }

    .report-print-heading p {
        margin: 0.2rem 0;
        color: #475569;
        font-size: 10px;
    }

    .report-print-heading .print-separator {
        padding: 0 0.4rem;
    }

    .kpi-card {
        box-shadow: none !important;
        break-inside: avoid;
    }

    .report-table-scroll {
        overflow: visible !important;
    }

    .report-print-table {
        display: table !important;
        width: 100% !important;
        min-width: 0 !important;
        table-layout: auto;
        font-size: 8px !important;
    }

    .report-print-table th,
    .report-print-table td {
        border: 1px solid #cbd5e1 !important;
        padding: 4px !important;
    }

    .report-print-table th {
        background: #f1f5f9 !important;
        font-weight: 700;
        text-align: left;
    }

    .report-print-table thead {
        display: table-header-group;
    }

    .report-print-table tfoot {
        display: table-footer-group;
    }

    .report-print-table tr {
        break-inside: avoid;
    }

    .status-badge {
        border: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        color: #0f172a !important;
    }
}
</style>
