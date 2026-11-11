<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const form = reactive({
    nama_konsumen: '',
    buyer_nik: '',
    buyer_phone: '',
    buyer_address: '',
    payment_type: 'selling_price',
    payment_amount: 0,
    sell_date: new Date().toISOString().slice(0, 10),
    intertine_expenses: [
        { name: 'KR Jual', cost: 0 },
        { name: 'OP Survei', cost: 0 },
        { name: 'Fee CMO', cost: 0 },
    ],
});
const rows = computed(() => payload.value?.data ?? []);
const pagination = computed(() => payload.value?.meta ?? null);
const isEdit = computed(() => route.name === 'sale-note-edit');
const isDetail = computed(() => route.name === 'sale-note-show');
const selectedVehicle = computed(() => payload.value?.car_transaction ?? null);
const remainingPayment = computed(() => {
    const amounts = {
        selling_price: Number(selectedVehicle.value?.selling_price ?? 0),
        down_payment: Number(selectedVehicle.value?.down_payment ?? 0),
        settlement_amount: Number(selectedVehicle.value?.settlement_amount ?? 0),
        leasing_disbursement: Number(selectedVehicle.value?.leasing_disbursement ?? 0),
    };
    const paymentFields = {
        selling_price: 'selling_price',
        down_payment: 'down_payment',
        settlement_amount: 'settlement_amount',
        leasing_disbursement: 'leasing_disbursement',
    };
    amounts[paymentFields[form.payment_type]] = Number(form.payment_amount || 0);

    return Math.max(
        amounts.selling_price
        - amounts.down_payment
        - amounts.settlement_amount
        - amounts.leasing_disbursement,
        0,
    );
});

function currency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;
}

function displayDate(value) {
    if (!value) return '—';

    const [year, month, day] = String(value).slice(0, 10).split('-');

    return `${day}-${month}-${year}`;
}

watch(
    () => route.fullPath,
    async (_path, _previous, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        isLoading.value = true;
        errorMessage.value = '';
        fieldErrors.value = {};
        try {
            if (route.name === 'sale-note-index') {
                payload.value = await apiRequest(`/api/spa/sale-notes?${new URLSearchParams(route.query)}`, { signal: controller.signal });
            } else {
                const response = await apiRequest(`/api/spa/sale-notes/${route.params.saleNote}`, { signal: controller.signal });
                payload.value = response.data;
                if (isEdit.value) {
                    form.nama_konsumen = response.data.nama_konsumen;
                    form.buyer_nik = response.data.buyer_nik ?? '';
                    form.buyer_phone = response.data.buyer_phone ?? '';
                    form.buyer_address = response.data.buyer_address ?? '';
                    form.payment_type = 'selling_price';
                    form.payment_amount = response.data.car_transaction?.selling_price ?? 0;
                    form.sell_date = response.data.car_transaction?.sell_date ?? '';
                    const expenses = response.data.car_transaction?.intertine_expenses ?? [];
                    form.intertine_expenses = ['KR Jual', 'OP Survei', 'Fee CMO'].map((name) => ({
                        name,
                        cost: expenses.find((expense) => expense.name?.toLowerCase() === name.toLowerCase())?.cost ?? 0,
                    }));
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

function goToPage(page) {
    router.push({ query: { ...route.query, page: String(page) } });
}

async function submitForm() {
    isSaving.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};
    const body = {
        nama_konsumen: form.nama_konsumen,
        buyer_nik: form.buyer_nik,
        buyer_phone: form.buyer_phone,
        buyer_address: form.buyer_address,
        payment_type: form.payment_type,
        payment_amount: form.payment_amount,
        sell_date: form.sell_date,
        intertine_expenses: form.intertine_expenses,
    };
    try {
        await apiRequest(`/api/spa/sale-notes/${route.params.saleNote}`, { method: 'PUT', body });
        await router.push(`/nota-penjualan/${route.params.saleNote}`);
    } catch (error) {
        errorMessage.value = error.message;
        fieldErrors.value = error.errors ?? {};
    } finally {
        isSaving.value = false;
    }
}

const vehicleFields = [
    ['plate_number', 'Nomor polisi'], ['manager_name', 'Penanggung jawab'], ['brand', 'Merek'],
    ['model', 'Model'], ['year', 'Tahun'], ['transmission', 'Transmisi'], ['color', 'Warna'],
    ['chassis_number', 'Nomor rangka'], ['engine_number', 'Nomor mesin'], ['mileage_km', 'Kilometer'],
    ['owner_name', 'Nama pemilik'], ['buy_date', 'Tanggal beli'], ['purchase_operational', 'OPS'],
    ['other_costs', 'KR beli'], ['total_repair_cost', 'Total biaya perbaikan'], ['setor_price', 'Harga setor'],
    ['opening_price', 'Harga buka'], ['selling_price', 'Harga jual'], ['sell_date', 'Tanggal jual'],
    ['down_payment', 'DP'], ['down_payment_date', 'Tanggal DP'], ['settlement_amount', 'Pelunasan'],
    ['settlement_date', 'Tanggal pelunasan'], ['leasing_disbursement', 'Pencairan leasing'],
    ['leasing_disbursement_date', 'Tanggal pencairan leasing'], ['remaining_payment', 'Sisa pembayaran'],
];
</script>

<template>
    <section class="showroom-content sale-note-page">
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat nota penjualan...</div>
        <div v-else-if="errorMessage && !payload" class="error-panel" role="alert">{{ errorMessage }}</div>
        <template v-else-if="route.name === 'sale-note-index'">
            <section class="showroom-page-header sale-note-heading" aria-labelledby="sale-note-title">
                <div><p class="showroom-page-eyebrow">Penjualan</p><h1 id="sale-note-title">Laporan Penjualan</h1><p>Daftar catatan penjualan yang terhubung langsung dengan data kendaraan.</p></div>
                <div class="sale-note-page-actions"><span class="showroom-page-count">{{ payload?.meta?.total ?? 0 }} nota</span></div>
            </section>
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <section class="panel showroom-inventory sale-note-table-card" aria-label="Daftar nota penjualan">
                <div class="table-scroll"><table class="data-table sale-note-table"><thead><tr><th>Kendaraan</th><th>Nama pembeli</th><th>Tanggal jual</th><th class="numeric">Harga jual</th><th v-if="payload.can_view_sensitive_data" class="numeric">Modal Beli</th><th v-if="payload.can_view_sensitive_data" class="numeric">Total Modal</th><th v-if="payload.can_view_sensitive_data" class="numeric">Laba Jual</th><th v-if="payload.can_view_sensitive_data" class="numeric">Laba Stor</th><th v-if="payload.can_view_sensitive_data" class="numeric">Laba Deal</th><th>Aksi</th></tr></thead>
                    <tbody><tr v-for="note in rows" :key="note.id"><td data-label="Kendaraan"><span class="cell-primary">{{ note.car_transaction?.plate_number }}</span><span class="cell-secondary">{{ note.car_transaction?.brand }} {{ note.car_transaction?.model }} · {{ note.car_transaction?.year }}</span></td><td data-label="Nama konsumen">{{ note.nama_konsumen }}</td><td data-label="Tanggal jual">{{ displayDate(note.car_transaction?.sell_date) }}</td><td class="numeric">{{ currency(note.car_transaction?.selling_price) }}</td><template v-if="payload.can_view_sensitive_data"><td class="numeric">{{ currency(note.car_transaction.buy_price) }}</td><td class="numeric">{{ currency(note.car_transaction.total_capital) }}</td><td class="numeric">{{ currency(note.car_transaction.gross_profit) }}</td><td class="numeric">{{ currency(note.car_transaction.setor_price - note.car_transaction.total_capital) }}</td><td class="numeric">{{ currency(note.car_transaction.gross_profit + note.car_transaction.leasing_ripan + note.car_transaction.insurance_ripan + note.car_transaction.booster_ripan) }}</td></template><td><div class="sale-note-row-actions"><RouterLink class="btn btn-secondary" :to="`/nota-penjualan/${note.id}`">Detail</RouterLink><RouterLink class="btn btn-secondary" :to="`/nota-penjualan/${note.id}/edit`">Edit</RouterLink></div></td></tr>
                        <tr v-if="!rows.length"><td class="table-empty" :colspan="payload.can_view_sensitive_data ? 10 : 5"><strong>Belum ada nota penjualan</strong>Transaksi yang dicatat melalui menu Penjualan akan muncul di sini.</td></tr>
                    </tbody></table></div>
                <div v-if="pagination?.last_page > 1" class="table-foot"><button class="btn btn-secondary" type="button" :disabled="pagination.current_page <= 1 || isLoading" @click="goToPage(pagination.current_page - 1)">Sebelumnya</button><span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span><button class="btn btn-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page || isLoading" @click="goToPage(pagination.current_page + 1)">Berikutnya</button></div>
            </section>
        </template>
        <template v-else-if="isEdit">
            <section class="sale-note-form-heading" aria-labelledby="sale-note-form-title"><div><p class="showroom-page-eyebrow page-eyebrow">Nota penjualan</p><h1 id="sale-note-form-title">Edit Nota Penjualan</h1><p>Spesifikasi dan rincian kendaraan ditampilkan dari data stok utama.</p></div><RouterLink to="/nota-penjualan" class="btn btn-secondary">Kembali ke daftar</RouterLink></section>
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <form class="transaction-form jual-form sell-module-form transaction-form--sale-note" :class="payload?.can_view_sensitive_data ? 'transaction-form--master' : 'transaction-form--admin'" :aria-busy="isSaving" @submit.prevent="submitForm">
                <section class="sale-note-card">
                    <header class="sale-note-card-heading"><span class="sale-note-section-number">01</span><div><h2>Data kendaraan</h2><p>Spesifikasi ditampilkan dari data stok utama.</p></div></header>
                    <label class="sale-note-field">Kendaraan
                        <input :value="payload.car_transaction?.plate_number" readonly>
                    </label>
                    <dl v-if="selectedVehicle" class="sale-note-vehicle-summary">
                        <div v-for="[key, label] in vehicleFields.slice(0, 10)" :key="key"><span>{{ label }}</span><strong>{{ selectedVehicle[key] ?? '—' }}</strong></div>
                    </dl>
                </section>
                <section class="sale-note-card">
                    <header class="sale-note-card-heading"><span class="sale-note-section-number">02</span><div><h2>Informasi konsumen</h2><p>Data identitas pembeli pada nota.</p></div></header>
                    <label class="sale-note-field">Nama konsumen<input v-model="form.nama_konsumen" maxlength="255" required :disabled="isSaving"><small v-if="fieldErrors.nama_konsumen" class="sale-note-error">{{ fieldErrors.nama_konsumen[0] }}</small></label>
                    <label class="sale-note-field">NIK<input v-model="form.buyer_nik" inputmode="numeric" maxlength="16" autocomplete="off" placeholder="Masukkan 16 digit NIK" :disabled="isSaving"><small v-if="fieldErrors.buyer_nik" class="sale-note-error">{{ fieldErrors.buyer_nik[0] }}</small></label>
                    <label class="sale-note-field">Alamat<textarea v-model="form.buyer_address" rows="2" maxlength="2000" placeholder="Masukkan alamat lengkap konsumen" :disabled="isSaving"></textarea><small v-if="fieldErrors.buyer_address" class="sale-note-error">{{ fieldErrors.buyer_address[0] }}</small></label>
                    <label class="sale-note-field">No. HP<input v-model="form.buyer_phone" type="tel" maxlength="30" autocomplete="tel" placeholder="Masukkan nomor WhatsApp/HP" :disabled="isSaving"><small v-if="fieldErrors.buyer_phone" class="sale-note-error">{{ fieldErrors.buyer_phone[0] }}</small></label>
                </section>
                <section class="sale-note-card">
                    <header class="sale-note-card-heading"><span class="sale-note-section-number">03</span><div><h2>Skema pembayaran</h2><p>Perbarui rincian transaksi yang sudah tercatat.</p></div></header>
                    <label class="sale-note-field">Kategori pembayaran<select v-model="form.payment_type" required :disabled="isSaving"><option value="selling_price">Harga Jual (Rp)</option><option value="down_payment">DP (Rp)</option><option value="settlement_amount">Pelunasan (Rp)</option><option value="leasing_disbursement">Pencairan Leasing (Rp)</option></select><small v-if="fieldErrors.payment_type" class="sale-note-error">{{ fieldErrors.payment_type[0] }}</small></label>
                    <label class="sale-note-field">Nilai pembayaran (Rp)<input v-model="form.payment_amount" type="number" min="0" step="0.01" required :disabled="isSaving"><small v-if="fieldErrors.payment_amount" class="sale-note-error">{{ fieldErrors.payment_amount[0] }}</small></label>
                    <label class="sale-note-field">Sisa pembayaran (Rp)<output class="status-badge" aria-live="polite">{{ currency(remainingPayment) }}</output></label>
                    <label class="sale-note-field">Tanggal jual<input v-model="form.sell_date" type="date" required :disabled="isSaving"><small v-if="fieldErrors.sell_date" class="sale-note-error">{{ fieldErrors.sell_date[0] }}</small></label>
                </section>
                <section class="sale-note-card">
                    <header class="sale-note-card-heading"><span class="sale-note-section-number">04</span><div><h2>Biaya & komisi penjualan</h2><p>Rincian pengeluaran pada transaksi penjualan.</p></div></header>
                    <label v-for="(expense, index) in form.intertine_expenses" :key="expense.name" class="sale-note-field"><input type="hidden" :name="`intertine_expenses[${index}][name]`" :value="expense.name">{{ expense.name }}<input v-model="expense.cost" :name="`intertine_expenses[${index}][cost]`" type="number" min="0" step="0.01" :disabled="isSaving"></label>
                </section>
                <div class="sale-note-form-actions"><RouterLink to="/nota-penjualan" class="btn btn-secondary">Batal</RouterLink><button class="btn btn-accent" type="submit" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan nota' }}</button></div>
            </form>
        </template>
        <template v-else-if="isDetail">
            <section class="showroom-page-header sale-note-heading"><div><p class="showroom-page-eyebrow">Detail nota penjualan</p><h1>{{ payload.nama_konsumen }}</h1><p>{{ payload.car_transaction?.plate_number }} · {{ payload.car_transaction?.brand }} {{ payload.car_transaction?.model }} · {{ displayDate(payload.car_transaction?.sell_date) }}</p></div><div class="sale-note-page-actions"><RouterLink :to="`/nota-penjualan/${payload.id}/edit`" class="btn btn-accent">Edit nota</RouterLink><RouterLink to="/nota-penjualan" class="btn btn-secondary">Kembali</RouterLink></div></section>
            <section class="panel sale-note-detail-card" aria-label="Detail nota"><dl class="sale-note-detail-list">
                <div><dt>Nama konsumen</dt><dd>{{ payload.nama_konsumen }}</dd></div>
                <div><dt>NIK</dt><dd>{{ payload.buyer_nik || '—' }}</dd></div>
                <div><dt>Alamat</dt><dd>{{ payload.buyer_address || '—' }}</dd></div>
                <div><dt>No. HP</dt><dd>{{ payload.buyer_phone || '—' }}</dd></div>
                <div><dt>Harga jual</dt><dd>{{ currency(payload.car_transaction?.selling_price) }}</dd></div>
                <div><dt>Tanggal jual</dt><dd>{{ displayDate(payload.car_transaction?.sell_date) }}</dd></div>
                <template v-if="payload.can_view_sensitive_data">
                    <div><dt>Modal beli</dt><dd>{{ currency(payload.car_transaction?.buy_price) }}</dd></div>
                    <div><dt>Total modal</dt><dd>{{ currency(payload.car_transaction?.total_capital) }}</dd></div>
                    <div><dt>Laba jual</dt><dd>{{ currency(payload.car_transaction?.gross_profit) }}</dd></div>
                    <div><dt>Laba stor</dt><dd>{{ currency(Number(payload.car_transaction?.setor_price) - Number(payload.car_transaction?.total_capital)) }}</dd></div>
                    <div><dt>Laba deal</dt><dd>{{ currency(Number(payload.car_transaction?.gross_profit) + Number(payload.car_transaction?.leasing_ripan) + Number(payload.car_transaction?.insurance_ripan) + Number(payload.car_transaction?.booster_ripan)) }}</dd></div>
                </template>
            </dl></section>
        </template>
    </section>
</template>
