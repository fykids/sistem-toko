<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { apiRequest, resolvePhotoUrl } from '../services/api.js';

const route = useRoute();
const transaction = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');
const activePhotoIndex = ref(0);
const unavailablePhotos = ref([]);
const canEdit = window.spaUser?.role !== 'marketing';

const vehicleStatus = computed(() => transaction.value?.sell_date
    ? 'Terjual'
    : 'Stok Tersedia');
const photos = computed(() => {
    const gallery = (transaction.value?.vehicle_photos ?? [])
        .map((photo) => ({ ...photo, url: resolvePhotoUrl(photo.url) }))
        .filter((photo) => photo.url);

    if (gallery.length > 0) {
        return gallery;
    }

    const legacyUrl = resolvePhotoUrl(transaction.value?.vehicle_photo_url);

    return legacyUrl
        ? [{ id: 'legacy', url: legacyUrl, is_cover: true }]
        : [];
});
const activePhoto = computed(() => photos.value[activePhotoIndex.value] ?? photos.value[0] ?? null);

const specifications = [
    ['Plat Nomor', 'plate_number'],
    ['Merek', 'brand'],
    ['Model', 'model'],
    ['Tahun', 'year'],
    ['Transmisi', 'transmission'],
    ['Warna', 'color'],
    ['Nomor Rangka', 'chassis_number'],
    ['Nomor Mesin', 'engine_number'],
    ['Kilometer (Km)', 'mileage_km'],
    ['Atas Nama STNK', 'owner_name'],
    ['Penanggung Jawab / Pengurus', 'manager_name'],
    ['Tanggal Beli', 'buy_date'],
    ['Tanggal Jual', 'sell_date'],
];

const paymentRows = [
    ['Harga Jual', 'selling_price'],
    ['DP', 'down_payment'],
    ['Pelunasan', 'settlement_amount'],
    ['Pencairan Leasing', 'leasing_disbursement'],
];

const paymentDateRows = [
    ['Tanggal Jual', 'sell_date'],
    ['Tanggal DP', 'down_payment_date'],
    ['Tanggal Pelunasan', 'settlement_date'],
    ['Tanggal Pencairan Leasing', 'leasing_disbursement_date'],
    ['Tanggal Rencana Pembayaran', 'planned_payment_date'],
];

const profitRows = [
    ['Laba Jual', 'gross_profit'],
    ['Ripan Leasing', 'leasing_ripan'],
    ['Ripan Asuransi', 'insurance_ripan'],
    ['Ripan Booster', 'booster_ripan'],
    ['Biaya Showroom', 'showroom_fee'],
    ['Total Biaya Penjualan', 'total_intertine_expense'],
];
const leasingOptions = computed(() => transaction.value?.leasing_prices
    ?? transaction.value?.leasing_options
    ?? []);
const totalPaid = computed(() => Number(transaction.value?.total_paid
    ?? Number(transaction.value?.down_payment ?? 0)
        + Number(transaction.value?.settlement_amount ?? 0)
        + Number(transaction.value?.leasing_disbursement ?? 0)));

onMounted(async () => {
    try {
        const response = await apiRequest(`/api/spa/transactions/${route.params.transaction}`);
        unavailablePhotos.value = [];
        activePhotoIndex.value = 0;
        transaction.value = response.data;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
});

function currency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
}

function displayValue(key, value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (key.endsWith('_date')) {
        const date = new Date(`${value}T00:00:00Z`);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(date);
    }

    if (key === 'mileage_km') {
        return `${Number(value).toLocaleString('id-ID')} km`;
    }

    return value;
}

function selectPhoto(index) {
    activePhotoIndex.value = index;
}

function photoUnavailable(url) {
    return !url || unavailablePhotos.value.includes(url);
}

function markPhotoUnavailable(url) {
    if (url && !unavailablePhotos.value.includes(url)) {
        unavailablePhotos.value.push(url);
    }
}

function paymentStatus() {
    if (!transaction.value?.sell_date) {
        return 'Belum terjual';
    }

    return Number(transaction.value.remaining_payment ?? 0) <= 0
        ? 'Lunas'
        : 'Belum lunas';
}
</script>

<template>
    <section class="showroom-content transaction-detail-page w-full max-w-full min-w-0 overflow-x-hidden space-y-4 font-sans">
        <div v-if="isLoading" class="spa-loading" role="status">
            <span class="spa-spinner" aria-hidden="true"></span>
            Memuat detail kendaraan...
        </div>
        <div v-else-if="errorMessage" class="error-panel" role="alert">
            {{ errorMessage }}
        </div>
        <template v-else-if="transaction">
            <header class="page-heading flex w-full min-w-0 flex-wrap items-center justify-between gap-3 !mb-0 !items-center">
                <div class="min-w-0 flex-1">
                    <p class="page-eyebrow text-[11px]! text-slate-400!">Inventaris kendaraan</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl! font-bold text-slate-900 sm:text-2xl!">Detail Kendaraan</h1>
                        <span
                            class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                            :class="transaction.sell_date
                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                        >
                            {{ vehicleStatus }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ transaction.plate_number }} · {{ transaction.brand }} {{ transaction.model }}
                    </p>
                </div>
                <div class="flex w-full flex-wrap gap-3 sm:w-auto sm:shrink-0">
                    <RouterLink
                        v-if="canEdit"
                        class="btn btn-accent"
                        :to="`/transactions/${transaction.id}/edit`"
                    >
                        Edit data
                    </RouterLink>
                    <RouterLink class="btn btn-secondary" to="/dashboard">
                        ← Kembali
                    </RouterLink>
                </div>
            </header>

            <div class="grid w-full max-w-full min-w-0 grid-cols-1 items-start gap-4 lg:grid-cols-12">
                <div class="w-full max-w-full min-w-0 space-y-4 lg:col-span-7">
                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="vehicle-specifications-title">
                        <header class="mb-4">
                            <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Identitas unit</p>
                            <h2 id="vehicle-specifications-title" class="mt-1 text-base! font-semibold text-slate-900">
                                Spesifikasi Kendaraan
                            </h2>
                        </header>
                        <dl class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                            <div v-for="[label, key] in specifications" :key="key" class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">{{ label }}</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">
                                    {{ displayValue(key, transaction[key]) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="financial-breakdown-title">
                        <header class="mb-4">
                            <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Ringkasan keuangan</p>
                            <h2 id="financial-breakdown-title" class="mt-1 text-base! font-semibold text-slate-900">
                                {{ transaction.can_view_sensitive_data
                                    ? 'Modal, Pengeluaran, Pembayaran & Laba'
                                    : 'Pengeluaran Unit & Pembayaran' }}
                            </h2>
                        </header>

                        <div class="grid min-w-0 gap-3">
                            <section class="w-full max-w-full min-w-0 rounded-xl bg-slate-50 p-3.5" aria-labelledby="capital-title">
                                <h3 id="capital-title" class="mb-2 text-base! font-semibold text-slate-900">Modal &amp; Pengeluaran Unit</h3>
                                <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                                    <div v-if="transaction.can_view_sensitive_data" class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">Modal Beli</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction.purchase_price ?? transaction.buy_price) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">Operasional Pembelian</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction.purchase_operation ?? transaction.purchase_operational) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">Biaya Lain</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction.other_costs) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">Pajak</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction.tax_amount) }}</dd>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">Total Biaya Perbaikan</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction.total_repair_cost) }}</dd>
                                    </div>
                                </dl>
                                <div
                                    v-for="(repair, index) in transaction.repairs ?? transaction.additional_repairs ?? []"
                                    :key="`repair-${index}`"
                                    class="flex min-w-0 items-center justify-between gap-3 border-t border-slate-200/80 py-1.5 text-sm"
                                >
                                    <span class="min-w-0 text-slate-600">
                                        Perbaikan: {{ repair.name || `Rincian ${index + 1}` }}
                                        <small v-if="repair.date" class="block text-xs text-slate-500">{{ displayValue('buy_date', repair.date) }}</small>
                                    </span>
                                    <span class="shrink-0 text-right font-medium tabular-nums text-slate-900">{{ currency(repair.cost) }}</span>
                                </div>
                                <div
                                    v-for="(tax, index) in transaction.tax_items ?? []"
                                    :key="`tax-${index}`"
                                    class="flex min-w-0 items-center justify-between gap-3 border-t border-slate-200/80 py-1.5 text-sm"
                                >
                                    <span class="min-w-0 text-slate-600">
                                        {{ tax.name || `Pajak ${index + 1}` }}
                                        <small v-if="tax.date" class="block text-xs text-slate-500">{{ displayValue('buy_date', tax.date) }}</small>
                                    </span>
                                    <span class="shrink-0 text-right font-medium tabular-nums text-slate-900">{{ currency(tax.cost) }}</span>
                                </div>
                                <div
                                    v-for="(expense, index) in transaction.intertine_expenses ?? []"
                                    :key="`expense-${index}`"
                                    class="flex min-w-0 items-center justify-between gap-3 border-t border-slate-200/80 py-1.5 text-sm"
                                >
                                    <span class="min-w-0 text-slate-600">{{ expense.name || `Biaya ${index + 1}` }}</span>
                                    <span class="shrink-0 text-right font-medium tabular-nums text-slate-900">{{ currency(expense.cost ?? expense.amount) }}</span>
                                </div>
                                <div v-if="transaction.can_view_sensitive_data" class="mt-2 flex w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <span class="text-sm font-semibold text-slate-800">Total Modal</span>
                                    <span class="text-right text-sm font-bold tabular-nums text-slate-900">{{ currency(transaction.total_cost ?? transaction.total_capital) }}</span>
                                </div>
                            </section>

                            <section class="w-full max-w-full min-w-0 rounded-xl bg-slate-50 p-3.5" aria-labelledby="payment-title">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <h3 id="payment-title" class="text-base! font-semibold text-slate-900">Harga Jual &amp; Pembayaran</h3>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="paymentStatus() === 'Lunas'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : paymentStatus() === 'Belum terjual'
                                                ? 'bg-slate-100 text-slate-700'
                                                : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ paymentStatus() }}
                                    </span>
                                </div>
                                <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                                    <div v-for="[label, key] in paymentRows" :key="key" class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">{{ label }}</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction[key]) }}</dd>
                                    </div>
                                </dl>
                                <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-1 border-t border-slate-200/80 pt-2 text-sm sm:grid-cols-2">
                                    <div v-for="[label, key] in paymentDateRows" :key="key" class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">{{ label }}</dt>
                                        <dd class="text-right font-medium text-slate-900">{{ displayValue(key, transaction[key]) }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-2 flex w-full min-w-0 items-center justify-between gap-3 border-t border-slate-200 pt-2">
                                    <span class="text-sm font-semibold text-slate-700">Total Dibayar</span>
                                    <span class="text-right text-sm font-bold tabular-nums text-slate-900">{{ currency(totalPaid) }}</span>
                                </div>
                                <div class="mt-2 flex w-full min-w-0 items-center justify-between gap-3 border-t border-slate-200 pt-2">
                                    <span class="text-sm font-semibold text-slate-700">Sisa Pembayaran</span>
                                    <span class="text-right text-sm font-bold tabular-nums text-slate-900">{{ currency(transaction.remaining_payment) }}</span>
                                </div>
                            </section>

                            <section v-if="transaction.can_view_sensitive_data" class="w-full max-w-full min-w-0 rounded-xl bg-slate-50 p-3.5" aria-labelledby="profit-title">
                                <h3 id="profit-title" class="mb-2 text-base! font-semibold text-slate-900">Kalkulasi Laba</h3>
                                <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                                    <div v-for="[label, key] in profitRows" :key="key" class="flex min-w-0 items-center justify-between gap-3 py-1.5">
                                        <dt class="text-slate-600">{{ label }}</dt>
                                        <dd class="text-right font-medium tabular-nums text-slate-900">{{ currency(transaction[key]) }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-2 flex w-full min-w-0 items-center justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2">
                                    <span class="text-sm font-semibold text-emerald-900">Laba Bersih</span>
                                    <span class="text-right text-sm font-bold tabular-nums text-emerald-800">{{ currency(transaction.net_profit) }}</span>
                                </div>
                            </section>
                        </div>
                        <p v-if="!transaction.can_view_sensitive_data" class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                            Modal beli, total modal, dan margin laba merupakan informasi internal showroom.
                        </p>
                    </section>
                </div>

                <aside class="w-full max-w-full min-w-0 space-y-4 lg:col-span-5">
                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="vehicle-gallery-title">
                        <header class="mb-3">
                            <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Dokumentasi unit</p>
                            <h2 id="vehicle-gallery-title" class="mt-1 text-base! font-semibold text-slate-900">Foto Kendaraan</h2>
                        </header>
                        <div class="h-52 w-full overflow-hidden rounded-xl bg-slate-100 sm:h-60">
                            <img
                                v-if="activePhoto && !photoUnavailable(activePhoto.url)"
                                class="h-full w-full object-contain"
                                :src="resolvePhotoUrl(activePhoto.url)"
                                :alt="`Foto ${transaction.brand} ${transaction.model}`"
                                @error="markPhotoUnavailable(activePhoto.url)"
                            >
                            <div v-else class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-sm text-slate-500">
                                <span class="text-3xl" aria-hidden="true">🚘</span>
                                <span>{{ activePhoto ? 'Foto tidak tersedia' : 'Belum ada foto kendaraan' }}</span>
                            </div>
                        </div>
                        <div v-if="photos.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Pilih foto kendaraan">
                            <button
                                v-for="(photo, index) in photos"
                                :key="photo.id ?? photo.url"
                                type="button"
                                class="h-16 w-24 shrink-0 overflow-hidden rounded-lg border-2 bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                                :class="activePhotoIndex === index ? 'border-emerald-600' : 'border-slate-200'"
                                :aria-label="`Tampilkan foto ${index + 1}`"
                                :aria-pressed="activePhotoIndex === index"
                                @click="selectPhoto(index)"
                            >
                                <img
                                    v-if="!photoUnavailable(photo.url)"
                                    class="h-full w-full object-cover"
                                    :src="resolvePhotoUrl(photo.url)"
                                    alt=""
                                    @error="markPhotoUnavailable(photo.url)"
                                >
                                <span v-else class="flex h-full items-center justify-center px-1 text-center text-xs text-slate-500">
                                    Foto tidak tersedia
                                </span>
                            </button>
                        </div>
                    </section>

                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="buyer-admin-title">
                        <header class="mb-3">
                            <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Informasi pelengkap</p>
                            <h2 id="buyer-admin-title" class="mt-1 text-base! font-semibold text-slate-900">Informasi Pembeli &amp; Penjualan</h2>
                        </header>
                        <dl class="grid min-w-0 gap-x-4 gap-y-3 sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">Nama Pembeli</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.sale_note?.nama_konsumen || 'Belum ada data penjualan' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">NIK Pembeli</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.sale_note?.buyer_nik || '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">Telepon Pembeli</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.sale_note?.buyer_phone || '—' }}</dd>
                            </div>
                            <div class="min-w-0 sm:col-span-2">
                                <dt class="text-xs font-medium text-slate-500">Alamat Pembeli</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.sale_note?.buyer_address || '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="seller-details-title">
                        <header class="mb-3">
                            <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Asal kendaraan</p>
                            <h2 id="seller-details-title" class="mt-1 text-base! font-semibold text-slate-900">Data Penjual &amp; Broker</h2>
                        </header>
                        <dl class="grid min-w-0 gap-x-4 gap-y-3 sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">Nama Penjual</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.seller_name || '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">NIK Penjual</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.seller_nik || '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">No. HP Penjual</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.seller_phone || '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs font-medium text-slate-500">Nama Broker</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.broker_name || '—' }}</dd>
                            </div>
                            <div class="min-w-0 sm:col-span-2">
                                <dt class="text-xs font-medium text-slate-500">Alamat Penjual</dt>
                                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ transaction.seller_address || '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="w-full max-w-full min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="leasing-title">
                        <header class="mb-3 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <p class="text-[11px]! font-semibold uppercase tracking-wider text-slate-400!">Simulasi pembiayaan</p>
                                <h2 id="leasing-title" class="mt-1 text-base! font-semibold text-slate-900">Harga Leasing</h2>
                            </div>
                            <span class="text-xs text-slate-500">Nilai dalam Rupiah</span>
                        </header>
                        <div v-if="leasingOptions.length" class="w-full max-w-full min-w-0 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full min-w-[720px] border-collapse text-xs whitespace-nowrap">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left font-semibold">Leasing</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">Cair</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">Harga Jual</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">DP</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">3 Thn</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">4 Thn</th>
                                        <th class="px-2 py-1.5 text-right font-semibold">5 Thn</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(option, index) in leasingOptions" :key="`${option.name}-${index}`">
                                        <th class="px-2 py-1.5 text-left font-medium text-slate-800">{{ option.name }}</th>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.disbursement) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.selling_price) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.dp) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.installment_3yr) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.installment_4yr) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-700">{{ currency(option.installment_5yr) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-5 text-center text-sm text-slate-500">
                            Belum ada detail harga leasing untuk kendaraan ini.
                        </p>
                    </section>
                </aside>
            </div>
        </template>
    </section>
</template>
