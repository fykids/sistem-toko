<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiRequest, resolvePhotoUrl } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const successMessage = ref('');
const form = reactive({});
const payment = reactive({ amount: '', payment_method: '', payment_date: new Date().toISOString().slice(0, 10), notes: '' });
const rows = computed(() => payload.value?.data ?? []);
const pagination = computed(() => payload.value?.meta ?? null);
const currentStatus = computed(() => route.query.status ?? 'all');
const isCreate = computed(() => route.name === 'jual-create');
const isEdit = computed(() => route.name === 'jual-edit');
const isPayment = computed(() => route.name === 'jual-payment');
const isDetail = computed(() => route.name === 'jual-show');
const pageMode = computed(() => isCreate.value ? 'create' : isEdit.value ? 'edit' : isPayment.value ? 'payment' : isDetail.value ? 'detail' : 'index');
const statusLabels = { paid: 'Lunas', leasing: 'Proses Leasing', unpaid: 'Belum Lunas' };
const paymentLabels = { cash: 'Pelunasan Tunai', leasing: 'Pencairan Leasing', bank_transfer: 'Transfer Bank' };
const saleVehicle = computed(() => payload.value?.car_transaction ?? null);
const vehiclePhotoUrl = computed(() => {
    const photos = saleVehicle.value?.vehicle_photos ?? [];
    const primaryPhoto = photos.find((photo) => photo.is_primary || photo.is_cover);

    return resolvePhotoUrl(saleVehicle.value?.vehicle_photo_url)
        ?? resolvePhotoUrl(primaryPhoto?.url)
        ?? resolvePhotoUrl(photos[0]?.url);
});
const detailPaymentStatus = computed(() => payload.value?.payment_status === 'paid' ? 'Lunas' : 'Belum Lunas');
const consumerFields = computed(() => [
    ['Nama konsumen', payload.value?.nama_konsumen || '—'],
    ['NIK', payload.value?.buyer_nik || '—'],
    ['No. HP', payload.value?.buyer_phone || '—'],
    ['Alamat', payload.value?.buyer_address || '—'],
]);
const detailPaymentFields = computed(() => [
    ['Harga jual', currency(saleVehicle.value?.selling_price)],
    ['Tanggal jual', formatDate(saleVehicle.value?.sell_date)],
    ['DP', currency(saleVehicle.value?.down_payment)],
    ['Pelunasan diterima', currency(saleVehicle.value?.settlement_amount)],
    ['Pencairan leasing', currency(saleVehicle.value?.leasing_disbursement)],
    ['Total dibayar', currency(payload.value?.total_paid)],
    ['Sisa pembayaran', currency(saleVehicle.value?.remaining_payment)],
    ['Tanggal rencana pembayaran', formatDate(saleVehicle.value?.planned_payment_date)],
]);
const saleExpenseRows = computed(() => {
    const expenses = saleVehicle.value?.intertine_expenses;
    const displayedExpenseNames = new Set(['KR Jual', 'OP Survei', 'Fee CMO']);

    return Array.isArray(expenses)
        ? expenses.filter((expense) => displayedExpenseNames.has(expense.name))
        : [];
});
const detailVehicleFields = [
    ['Plat Nomor', 'plate_number'],
    ['Merek', 'brand'],
    ['Model', 'model'],
    ['Tahun', 'year'],
    ['Transmisi', 'transmission'],
    ['Warna', 'color'],
    ['Kilometer (Km)', 'mileage_km'],
    ['Nomor Rangka', 'chassis_number'],
    ['Nomor Mesin', 'engine_number'],
    ['Atas nama STNK', 'owner_name'],
    ['Penanggung jawab', 'manager_name'],
    ['Tanggal beli', 'buy_date'],
];
const selectedVehicle = computed(() => {
    if (isEdit.value) return payload.value?.car_transaction ?? null;

    return payload.value?.vehicles?.find(
        (vehicle) => String(vehicle.id) === String(form.car_transaction_id),
    ) ?? null;
});
const activeFormVehiclePhotoId = ref(null);
const isFormVehiclePhotoUnavailable = ref(false);
const formVehiclePhotos = computed(() => {
    const photos = Array.isArray(selectedVehicle.value?.vehicle_photos)
        ? selectedVehicle.value.vehicle_photos
        : [];

    return photos
        .map((photo) => ({
            ...photo,
            resolvedUrl: resolvePhotoUrl(photo.url) ?? resolvePhotoUrl(photo.path),
        }))
        .filter((photo) => photo.resolvedUrl);
});
const activeFormVehiclePhoto = computed(() => {
    const selectedPhoto = formVehiclePhotos.value.find(
        (photo) => String(photo.id) === String(activeFormVehiclePhotoId.value),
    );
    const coverPhoto = formVehiclePhotos.value.find(
        (photo) => photo.is_primary || photo.is_cover,
    ) ?? formVehiclePhotos.value.find(
        (photo) => photo.resolvedUrl === resolvePhotoUrl(selectedVehicle.value?.vehicle_photo_url),
    );

    return selectedPhoto ?? coverPhoto ?? formVehiclePhotos.value[0] ?? null;
});
const formVehiclePhotoUrl = computed(() => (
    activeFormVehiclePhoto.value?.resolvedUrl
    ?? resolvePhotoUrl(selectedVehicle.value?.vehicle_photo_url)
));
const canViewSensitiveVehicleData = computed(() => Boolean(
    payload.value?.can_view_sensitive_data
    ?? selectedVehicle.value?.can_view_sensitive_data,
));
watch(
    () => selectedVehicle.value?.id,
    () => {
        activeFormVehiclePhotoId.value = null;
        isFormVehiclePhotoUnavailable.value = false;
    },
);
const remainingPayment = computed(() => Math.max(
    Number(form.selling_price || 0)
    - Number(form.down_payment || 0)
    - Number(form.settlement_amount || 0)
    - Number(form.leasing_disbursement || 0),
    0,
));
const totalPaymentReceived = computed(() => (
    Number(form.down_payment || 0)
    + Number(form.settlement_amount || 0)
    + Number(form.leasing_disbursement || 0)
));
const isSalePaid = computed(() => (
    Number(form.selling_price || 0) > 0 && remainingPayment.value === 0
));
const estimatedNetProfit = computed(() => {
    if (!canViewSensitiveVehicleData.value || !selectedVehicle.value) return null;

    const totalCapital = Number(
        selectedVehicle.value.total_cost ?? selectedVehicle.value.total_capital,
    );
    if (!Number.isFinite(totalCapital)) return null;

    const grossProfit = Number(form.selling_price || 0) - totalCapital;
    const showroomFee = Math.round(grossProfit * 0.1 * 100) / 100;
    const otherExpenses = form.intertine_expenses.reduce(
        (total, expense) => total + Number(expense.cost || 0),
        0,
    );

    return grossProfit - showroomFee - otherExpenses;
});

function currency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
}

function formatDate(value) {
    if (!value) return '—';

    const date = new Date(value.length === 10 ? `${value}T00:00:00Z` : value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

function displayVehicleValue(key, value) {
    if (value === null || value === undefined || value === '') return '—';
    if (key === 'buy_date') return formatDate(value);
    if (key === 'mileage_km') return `${Number(value).toLocaleString('id-ID')} km`;
    if (key === 'purchase_price' || key === 'total_cost') return currency(value);

    return value;
}

const isVehiclePhotoUnavailable = ref(false);

function markVehiclePhotoUnavailable() {
    isVehiclePhotoUnavailable.value = true;
}

function emptySellForm() {
    return {
        car_transaction_id: '',
        nama_konsumen: '',
        buyer_nik: '',
        buyer_phone: '',
        buyer_address: '',
        selling_price: 0,
        down_payment: 0,
        down_payment_date: '',
        settlement_amount: 0,
        settlement_date: '',
        leasing_disbursement: 0,
        leasing_disbursement_date: '',
        sell_date: new Date().toISOString().slice(0, 10),
        planned_payment_date: '',
        intertine_expenses: [
            { name: 'KR Jual', cost: 0 },
            { name: 'OP Survei', cost: 0 },
            { name: 'Fee CMO', cost: 0 },
        ],
    };
}

watch(
    () => route.fullPath,
    async (_path, _previous, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        isLoading.value = true;
        errorMessage.value = '';
        fieldErrors.value = {};
        successMessage.value = '';
        isVehiclePhotoUnavailable.value = false;
        try {
            if (pageMode.value === 'index') {
                successMessage.value = sessionStorage.getItem('jualSuccessMessage') ?? '';
                sessionStorage.removeItem('jualSuccessMessage');
                const query = new URLSearchParams(route.query);
                payload.value = await apiRequest(`/api/spa/jual?${query}`, { signal: controller.signal });
            } else if (pageMode.value === 'create') {
                const query = new URLSearchParams();
                if (route.query.vehicle) {
                    query.set('vehicle', route.query.vehicle);
                }
                const data = await apiRequest(`/api/spa/jual/create?${query}`, { signal: controller.signal });
                payload.value = data.data;
                Object.assign(form, emptySellForm());
                if (data.data.selected_vehicle) {
                    Object.assign(form, {
                        car_transaction_id: data.data.selected_vehicle.id,
                        selling_price: data.data.selected_vehicle.selling_price ?? 0,
                        down_payment: data.data.selected_vehicle.down_payment ?? 0,
                        down_payment_date: data.data.selected_vehicle.down_payment_date ?? '',
                        settlement_amount: data.data.selected_vehicle.settlement_amount ?? 0,
                        settlement_date: data.data.selected_vehicle.settlement_date ?? '',
                        leasing_disbursement: data.data.selected_vehicle.leasing_disbursement ?? 0,
                        leasing_disbursement_date: data.data.selected_vehicle.leasing_disbursement_date ?? '',
                        sell_date: data.data.selected_vehicle.sell_date ?? emptySellForm().sell_date,
                        planned_payment_date: data.data.selected_vehicle.planned_payment_date ?? '',
                    });
                }
            } else {
                const response = await apiRequest(`/api/spa/jual/${route.params.buyer}`, { signal: controller.signal });
                payload.value = response.data;
                if (pageMode.value === 'edit') {
                    Object.assign(form, {
                        ...emptySellForm(),
                        nama_konsumen: response.data.nama_konsumen,
                        buyer_nik: response.data.buyer_nik ?? '',
                        buyer_phone: response.data.buyer_phone ?? '',
                        buyer_address: response.data.buyer_address ?? '',
                        selling_price: response.data.car_transaction.selling_price,
                        down_payment: response.data.car_transaction.down_payment,
                        down_payment_date: response.data.car_transaction.down_payment_date ?? '',
                        settlement_amount: response.data.car_transaction.settlement_amount,
                        settlement_date: response.data.car_transaction.settlement_date ?? '',
                        leasing_disbursement: response.data.car_transaction.leasing_disbursement,
                        leasing_disbursement_date: response.data.car_transaction.leasing_disbursement_date ?? '',
                        sell_date: response.data.car_transaction.sell_date,
                        planned_payment_date: response.data.car_transaction.planned_payment_date ?? '',
                    });
                }
            }
        } catch (error) {
            if (error.name !== 'AbortError') errorMessage.value = error.message;
        } finally {
            if (!controller.signal.aborted) isLoading.value = false;
        }
    },
    { immediate: true },
);

watch(
    () => form.car_transaction_id,
    (vehicleId) => {
        if (!isCreate.value || !payload.value?.vehicles) return;

        const vehicle = payload.value.vehicles.find((item) => String(item.id) === String(vehicleId));
        if (!vehicle) return;

        Object.assign(form, {
            selling_price: vehicle.selling_price ?? 0,
            down_payment: vehicle.down_payment ?? 0,
            down_payment_date: vehicle.down_payment_date ?? '',
            settlement_amount: vehicle.settlement_amount ?? 0,
            settlement_date: vehicle.settlement_date ?? '',
            leasing_disbursement: vehicle.leasing_disbursement ?? 0,
            leasing_disbursement_date: vehicle.leasing_disbursement_date ?? '',
            sell_date: vehicle.sell_date ?? form.sell_date,
            planned_payment_date: vehicle.planned_payment_date ?? '',
        });
    },
);

function setStatus(status) {
    router.push({ query: status === 'all' ? {} : { status } });
}

function goToPage(page) {
    router.push({ query: { ...route.query, page: String(page) } });
}

async function submitSell() {
    isSaving.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};
    const body = { ...form };
    if (isEdit.value) delete body.car_transaction_id;
    try {
        const response = await apiRequest(
            isEdit.value ? `/api/spa/jual/${route.params.buyer}` : '/api/spa/jual',
            { method: isEdit.value ? 'PUT' : 'POST', body },
        );
        sessionStorage.setItem(
            'jualSuccessMessage',
            response.message || (isEdit.value ? 'Penjualan berhasil diperbarui' : 'Penjualan berhasil disimpan'),
        );
        await router.push('/jual');
    } catch (error) {
        console.error('Gagal menyimpan penjualan:', error);
        errorMessage.value = error?.response?.data?.message
            || error?.message
            || 'Penjualan tidak dapat disimpan saat ini.';
        fieldErrors.value = error?.errors ?? {};
    } finally {
        isSaving.value = false;
    }
}

async function submitPayment() {
    isSaving.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};
    try {
        await apiRequest(`/api/spa/jual/${route.params.buyer}/payments`, {
            method: 'POST',
            body: payment,
        });
        await router.push(`/jual/${route.params.buyer}`);
    } catch (error) {
        errorMessage.value = error.message;
        fieldErrors.value = error.errors ?? {};
    } finally {
        isSaving.value = false;
    }
}

async function deleteNote(note) {
    if (!window.confirm(`Hapus transaksi ${note.nama_konsumen}?`)) return;
    isSaving.value = true;
    try {
        await apiRequest(`/api/spa/jual/${note.id}`, { method: 'DELETE' });
        await router.push('/jual');
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isSaving.value = false;
    }
}

const vehicleFields = [
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
    ['Penanggung Jawab', 'manager_name'],
    ['Tanggal Beli', 'buy_date'],
    ['Nama Penjual Asal', 'seller_name'],
    ['Modal Beli Unit', 'purchase_price', true],
    ['Total Modal', 'total_cost', true],
];
const visibleVehicleFields = computed(() => vehicleFields.filter(
    ([, , masterOnly]) => !masterOnly || canViewSensitiveVehicleData.value,
));
const consumerInputFields = [
    { key: 'nama_konsumen', label: 'Nama konsumen', type: 'text', maxlength: 255, required: true },
    { key: 'buyer_nik', label: 'NIK', type: 'text', maxlength: 16, inputmode: 'numeric' },
    { key: 'buyer_phone', label: 'No. HP', type: 'tel', maxlength: 30 },
    { key: 'buyer_address', label: 'Alamat', type: 'textarea', maxlength: 2000, rows: 2 },
];
const paymentInputFields = [
    { key: 'selling_price', label: 'Harga jual (Rp)', type: 'number', required: true },
    { key: 'sell_date', label: 'Tanggal transaksi', type: 'date', required: true },
    { key: 'down_payment', label: 'DP (Rp)', type: 'number', required: true },
    { key: 'down_payment_date', label: 'Tanggal pembayaran DP', type: 'date' },
    { key: 'settlement_amount', label: 'Pelunasan (Rp)', type: 'number', required: true },
    { key: 'settlement_date', label: 'Tanggal pelunasan', type: 'date' },
    { key: 'leasing_disbursement', label: 'Pencairan leasing (Rp)', type: 'number', required: true },
    { key: 'leasing_disbursement_date', label: 'Tanggal pencairan leasing', type: 'date' },
];
const paymentInputPairs = [
    [paymentInputFields[0], paymentInputFields[1]],
    [paymentInputFields[2], paymentInputFields[3]],
    [paymentInputFields[4], paymentInputFields[5]],
    [paymentInputFields[6], paymentInputFields[7]],
];
</script>

<template>
    <section class="showroom-content jual-page w-full max-w-full min-w-0 overflow-x-hidden">
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat data penjualan...</div>
        <div v-else-if="errorMessage && !payload" class="error-panel" role="alert">{{ errorMessage }}</div>
        <template v-else-if="pageMode === 'index'">
            <p v-if="successMessage" class="spa-success" role="status">{{ successMessage }}</p>
            <div v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</div>
            <section class="panel showroom-inventory jual-table-card" aria-label="Daftar transaksi Jual">
                <header class="jual-table-heading">
                    <h1>Laporan Penjualan</h1>
                    <RouterLink to="/jual/create" class="showroom-primary-action jual-create-action"><span aria-hidden="true">+</span>Tambah</RouterLink>
                </header>
                <nav class="jual-page-actions jual-status-filters" aria-label="Filter status pembayaran">
                    <button v-for="(label, status) in { all: 'Semua', unpaid: 'Belum Lunas', leasing: 'Proses Leasing', paid: 'Lunas' }" :key="status" type="button" class="btn" :class="currentStatus === status ? 'btn-accent' : 'btn-secondary'" :aria-current="currentStatus === status ? 'page' : undefined" :disabled="isSaving || isLoading" @click="setStatus(status)">{{ label }}</button>
                </nav>
                <div class="table-scroll"><table class="data-table jual-table"><thead><tr><th>Kendaraan</th><th>Konsumen</th><th>Tanggal</th><th class="numeric">Harga deal</th><th>Status pembayaran</th><th v-if="payload.can_view_sensitive_data" class="numeric">Modal beli</th><th v-if="payload.can_view_sensitive_data" class="numeric">Total modal</th><th v-if="payload.can_view_sensitive_data" class="numeric">Laba deal</th><th>Aksi</th></tr></thead>
                    <tbody><tr v-for="note in rows" :key="note.id"><td data-label="Kendaraan"><span class="cell-primary">{{ note.car_transaction.plate_number }}</span><span class="cell-secondary">{{ note.car_transaction.brand }} {{ note.car_transaction.model }} · {{ note.car_transaction.year }}</span></td><td data-label="Konsumen">{{ note.nama_konsumen }}</td><td data-label="Tanggal">{{ note.car_transaction.sell_date }}</td><td class="numeric" data-label="Harga deal">{{ currency(note.car_transaction.selling_price) }}</td><td data-label="Status pembayaran"><span class="status-badge" :class="{ pending: note.payment_status !== 'paid' }">{{ statusLabels[note.payment_status] }}</span></td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.buy_price) }}</td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.total_capital) }}</td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.gross_profit + note.car_transaction.leasing_ripan + note.car_transaction.insurance_ripan + note.car_transaction.booster_ripan) }}</td><td data-label="Aksi"><div class="jual-page-actions"><RouterLink class="btn btn-secondary" :to="`/jual/${note.id}`">Detail</RouterLink><RouterLink v-if="Number(note.car_transaction.remaining_payment) > 0" class="btn btn-accent" :to="`/jual/${note.id}/pelunasan`">Catat pelunasan</RouterLink><RouterLink class="btn btn-secondary" :to="`/jual/${note.id}/edit`">Edit</RouterLink><button class="btn btn-secondary" type="button" :disabled="isSaving" @click="deleteNote(note)">Hapus</button></div></td></tr>
                        <tr v-if="!rows.length"><td class="table-empty" :colspan="payload.can_view_sensitive_data ? 9 : 6"><strong>Belum ada transaksi Jual</strong>Catat penjualan kendaraan melalui menu ini untuk memulai.</td></tr>
                    </tbody></table></div>
                <div v-if="pagination?.last_page > 1" class="table-foot"><button type="button" class="btn btn-secondary" :disabled="pagination.current_page <= 1 || isLoading" @click="goToPage(pagination.current_page - 1)">Sebelumnya</button><span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span><button type="button" class="btn btn-secondary" :disabled="pagination.current_page >= pagination.last_page || isLoading" @click="goToPage(pagination.current_page + 1)">Berikutnya</button></div>
            </section>
        </template>
        <template v-else-if="pageMode === 'create' || pageMode === 'edit'">
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penjualan</p>
                    <h1 class="text-xl font-bold text-slate-900">{{ isEdit ? 'Edit Penjualan' : 'Input Penjualan' }}</h1>
                </div>
                <RouterLink to="/jual" class="btn btn-secondary shrink-0">&larr; Kembali ke daftar</RouterLink>
            </div>
            <form class="jual-form w-full min-w-0" :aria-busy="isSaving" @submit.prevent="submitSell">
                <fieldset class="min-w-0 border-0 p-0" :disabled="isSaving">
                    <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-12">
                        <div class="min-w-0 space-y-3 xl:col-span-8">
                            <section class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="jual-vehicle-title">
                                <header>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Section 1</p>
                                    <h2 id="jual-vehicle-title" class="mt-1 text-base! font-semibold text-slate-900">Data Kendaraan</h2>
                                </header>
                                <div class="flex min-w-0 flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-4">
                                    <label for="jual-vehicle-select" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                        Pilih kendaraan
                                    </label>
                                    <div class="grid min-w-0 flex-1 gap-1">
                                        <select
                                            v-if="!isEdit"
                                            id="jual-vehicle-select"
                                            v-model="form.car_transaction_id"
                                            class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                            name="car_transaction_id"
                                            required
                                        >
                                            <option value="">Pilih kendaraan</option>
                                            <option v-for="vehicle in payload.vehicles" :key="vehicle.id" :value="vehicle.id">
                                                {{ vehicle.plate_number }} · {{ vehicle.brand }} {{ vehicle.model }}
                                            </option>
                                        </select>
                                        <input
                                            v-else
                                            id="jual-vehicle-select"
                                            class="w-full min-w-0 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            :value="payload.car_transaction?.plate_number"
                                            readonly
                                        >
                                        <small v-if="fieldErrors.car_transaction_id" class="form-help">{{ fieldErrors.car_transaction_id[0] }}</small>
                                    </div>
                                </div>
                                <div v-if="selectedVehicle" class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-[11rem_minmax(0,1fr)]">
                                    <div class="min-w-0">
                                        <div class="h-32 w-44 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                            <img
                                                v-if="formVehiclePhotoUrl && !isFormVehiclePhotoUnavailable"
                                                class="h-full w-full object-cover"
                                                :src="formVehiclePhotoUrl"
                                                :alt="`Foto ${selectedVehicle.brand ?? ''} ${selectedVehicle.model ?? ''}`"
                                                @error="isFormVehiclePhotoUnavailable = true"
                                            >
                                            <div v-else class="flex h-full items-center justify-center px-3 text-center text-xs text-slate-500">
                                                Foto kendaraan belum tersedia
                                            </div>
                                        </div>
                                        <div v-if="formVehiclePhotos.length > 1" class="mt-2 flex gap-1.5 overflow-x-auto">
                                            <button
                                                v-for="(photo, index) in formVehiclePhotos"
                                                :key="photo.id ?? photo.resolvedUrl"
                                                type="button"
                                                class="h-10 w-14 shrink-0 overflow-hidden rounded-md border bg-white"
                                                :class="String(activeFormVehiclePhoto?.id) === String(photo.id) ? 'border-emerald-600 ring-1 ring-emerald-600' : 'border-slate-200'"
                                                :aria-label="`Tampilkan foto kendaraan ${index + 1}`"
                                                :aria-pressed="String(activeFormVehiclePhoto?.id) === String(photo.id)"
                                                @click="activeFormVehiclePhotoId = photo.id; isFormVehiclePhotoUnavailable = false"
                                            >
                                                <img class="h-full w-full object-cover" :src="photo.resolvedUrl" alt="">
                                            </button>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <dl class="grid min-w-0 grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-2">
                                            <div v-for="[label, key] in visibleVehicleFields" :key="key" class="min-w-0">
                                                <dt class="text-[11px] text-slate-500">{{ label }}</dt>
                                                <dd class="break-words text-sm font-medium text-slate-900">
                                                    {{ displayVehicleValue(key, selectedVehicle[key]) }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <RouterLink
                                            :to="`/app/transactions/${selectedVehicle.id}`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-2 inline-flex text-xs font-medium text-emerald-700 hover:text-emerald-800"
                                        >
                                            Lihat detail lengkap unit ↗
                                        </RouterLink>
                                    </div>
                                </div>
                                <p v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center text-xs text-slate-500">
                                    Pilih kendaraan untuk melihat foto dan detail unit.
                                </p>
                                <p v-if="!payload.has_available_vehicles && !isEdit" class="form-help">
                                    Tidak ada kendaraan yang tersedia untuk dicatat pada Menu Jual.
                                </p>
                            </section>

                            <section class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="jual-consumer-title">
                                <header>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Section 2</p>
                                    <h2 id="jual-consumer-title" class="mt-1 text-base! font-semibold text-slate-900">Informasi Konsumen</h2>
                                </header>
                                <div class="space-y-2">
                                    <div
                                        v-for="field in consumerInputFields"
                                        :key="field.key"
                                        class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-3"
                                    >
                                        <label :for="`jual-${field.key}`" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                            {{ field.label }}
                                        </label>
                                        <div class="grid min-w-0 flex-1 gap-1">
                                            <textarea
                                                v-if="field.type === 'textarea'"
                                                :id="`jual-${field.key}`"
                                                v-model="form[field.key]"
                                                class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                                :name="field.key"
                                                :rows="field.rows"
                                                :maxlength="field.maxlength"
                                            ></textarea>
                                            <input
                                                v-else
                                                :id="`jual-${field.key}`"
                                                v-model="form[field.key]"
                                                class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                                :name="field.key"
                                                :type="field.type"
                                                :inputmode="field.inputmode"
                                                :maxlength="field.maxlength"
                                                :required="field.required"
                                            >
                                            <small v-if="fieldErrors[field.key]" class="form-help">{{ fieldErrors[field.key][0] }}</small>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="jual-payment-title">
                                <header>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Section 3</p>
                                    <h2 id="jual-payment-title" class="mt-1 text-base! font-semibold text-slate-900">Rincian Pembayaran</h2>
                                </header>
                                <div class="space-y-2.5">
                                    <div
                                        v-for="(pair, pairIndex) in paymentInputPairs"
                                        :key="pairIndex"
                                        class="grid min-w-0 grid-cols-1 gap-2.5 lg:grid-cols-2"
                                    >
                                        <div
                                            v-for="field in pair"
                                            :key="field.key"
                                            class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2"
                                        >
                                            <label :for="`jual-${field.key}`" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                                {{ field.label }}
                                            </label>
                                            <div class="grid min-w-0 flex-1 gap-1">
                                                <input
                                                    :id="`jual-${field.key}`"
                                                    v-model="form[field.key]"
                                                    class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                                    :name="field.key"
                                                    :type="field.type"
                                                    :min="field.type === 'number' ? 0 : undefined"
                                                    :step="field.type === 'number' ? '0.01' : undefined"
                                                    :required="field.required"
                                                >
                                                <small v-if="fieldErrors[field.key]" class="form-help">{{ fieldErrors[field.key][0] }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid min-w-0 grid-cols-1 gap-2.5 lg:grid-cols-2">
                                        <div class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                                            <label for="jual-remaining-payment" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                                Sisa pembayaran (Rp)
                                            </label>
                                            <output id="jual-remaining-payment" class="w-full min-w-0 flex-1 rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold tabular-nums text-slate-800" aria-live="polite">
                                                {{ currency(remainingPayment) }}
                                            </output>
                                        </div>
                                        <div class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                                            <label for="jual-planned-payment-date" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                                Tanggal rencana pembayaran
                                            </label>
                                            <div class="grid min-w-0 flex-1 gap-1">
                                                <input
                                                    id="jual-planned-payment-date"
                                                    v-model="form.planned_payment_date"
                                                    class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                                    name="planned_payment_date"
                                                    type="date"
                                                >
                                                <small v-if="fieldErrors.planned_payment_date" class="form-help">{{ fieldErrors.planned_payment_date[0] }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="jual-expenses-title">
                                <header>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Section 4</p>
                                    <h2 id="jual-expenses-title" class="mt-1 text-base! font-semibold text-slate-900">Biaya &amp; Komisi Penjualan</h2>
                                </header>
                                <div class="space-y-2">
                                    <div
                                        v-for="(expense, index) in form.intertine_expenses"
                                        :key="expense.name"
                                        class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-3"
                                    >
                                        <label :for="`jual-expense-${index}`" class="text-xs font-medium text-slate-600 sm:w-40 sm:shrink-0 sm:text-sm">
                                            {{ expense.name }}
                                        </label>
                                        <div class="grid min-w-0 flex-1 gap-1">
                                            <input type="hidden" :name="`intertine_expenses[${index}][name]`" :value="expense.name">
                                            <input
                                                :id="`jual-expense-${index}`"
                                                v-model="expense.cost"
                                                class="w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                                :name="`intertine_expenses[${index}][cost]`"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                            >
                                            <small v-if="fieldErrors[`intertine_expenses.${index}.cost`]" class="form-help">
                                                {{ fieldErrors[`intertine_expenses.${index}.cost`][0] }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <aside class="min-w-0 xl:col-span-4">
                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-4" aria-labelledby="jual-summary-title">
                                <h2 id="jual-summary-title" class="text-base font-semibold text-slate-900">Ringkasan Transaksi</h2>
                                <div v-if="selectedVehicle" class="flex min-w-0 items-center gap-3 rounded-xl bg-slate-50 p-3">
                                    <div class="h-16 w-24 shrink-0 overflow-hidden rounded-lg bg-white">
                                        <img
                                            v-if="formVehiclePhotoUrl && !isFormVehiclePhotoUnavailable"
                                            class="h-full w-full object-cover"
                                            :src="formVehiclePhotoUrl"
                                            :alt="`Foto ${selectedVehicle.brand ?? ''} ${selectedVehicle.model ?? ''}`"
                                        >
                                        <div v-else class="flex h-full items-center justify-center text-[10px] text-slate-400">Tanpa foto</div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ selectedVehicle.brand }} {{ selectedVehicle.model }} {{ selectedVehicle.year }}</p>
                                        <p class="text-xs text-slate-600">{{ selectedVehicle.plate_number }}</p>
                                        <span class="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            {{ selectedVehicle.vehicle_status === 'sold' ? 'Terjual' : 'Stok tersedia' }}
                                        </span>
                                    </div>
                                </div>
                                <p v-else class="rounded-xl bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                                    Pilih kendaraan untuk melihat ringkasan transaksi.
                                </p>
                                <dl class="space-y-2 border-t border-slate-100 pt-3 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Harga jual</dt>
                                        <dd class="font-semibold tabular-nums text-slate-900">{{ currency(form.selling_price) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Total dana masuk</dt>
                                        <dd class="font-semibold tabular-nums text-slate-900">{{ currency(totalPaymentReceived) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-slate-500">Sisa pembayaran</dt>
                                        <dd class="font-semibold tabular-nums text-slate-900">{{ currency(remainingPayment) }}</dd>
                                    </div>
                                    <div class="flex justify-end">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="isSalePaid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                        >
                                            {{ isSalePaid ? 'Lunas' : 'Belum Lunas' }}
                                        </span>
                                    </div>
                                    <div v-if="canViewSensitiveVehicleData" class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2">
                                        <dt class="text-slate-500">Estimasi laba bersih</dt>
                                        <dd
                                            class="font-semibold tabular-nums"
                                            :class="estimatedNetProfit === null || estimatedNetProfit >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                                        >
                                            {{ estimatedNetProfit === null ? '—' : currency(estimatedNetProfit) }}
                                        </dd>
                                    </div>
                                </dl>
                                <div class="space-y-2 border-t border-slate-100 pt-3">
                                    <button class="btn btn-accent w-full justify-center rounded-xl py-2.5" type="submit" :disabled="isSaving">
                                        {{ isSaving ? 'Menyimpan...' : isEdit ? 'Simpan perubahan' : 'Simpan penjualan' }}
                                    </button>
                                    <RouterLink to="/jual" class="btn btn-secondary w-full justify-center rounded-xl py-2.5">Batal</RouterLink>
                                </div>
                            </section>
                        </aside>
                    </div>
                </fieldset>
            </form>
        </template>
        <template v-else-if="pageMode === 'payment'">
            <section class="showroom-page-header jual-heading"><div><p class="showroom-page-eyebrow">Penjualan</p><h1>Catat Pelunasan</h1><p>{{ payload.car_transaction?.plate_number }} · {{ payload.car_transaction?.brand }} {{ payload.car_transaction?.model }}</p></div><RouterLink :to="`/jual/${payload.id}`" class="btn btn-secondary">Batal / Kembali</RouterLink></section>
            <section class="panel jual-detail-card"><h2>Ringkasan transaksi</h2><dl class="jual-detail-list"><div><dt>Nama pembeli</dt><dd>{{ payload.nama_konsumen }}</dd></div><div><dt>Harga jual deal</dt><dd>{{ currency(payload.car_transaction?.selling_price) }}</dd></div><div><dt>Total sudah dibayar</dt><dd>{{ currency(payload.total_paid) }}</dd></div><div><dt>Sisa pembayaran</dt><dd><strong>{{ currency(payload.car_transaction?.remaining_payment) }}</strong></dd></div></dl></section>
            <section class="panel jual-detail-card"><h2>Data pelunasan</h2><p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p><form @submit.prevent="submitPayment"><fieldset class="field-grid two-column" :disabled="isSaving">
                <label class="form-group">Nominal pelunasan (Rp)<input v-model="payment.amount" type="number" min="0.01" step="0.01" required><small v-if="fieldErrors.amount" class="form-help">{{ fieldErrors.amount[0] }}</small></label>
                <label class="form-group">Metode / jenis pelunasan<select v-model="payment.payment_method" required><option value="">Pilih metode pelunasan</option><option value="cash">Pelunasan Tunai</option><option value="leasing">Pencairan Leasing</option><option value="bank_transfer">Transfer Bank</option></select><small v-if="fieldErrors.payment_method" class="form-help">{{ fieldErrors.payment_method[0] }}</small></label>
                <label class="form-group">Tanggal pelunasan<input v-model="payment.payment_date" type="date" required><small v-if="fieldErrors.payment_date" class="form-help">{{ fieldErrors.payment_date[0] }}</small></label>
                <label class="form-group">Catatan / keterangan<textarea v-model="payment.notes" rows="3" maxlength="1000"></textarea></label>
                <div class="jual-page-actions"><RouterLink :to="`/jual/${payload.id}`" class="btn btn-secondary">Batal / Kembali</RouterLink><button class="btn btn-accent" type="submit" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan Pelunasan' }}</button></div>
            </fieldset></form></section>
        </template>
        <template v-else-if="pageMode === 'detail'">
            <section class="showroom-page-header jual-heading flex w-full max-w-full min-w-0 flex-wrap items-center justify-between gap-3 mb-0! min-h-0! p-4! sm:p-5!">
                <div class="min-w-0 flex-1">
                    <p class="showroom-page-eyebrow mb-1! text-xs! text-slate-400!">Detail Menu Jual</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl! font-bold text-slate-900! sm:text-2xl!">{{ payload.nama_konsumen }}</h1>
                        <span class="status-badge" :class="{ pending: payload.payment_status !== 'paid' }">
                            {{ detailPaymentStatus }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ saleVehicle?.plate_number }} · {{ saleVehicle?.brand }} {{ saleVehicle?.model }} · {{ formatDate(saleVehicle?.sell_date) }}
                    </p>
                </div>
                <div class="jual-page-actions flex w-full flex-wrap gap-2 sm:w-auto sm:shrink-0">
                    <RouterLink :to="`/jual/${payload.id}/edit`" class="btn btn-accent">Edit</RouterLink>
                    <button class="btn btn-secondary" type="button" :disabled="isSaving" @click="deleteNote(payload)">
                        Hapus
                    </button>
                    <RouterLink to="/jual" class="btn btn-secondary">Kembali ke Jual</RouterLink>
                </div>
            </section>
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <div class="mt-4 grid w-full min-w-0 grid-cols-1 gap-4 lg:grid-cols-12">
                <section
                    class="panel jual-detail-card w-full min-w-0 rounded-2xl border border-slate-200 bg-white p-4! shadow-sm sm:p-5! lg:col-span-5"
                    aria-labelledby="jual-vehicle-title"
                >
                    <header class="mb-4">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Unit terjual</p>
                        <h2 id="jual-vehicle-title" class="text-base! font-semibold text-slate-900">Detail Mobil</h2>
                    </header>
                    <div class="mb-4 h-44 w-full overflow-hidden rounded-xl bg-slate-100">
                        <img
                            v-if="vehiclePhotoUrl && !isVehiclePhotoUnavailable"
                            class="h-full w-full object-cover"
                            :src="vehiclePhotoUrl"
                            :alt="`Foto ${saleVehicle?.brand ?? ''} ${saleVehicle?.model ?? ''}`"
                            @error="markVehiclePhotoUnavailable"
                        >
                        <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-slate-500">
                            Foto kendaraan belum tersedia
                        </div>
                    </div>
                    <dl class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-2.5">
                        <div v-for="[label, key] in detailVehicleFields" :key="key" class="min-w-0">
                            <dt class="text-xs text-slate-500">{{ label }}</dt>
                            <dd class="mt-1 break-words text-sm font-medium text-slate-900">
                                {{ displayVehicleValue(key, saleVehicle?.[key]) }}
                            </dd>
                        </div>
                    </dl>
                    <RouterLink
                        v-if="saleVehicle?.id"
                        :to="`/transactions/${saleVehicle.id}`"
                        class="mt-4 inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-800"
                    >
                        Lihat detail unit →
                    </RouterLink>
                </section>

                <div class="grid min-w-0 grid-cols-1 gap-4 lg:col-span-7">
                    <section
                        class="panel jual-detail-card w-full min-w-0 rounded-2xl border border-slate-200 bg-white p-4! shadow-sm sm:p-5!"
                        aria-labelledby="jual-transaction-title"
                    >
                        <header class="mb-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Informasi konsumen &amp; pembayaran</p>
                            <h2 id="jual-transaction-title" class="text-base! font-semibold text-slate-900">Detail Transaksi</h2>
                        </header>
                        <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-2">
                            <section class="min-w-0" aria-labelledby="jual-consumer-title">
                                <h3 id="jual-consumer-title" class="mb-2 text-sm! font-semibold text-slate-700">Informasi konsumen</h3>
                                <dl class="grid min-w-0 grid-cols-2 gap-x-3 gap-y-3">
                                    <div v-for="[label, value] in consumerFields" :key="label" class="min-w-0">
                                        <dt class="text-xs text-slate-500">{{ label }}</dt>
                                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ value }}</dd>
                                    </div>
                                </dl>
                            </section>
                            <section class="min-w-0" aria-labelledby="jual-payment-title">
                                <h3 id="jual-payment-title" class="mb-2 text-sm! font-semibold text-slate-700">Pembayaran</h3>
                                <dl class="grid min-w-0 grid-cols-2 gap-x-3 gap-y-3">
                                    <div v-for="[label, value] in detailPaymentFields" :key="label" class="min-w-0">
                                        <dt class="text-xs text-slate-500">{{ label }}</dt>
                                        <dd class="mt-1 break-words text-sm font-medium tabular-nums text-slate-900">{{ value }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs text-slate-500">Status pembayaran</dt>
                                        <dd class="mt-1">
                                            <span class="status-badge" :class="{ pending: payload.payment_status !== 'paid' }">
                                                {{ detailPaymentStatus }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                        <section
                            v-if="saleExpenseRows.length"
                            class="mt-4 border-t border-slate-200 pt-3"
                            aria-labelledby="jual-expenses-title"
                        >
                            <h3 id="jual-expenses-title" class="mb-2 text-sm! font-semibold text-slate-700">Biaya &amp; komisi penjualan</h3>
                            <dl class="grid min-w-0 grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-3">
                                <div v-for="expense in saleExpenseRows" :key="expense.name" class="min-w-0">
                                    <dt class="text-xs text-slate-500">{{ expense.name }}</dt>
                                    <dd class="mt-1 text-sm font-medium tabular-nums text-slate-900">{{ currency(expense.cost) }}</dd>
                                </div>
                            </dl>
                        </section>
                    </section>

                    <section
                        class="panel jual-detail-card w-full min-w-0 rounded-2xl border border-slate-200 bg-white p-4! shadow-sm sm:p-5!"
                        aria-labelledby="jual-payments-title"
                    >
                        <header class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <h2 id="jual-payments-title" class="text-base! font-semibold text-slate-900">Riwayat Pelunasan</h2>
                            <RouterLink
                                v-if="Number(saleVehicle?.remaining_payment) > 0"
                                :to="`/jual/${payload.id}/pelunasan`"
                                class="btn btn-accent"
                            >
                                Tambah pembayaran
                            </RouterLink>
                        </header>
                        <div v-if="payload.payments?.length" class="table-scroll w-full min-w-0 overflow-x-auto">
                            <table class="data-table jual-table min-w-[560px]">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Metode</th>
                                        <th class="numeric">Nominal</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in payload.payments" :key="item.id">
                                        <td>{{ formatDate(item.payment_date) }}</td>
                                        <td>{{ paymentLabels[item.payment_method] }}</td>
                                        <td class="numeric">{{ currency(item.amount) }}</td>
                                        <td>{{ item.notes || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-sm text-slate-600">
                            Belum ada pembayaran pelunasan tambahan yang dicatat.
                        </p>
                    </section>
                </div>
            </div>
        </template>
    </section>
</template>
