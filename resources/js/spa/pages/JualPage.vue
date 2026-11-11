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
const selectedVehicle = computed(() => {
    if (isEdit.value) return payload.value?.car_transaction ?? null;

    return payload.value?.vehicles?.find(
        (vehicle) => String(vehicle.id) === String(form.car_transaction_id),
    ) ?? null;
});
const remainingPayment = computed(() => Math.max(
    Number(form.selling_price || 0)
    - Number(form.down_payment || 0)
    - Number(form.settlement_amount || 0)
    - Number(form.leasing_disbursement || 0),
    0,
));

function currency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
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
        try {
            if (pageMode.value === 'index') {
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
        await router.push(`/jual/${response.data.id}`);
    } catch (error) {
        errorMessage.value = error.message;
        fieldErrors.value = error.errors ?? {};
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
    ['brand', 'Merek'], ['model', 'Model'], ['year', 'Tahun'], ['transmission', 'Transmisi'],
    ['color', 'Warna'], ['chassis_number', 'Nomor rangka'], ['engine_number', 'Nomor mesin'],
    ['mileage_km', 'Kilometer (Km)'], ['owner_name', 'Atas nama STNK'], ['manager_name', 'Penanggung jawab'],
];
</script>

<template>
    <section class="showroom-content jual-page">
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat data penjualan...</div>
        <div v-else-if="errorMessage && !payload" class="error-panel" role="alert">{{ errorMessage }}</div>
        <template v-else-if="pageMode === 'index'">
            <div v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</div>
            <div class="jual-index-toolbar">
                <nav class="jual-page-actions jual-status-filters" aria-label="Filter status pembayaran">
                    <button v-for="(label, status) in { all: 'Semua', unpaid: 'Belum Lunas', leasing: 'Proses Leasing', paid: 'Lunas' }" :key="status" type="button" class="btn" :class="currentStatus === status ? 'btn-accent' : 'btn-secondary'" :aria-current="currentStatus === status ? 'page' : undefined" :disabled="isSaving || isLoading" @click="setStatus(status)">{{ label }}</button>
                </nav>
                <RouterLink to="/jual/create" class="showroom-primary-action jual-create-action"><span aria-hidden="true">+</span>Input penjualan</RouterLink>
            </div>
            <section class="panel showroom-inventory jual-table-card" aria-label="Daftar transaksi Jual">
                <div class="table-scroll"><table class="data-table jual-table"><thead><tr><th>Kendaraan</th><th>Konsumen</th><th>Tanggal</th><th class="numeric">Harga deal</th><th>Status pembayaran</th><th v-if="payload.can_view_sensitive_data" class="numeric">Modal beli</th><th v-if="payload.can_view_sensitive_data" class="numeric">Total modal</th><th v-if="payload.can_view_sensitive_data" class="numeric">Laba deal</th><th>Aksi</th></tr></thead>
                    <tbody><tr v-for="note in rows" :key="note.id"><td data-label="Kendaraan"><span class="cell-primary">{{ note.car_transaction.plate_number }}</span><span class="cell-secondary">{{ note.car_transaction.brand }} {{ note.car_transaction.model }} · {{ note.car_transaction.year }}</span></td><td data-label="Konsumen">{{ note.nama_konsumen }}</td><td data-label="Tanggal">{{ note.car_transaction.sell_date }}</td><td class="numeric" data-label="Harga deal">{{ currency(note.car_transaction.selling_price) }}</td><td data-label="Status pembayaran"><span class="status-badge" :class="{ pending: note.payment_status !== 'paid' }">{{ statusLabels[note.payment_status] }}</span></td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.buy_price) }}</td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.total_capital) }}</td><td v-if="payload.can_view_sensitive_data" class="numeric">{{ currency(note.car_transaction.gross_profit + note.car_transaction.leasing_ripan + note.car_transaction.insurance_ripan + note.car_transaction.booster_ripan) }}</td><td data-label="Aksi"><div class="jual-page-actions"><RouterLink class="btn btn-secondary" :to="`/jual/${note.id}`">Detail</RouterLink><RouterLink v-if="Number(note.car_transaction.remaining_payment) > 0" class="btn btn-accent" :to="`/jual/${note.id}/pelunasan`">Catat pelunasan</RouterLink><RouterLink class="btn btn-secondary" :to="`/jual/${note.id}/edit`">Edit</RouterLink><button class="btn btn-secondary" type="button" :disabled="isSaving" @click="deleteNote(note)">Hapus</button></div></td></tr>
                        <tr v-if="!rows.length"><td class="table-empty" :colspan="payload.can_view_sensitive_data ? 9 : 6"><strong>Belum ada transaksi Jual</strong>Catat penjualan kendaraan melalui menu ini untuk memulai.</td></tr>
                    </tbody></table></div>
                <div v-if="pagination?.last_page > 1" class="table-foot"><button type="button" class="btn btn-secondary" :disabled="pagination.current_page <= 1 || isLoading" @click="goToPage(pagination.current_page - 1)">Sebelumnya</button><span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span><button type="button" class="btn btn-secondary" :disabled="pagination.current_page >= pagination.last_page || isLoading" @click="goToPage(pagination.current_page + 1)">Berikutnya</button></div>
            </section>
        </template>
        <template v-else-if="pageMode === 'create' || pageMode === 'edit'">
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <div class="jual-form-toolbar">
                <RouterLink to="/jual" class="btn btn-secondary">Kembali ke daftar</RouterLink>
            </div>
            <form class="transaction-form jual-form sell-module-form" :class="payload?.can_view_sensitive_data ? 'transaction-form--master' : 'transaction-form--admin'" :aria-busy="isSaving" @submit.prevent="submitSell">
                <section class="form-section sell-module-form-section">
                    <header class="section-heading"><span class="section-number">01</span><div><h2 id="jual-form-title">{{ isEdit ? 'Edit transaksi Jual' : 'Input Penjualan' }}</h2><p>Masukkan data kendaraan, konsumen, dan pembayaran.</p></div></header>
                    <fieldset class="section-content grid gap-4" :disabled="isSaving" aria-labelledby="jual-form-title">
                    <section class="sell-vehicle-section"><h3 class="form-section-title">Data kendaraan</h3><div class="sell-vehicle-fields">
                        <div class="sell-form-row"><label for="jual-vehicle-select">Plat nomor</label><div class="sell-form-control">
                            <select v-if="!isEdit" id="jual-vehicle-select" v-model="form.car_transaction_id" name="car_transaction_id" required><option value="">Pilih kendaraan</option><option v-for="vehicle in payload.vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate_number }} · {{ vehicle.brand }} {{ vehicle.model }}</option></select>
                            <input v-else id="jual-vehicle-select" :value="payload.car_transaction?.plate_number" readonly>
                            <small v-if="fieldErrors.car_transaction_id" class="form-help">{{ fieldErrors.car_transaction_id[0] }}</small>
                        </div></div>
                        <div class="sell-vehicle-readonly-fields"><div v-for="[key, label] in vehicleFields" :key="key" class="sell-form-row"><label>{{ label }}</label><input :value="selectedVehicle?.[key] ?? ''" readonly></div></div>
                        <p v-if="!payload.has_available_vehicles && !isEdit" class="form-help">Tidak ada kendaraan yang tersedia untuk dicatat pada Menu Jual.</p>
                    </div></section>
                    <section class="sell-consumer-section"><h3 class="form-section-title">Informasi konsumen</h3><div class="sell-consumer-fields">
                        <label class="sell-form-row"><span>Nama konsumen</span><div class="sell-form-control"><input v-model="form.nama_konsumen" name="nama_konsumen" maxlength="255" required><small v-if="fieldErrors.nama_konsumen" class="form-help">{{ fieldErrors.nama_konsumen[0] }}</small></div></label>
                        <label class="sell-form-row"><span>NIK</span><div class="sell-form-control"><input v-model="form.buyer_nik" name="buyer_nik" inputmode="numeric" maxlength="16"><small v-if="fieldErrors.buyer_nik" class="form-help">{{ fieldErrors.buyer_nik[0] }}</small></div></label>
                        <label class="sell-form-row"><span>Alamat</span><div class="sell-form-control"><textarea v-model="form.buyer_address" name="buyer_address" rows="2" maxlength="2000"></textarea><small v-if="fieldErrors.buyer_address" class="form-help">{{ fieldErrors.buyer_address[0] }}</small></div></label>
                        <label class="sell-form-row"><span>No. HP</span><div class="sell-form-control"><input v-model="form.buyer_phone" name="buyer_phone" type="tel" maxlength="30"><small v-if="fieldErrors.buyer_phone" class="form-help">{{ fieldErrors.buyer_phone[0] }}</small></div></label>
                    </div></section>
                    <section aria-labelledby="jual-payment-title" class="sell-payment-section">
                        <h3 id="jual-payment-title" class="form-section-title">Pembayaran</h3>
                        <div class="sell-payment-rows">
                            <div v-for="[key, label, dateKey, dateLabel] in [['selling_price','Harga jual (Rp)','sell_date','Tanggal transaksi'],['down_payment','DP (Rp)','down_payment_date','Tanggal pembayaran DP'],['settlement_amount','Pelunasan (Rp)','settlement_date','Tanggal pelunasan'],['leasing_disbursement','Pencairan leasing (Rp)','leasing_disbursement_date','Tanggal pencairan leasing']]" :key="key" class="sell-payment-row">
                                <label :for="`jual-${key}`">{{ label }}</label>
                                <div class="sell-payment-row-fields">
                                    <div class="form-group"><label class="sr-only" :for="`jual-${key}`">Nominal {{ label }}</label><input :id="`jual-${key}`" v-model="form[key]" :name="key" type="number" min="0" step="0.01" required><small v-if="fieldErrors[key]" class="form-help">{{ fieldErrors[key][0] }}</small></div>
                                    <div class="form-group"><label :for="`jual-${dateKey}`">{{ dateLabel }}</label><input :id="`jual-${dateKey}`" v-model="form[dateKey]" :name="dateKey" type="date" :required="dateKey === 'sell_date'"><small v-if="fieldErrors[dateKey]" class="form-help">{{ fieldErrors[dateKey][0] }}</small></div>
                                </div>
                            </div>
                            <div class="sell-payment-row sell-payment-row--remaining">
                                <label for="jual-remaining-payment">Sisa pembayaran (Rp)</label>
                                <div class="sell-payment-row-fields">
                                    <output id="jual-remaining-payment" class="status-badge" aria-live="polite">{{ currency(remainingPayment) }}</output>
                                    <div class="form-group"><label for="jual-planned-payment-date">Tanggal rencana pembayaran</label><input id="jual-planned-payment-date" v-model="form.planned_payment_date" name="planned_payment_date" type="date"><small v-if="fieldErrors.planned_payment_date" class="form-help">{{ fieldErrors.planned_payment_date[0] }}</small></div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="jual-form-section jual-form-section--costs"><header class="jual-card-heading"><div><h2>Biaya & komisi penjualan</h2><p>Rincian ini tersimpan pada data kendaraan terpilih.</p></div></header>
                        <div class="field-grid two-column"><label v-for="(expense, index) in form.intertine_expenses" :key="expense.name" class="form-group"><input type="hidden" :name="`intertine_expenses[${index}][name]`" :value="expense.name">{{ expense.name }}<input v-model="expense.cost" :name="`intertine_expenses[${index}][cost]`" type="number" min="0" step="0.01"></label></div>
                    </section>
                    <div class="jual-form-actions"><RouterLink to="/jual" class="btn btn-secondary">Batal</RouterLink><button class="btn btn-accent" type="submit" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : isEdit ? 'Simpan perubahan' : 'Simpan penjualan' }}</button></div>
                    </fieldset>
                </section>
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
            <section class="showroom-page-header jual-heading"><div><p class="showroom-page-eyebrow">Detail Menu Jual</p><h1>{{ payload.nama_konsumen }}</h1><p>{{ payload.car_transaction?.plate_number }} · {{ payload.car_transaction?.brand }} {{ payload.car_transaction?.model }} · {{ payload.car_transaction?.sell_date }}</p></div><div class="jual-page-actions"><RouterLink v-if="Number(payload.car_transaction?.remaining_payment) > 0" :to="`/jual/${payload.id}/pelunasan`" class="btn btn-accent">Catat pelunasan</RouterLink><RouterLink :to="`/jual/${payload.id}/edit`" class="btn btn-accent">Edit</RouterLink><button class="btn btn-secondary" type="button" :disabled="isSaving" @click="deleteNote(payload)">Hapus</button><RouterLink to="/jual" class="btn btn-secondary">Kembali ke Jual</RouterLink></div></section>
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <section class="panel jual-detail-card"><dl class="jual-detail-list"><div v-for="[label, value] in [['Nama konsumen', payload.nama_konsumen], ['NIK', payload.buyer_nik || '—'], ['Alamat', payload.buyer_address || '—'], ['No. HP', payload.buyer_phone || '—'], ['Harga jual', currency(payload.car_transaction?.selling_price)], ['Tanggal jual', payload.car_transaction?.sell_date], ['DP', currency(payload.car_transaction?.down_payment)], ['Pelunasan diterima', currency(payload.car_transaction?.settlement_amount)], ['Pencairan leasing', currency(payload.car_transaction?.leasing_disbursement)], ['Total dibayar', currency(payload.total_paid)], ['Sisa pembayaran', currency(payload.car_transaction?.remaining_payment)], ['Tanggal rencana pembayaran', payload.car_transaction?.planned_payment_date || '—']]" :key="label"><dt>{{ label }}</dt><dd>{{ value }}</dd></div><div><dt>Status pembayaran</dt><dd><span class="status-badge" :class="{ pending: payload.payment_status !== 'paid' }">{{ statusLabels[payload.payment_status] }}</span></dd></div></dl></section>
            <section class="panel jual-detail-card" aria-labelledby="jual-payments-title"><h2 id="jual-payments-title">Riwayat pelunasan</h2><div v-if="payload.payments?.length" class="table-scroll"><table class="data-table jual-table"><thead><tr><th>Tanggal</th><th>Metode</th><th class="numeric">Nominal</th><th>Catatan</th></tr></thead><tbody><tr v-for="item in payload.payments" :key="item.id"><td>{{ item.payment_date }}</td><td>{{ paymentLabels[item.payment_method] }}</td><td class="numeric">{{ currency(item.amount) }}</td><td>{{ item.notes || '—' }}</td></tr></tbody></table></div><p v-else>Belum ada pembayaran pelunasan tambahan yang dicatat.</p></section>
        </template>
    </section>
</template>
