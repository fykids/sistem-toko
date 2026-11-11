<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiFormRequest, apiRequest } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const isEditing = computed(() => Boolean(route.params.transaction));
const canViewSensitiveData = window.spaUser?.role === 'master';
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const photos = ref([]);
const newPhotos = ref([]);
const newPhotoInput = ref(null);
const originalPhotoIds = ref([]);
const removeLegacyPhoto = ref(false);
const coverPhoto = ref('');
const leasingCompanies = ['BCA', 'MUF', 'BUANA', 'ADIRA', 'SINAR MAS', 'WORI', 'WOM'];
const form = reactive(emptyTransaction());
let activeLoadController = null;
const moneyFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const totalRepairCost = computed(() => sumAmounts(form.additional_repairs, 'cost'));
const totalCapital = computed(() => (
    amount(form.buy_price)
    + amount(form.purchase_operational)
    + amount(form.other_costs)
    + sumAmounts(form.tax_items, 'cost')
    + totalRepairCost.value
));
const saleProfit = computed(() => amount(form.selling_price) - totalCapital.value);
const totalProfit = computed(() => (
    saleProfit.value
    + amount(form.leasing_ripan)
    + amount(form.insurance_ripan)
    + amount(form.booster_ripan)
));
const setorProfit = computed(() => amount(form.setor_price) - totalCapital.value);
const setorProfitPercentage = computed(() => amount(form.setor_price) === 0
    ? 0
    : 100 - (totalCapital.value / amount(form.setor_price) * 100));
const openingProfit = computed(() => amount(form.opening_price) - totalCapital.value);
const openingProfitPercentage = computed(() => amount(form.opening_price) === 0
    ? 0
    : 100 - (totalCapital.value / amount(form.opening_price) * 100));
const showroomFee = computed(() => Math.round(saleProfit.value * 0.1 * 100) / 100);
const totalIntertineExpenses = computed(() => (
    showroomFee.value + sumAmounts(form.intertine_expenses, 'cost')
));
const netProfit = computed(() => saleProfit.value - totalIntertineExpenses.value);
const remainingPayment = computed(() => Math.max(
    amount(form.selling_price)
    - amount(form.down_payment)
    - amount(form.settlement_amount)
    - amount(form.leasing_disbursement),
    0,
));
const leasingVehicleName = computed(() => [
    form.brand,
    form.model,
    form.year,
    form.transmission,
    form.color,
    form.plate_number,
].filter(Boolean).join(' ') || 'Lengkapi data kendaraan');

function amount(value) {
    return Number(value) || 0;
}

function sumAmounts(items, key) {
    return items.reduce((total, item) => total + amount(item[key]), 0);
}

function formatted(value) {
    return moneyFormatter.format(value);
}

function formattedPercentage(value) {
    return `${formatted(value)}%`;
}

function emptyTransaction() {
    return {
        plate_number: '', brand: '', model: '', year: '', transmission: '', color: '',
        chassis_number: '', engine_number: '', mileage_km: '', owner_name: '', manager_name: '',
        nama_penjual: '', nik_penjual: '', no_hp_penjual: '', alamat_penjual: '', nama_broker: '',
        buy_date: '', buy_price: 0, purchase_operational: 0, other_costs: 0,
        selling_price: 0, down_payment: 0, down_payment_date: '', settlement_amount: 0,
        settlement_date: '', leasing_disbursement: 0, leasing_disbursement_date: '',
        planned_payment_date: '', setor_price: 0, opening_price: 0,
        leasing_ripan: 0, insurance_ripan: 0, booster_ripan: 0, sell_date: '',
        tax_items: [
            { name: 'Pajak', cost: 0, estimated_cost: '', date: '' },
            { name: 'BBN', cost: 0, estimated_cost: '', date: '' },
            { name: 'ETLE', cost: 0, estimated_cost: '', date: '' },
        ],
        additional_repairs: [],
        intertine_expenses: [
            { name: 'KR Jual', cost: 0 },
            { name: 'OP Survei', cost: 0 },
            { name: 'Fee CMO', cost: 0 },
        ],
        leasing_options: leasingCompanies.map((name) => ({ name })),
    };
}

function syncTransaction(transaction) {
    Object.assign(form, emptyTransaction(), transaction, {
        nama_penjual: transaction.seller_name ?? '',
        nik_penjual: transaction.seller_nik ?? '',
        no_hp_penjual: transaction.seller_phone ?? '',
        alamat_penjual: transaction.seller_address ?? '',
        nama_broker: transaction.broker_name ?? '',
        leasing_options: transaction.leasing_options?.length
            ? transaction.leasing_options
            : emptyTransaction().leasing_options,
        tax_items: transaction.tax_items?.length ? transaction.tax_items : emptyTransaction().tax_items,
        additional_repairs: transaction.additional_repairs ?? [],
        intertine_expenses: transaction.intertine_expenses?.length
            ? transaction.intertine_expenses
            : emptyTransaction().intertine_expenses,
    });
    photos.value = transaction.vehicle_photos ?? [];
    if (
        transaction.vehicle_photo_path
        && !photos.value.some((photo) => photo.url === transaction.legacy_photo_url)
    ) {
        photos.value.push({ id: 'legacy', url: transaction.legacy_photo_url, is_cover: true });
    }
    originalPhotoIds.value = photos.value.filter((photo) => photo.id !== 'legacy').map((photo) => photo.id);
    coverPhoto.value = photos.value.find((photo) => photo.is_cover)?.id
        ? `existing:${photos.value.find((photo) => photo.is_cover).id}`
        : (transaction.vehicle_photo_path ? 'existing:legacy' : '');
}

function resetForm() {
    newPhotos.value.forEach(({ url }) => URL.revokeObjectURL(url));
    Object.assign(form, emptyTransaction());
    photos.value = [];
    newPhotos.value = [];
    originalPhotoIds.value = [];
    removeLegacyPhoto.value = false;
    coverPhoto.value = '';
    errorMessage.value = '';
    fieldErrors.value = {};
}

async function loadTransaction(transactionId, signal) {
    isLoading.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    try {
        const response = await apiRequest(`/api/spa/transactions/${transactionId}`, { signal });
        if (!signal.aborted) {
            syncTransaction(response.data);
        }
    } catch (error) {
        if (!signal.aborted && error.name !== 'AbortError') {
            errorMessage.value = error.message;
        }
    } finally {
        if (!signal.aborted) {
            isLoading.value = false;
        }
    }
}

watch(
    () => route.params.transaction,
    async (transactionId, _previousId, onCleanup) => {
        const controller = new AbortController();
        activeLoadController = controller;
        onCleanup(() => controller.abort());
        resetForm();

        if (!transactionId) {
            isLoading.value = false;
            return;
        }

        await loadTransaction(transactionId, controller.signal);
    },
    { immediate: true },
);

function retryLoad() {
    const transactionId = route.params.transaction;
    if (!transactionId) return;

    activeLoadController?.abort();
    const controller = new AbortController();
    activeLoadController = controller;
    loadTransaction(transactionId, controller.signal);
}

function addRepair() {
    form.additional_repairs.push({ name: '', date: '', cost: 0, payment_status: 'lunas', payment_note: '' });
}

function removeRepair(index) {
    form.additional_repairs.splice(index, 1);
}

function addTax() {
    if (form.tax_items.length < 50) {
        form.tax_items.push({ name: '', cost: 0, estimated_cost: '', date: '' });
    }
}

function removeTax(index) {
    if (index >= 3) {
        form.tax_items.splice(index, 1);
    }
}

function addLeasing() {
    if (form.leasing_options.length < 50) form.leasing_options.push({ name: '' });
}

function updateLeasingDownPayment(option) {
    option.dp = Math.max(amount(option.selling_price) - amount(option.disbursement), 0);
}

function addPhotos(event) {
    const files = Array.from(event.target.files ?? [])
        .filter((file) => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
    const remainingSlots = Math.max(0, 20 - photos.value.length - newPhotos.value.length);
    const filesToAdd = files.slice(0, remainingSlots);

    newPhotos.value = [
        ...newPhotos.value,
        ...filesToAdd.map((file) => ({ file, url: URL.createObjectURL(file) })),
    ];
    if (!coverPhoto.value && newPhotos.value.length) coverPhoto.value = `new:0`;
    event.target.value = '';
}

function removeNewPhoto(index) {
    const [photo] = newPhotos.value.splice(index, 1);
    URL.revokeObjectURL(photo.url);

    if (coverPhoto.value === `new:${index}`) {
        coverPhoto.value = newPhotos.value.length ? 'new:0' : '';
    } else if (coverPhoto.value.startsWith('new:')) {
        const coverIndex = Number(coverPhoto.value.slice(4));
        if (coverIndex > index) coverPhoto.value = `new:${coverIndex - 1}`;
    }
}

function removeExistingPhoto(photo) {
    photos.value = photos.value.filter((item) => item.id !== photo.id);
    if (photo.id === 'legacy') removeLegacyPhoto.value = true;
    if (coverPhoto.value === `existing:${photo.id}`) coverPhoto.value = '';
}

function addInput(formData, name, value) {
    if (Array.isArray(value)) {
        value.forEach((item, index) => {
            Object.entries(item).forEach(([key, itemValue]) => {
                formData.append(`${name}[${index}][${key}]`, itemValue ?? '');
            });
        });
        return;
    }

    formData.append(name, value ?? '');
}

async function submitForm() {
    isSaving.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};
    const body = new FormData();

    const keptIds = photos.value.map((photo) => String(photo.id));
    originalPhotoIds.value.filter((id) => !keptIds.includes(String(id)))
        .forEach((id) => body.append('hapus_foto[]', id));
    Object.keys(emptyTransaction()).forEach((name) => addInput(body, name, form[name]));
    newPhotos.value.forEach(({ file }) => body.append('foto_kendaraan[]', file));
    if (coverPhoto.value) body.append('foto_utama', coverPhoto.value);
    if (removeLegacyPhoto.value) body.append('hapus_foto_legacy', '1');
    if (!canViewSensitiveData) {
        body.set('buy_price', '0');
        body.set('leasing_ripan', '0');
        body.set('insurance_ripan', '0');
        body.set('booster_ripan', '0');
    }
    if (isEditing.value) body.append('_method', 'PUT');

    try {
        await apiFormRequest(
            isEditing.value
                ? `/api/spa/transactions/${route.params.transaction}`
                : '/api/spa/transactions',
            { body },
        );
        await router.push('/dashboard');
    } catch (error) {
        errorMessage.value = error.message;
        fieldErrors.value = error.errors ?? {};
    } finally {
        isSaving.value = false;
    }
}

onBeforeUnmount(() => {
    activeLoadController?.abort();
    newPhotos.value.forEach(({ url }) => URL.revokeObjectURL(url));
});

const vehicleFields = [
    ['plate_number', 'Plat nomor', 'text', true],
    ['brand', 'Merek', 'text', true],
    ['model', 'Model', 'text', true],
    ['year', 'Tahun', 'number', true],
    ['transmission', 'Transmisi', 'text', true],
    ['color', 'Warna', 'text', true],
    ['chassis_number', 'Nomor rangka', 'text'],
    ['engine_number', 'Nomor mesin', 'text'],
    ['mileage_km', 'Kilometer (Km)', 'number'],
    ['owner_name', 'Atas nama STNK', 'text'],
    ['manager_name', 'Penanggung jawab', 'text'],
];
</script>

<template>
    <section class="transaction-form-page showroom-content">
        <div class="page-heading">
            <div><p class="page-eyebrow">Data showroom</p><h1>{{ isEditing ? 'Edit transaksi' : 'Transaksi baru' }}</h1></div>
            <RouterLink class="btn btn-secondary" to="/dashboard">Kembali</RouterLink>
        </div>
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat transaksi...</div>
        <div v-else-if="errorMessage" class="error-panel" role="alert">
            <p>{{ errorMessage }}</p>
            <button v-if="isEditing" class="btn btn-secondary" type="button" @click="retryLoad">Coba lagi</button>
        </div>
        <form v-else-if="!isLoading" class="transaction-form" :class="{ 'transaction-form--master': canViewSensitiveData, 'transaction-form--admin': !canViewSensitiveData }" :aria-busy="isSaving" @submit.prevent="submitForm">
            <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
            <section class="form-section vehicle-section">
                <header class="section-heading"><span class="section-number">01</span><div><h2>Data kendaraan</h2></div></header>
                <fieldset class="section-content field-grid vehicle-fields" :disabled="isSaving" aria-label="Data kendaraan">
                    <div v-for="[name, label, type, required] in vehicleFields" :key="name" class="form-group">
                        <label :for="`transaction-${name}`">{{ label }} <span v-if="required" aria-hidden="true">*</span></label>
                        <input :id="`transaction-${name}`" v-model="form[name]" :type="type" :name="name" :required="required" :min="type === 'number' ? 0 : undefined">
                        <small v-if="fieldErrors[name]" class="form-help">{{ fieldErrors[name][0] }}</small>
                    </div>
                </fieldset>
            </section>

            <section class="form-section costs-section">
                <header class="section-heading"><span class="section-number">02</span><div><h2>{{ canViewSensitiveData ? 'Modal dan biaya kendaraan' : 'Tanggal beli dan biaya kendaraan' }}</h2></div></header>
                <fieldset class="section-content" :disabled="isSaving" aria-label="Modal dan biaya kendaraan">
                    <input v-if="!canViewSensitiveData" type="hidden" name="buy_price" value="0">
                    <div class="field-grid costs-primary-grid">
                        <div v-if="canViewSensitiveData" class="form-group buy-date-group"><label for="buy-price">Modal Beli (Rp) *</label><div class="cost-inputs cost-inputs--dated"><input id="buy-price" v-model="form.buy_price" name="buy_price" type="number" min="0" step="0.01" required><input id="buy-date" v-model="form.buy_date" name="buy_date" type="date" aria-label="Tanggal beli" required></div></div>
                        <div v-else class="form-group buy-date-group"><label for="buy-date">Tanggal beli</label><div class="cost-inputs cost-inputs--date-only"><input id="buy-date" v-model="form.buy_date" name="buy_date" type="date" required></div></div>
                    </div>
                    <div class="field-grid costs-vertical-grid">
                        <div class="form-group"><label for="purchase-operational">OPS (Rp)</label><div class="cost-inputs"><input id="purchase-operational" v-model="form.purchase_operational" name="purchase_operational" type="number" min="0" step="0.01"></div></div>
                        <div class="form-group"><label for="other-costs">KR Beli (Rp)</label><div class="cost-inputs"><input id="other-costs" v-model="form.other_costs" name="other_costs" type="number" min="0" step="0.01"></div></div>
                        <template v-for="(tax, index) in form.tax_items" :key="index">
                            <input v-if="index < 3" type="hidden" :name="`tax_items[${index}][name]`" :value="tax.name">
                            <div v-if="canViewSensitiveData && index === 0" class="form-group intertine-expense-item"><label for="intertine-kr-jual">KR Jual</label><div class="cost-inputs"><input id="intertine-kr-jual" v-model="form.intertine_expenses[0].cost" name="intertine_expenses[0][cost]" type="number" min="0" step="0.01"><input type="hidden" name="intertine_expenses[0][name]" value="KR Jual"></div></div>
                            <input type="hidden" :name="`tax_items[${index}][payment_status]`" :value="tax.payment_status ?? ''">
                            <input type="hidden" :name="`tax_items[${index}][payment_note]`" :value="tax.payment_note ?? ''">
                            <div class="form-group tax-cost-group">
                                <label :for="`tax-${index}`">{{ index < 3 ? `Biaya ${['Pajak', 'BBN', 'ETLE'][index]}` : tax.name || 'Biaya pajak lainnya' }}</label>
                                <div class="cost-inputs cost-inputs--dated cost-inputs--tax">
                                    <input v-if="index >= 3" v-model="tax.name" :name="`tax_items[${index}][name]`" :aria-label="`Nama pajak ${index - 2}`" placeholder="Nama biaya">
                                    <input :id="`tax-${index}`" v-model="tax.cost" :name="`tax_items[${index}][cost]`" type="number" min="0" step="0.01">
                                    <input v-model="tax.estimated_cost" :name="`tax_items[${index}][estimated_cost]`" type="number" min="0" step="0.01" :aria-label="`Estimasi biaya ${tax.name}`" placeholder="Estimasi Rp 0">
                                    <input v-model="tax.date" :name="`tax_items[${index}][date]`" type="date" :aria-label="`Tanggal ${tax.name}`">
                                    <button v-if="index >= 3" class="icon-button remove-item" type="button" :aria-label="`Hapus biaya ${tax.name || index - 1}`" @click="removeTax(index)">Hapus</button>
                                </div>
                            </div>
                        </template>
                        <template v-if="!canViewSensitiveData">
                            <div v-for="(expense, index) in form.intertine_expenses" :key="expense.name" class="form-group intertine-expense-item">
                                <label :for="`admin-intertine-${index}`">{{ expense.name }}</label><input :id="`admin-intertine-${index}`" v-model="expense.cost" :name="`intertine_expenses[${index}][cost]`" type="number" min="0" step="0.01"><input type="hidden" :name="`intertine_expenses[${index}][name]`" :value="expense.name">
                            </div>
                        </template>
                    </div>
                    <div class="repair-list-actions"><button class="btn btn-secondary btn-small" type="button" :disabled="isSaving || form.additional_repairs.length >= 50" @click="addRepair">Tambah perbaikan</button><button class="btn btn-secondary btn-small" type="button" :disabled="isSaving || form.tax_items.length >= 50" @click="addTax">Tambah biaya pajak</button></div>
                    <div class="repeatable-list">
                        <fieldset v-for="(repair, index) in form.additional_repairs" :key="index" class="repair-form-row field-grid" :disabled="isSaving">
                            <label class="form-group">Nama<input v-model="repair.name" :name="`additional_repairs[${index}][name]`" required maxlength="100"></label>
                            <label class="form-group">Tanggal<input v-model="repair.date" :name="`additional_repairs[${index}][date]`" type="date" required></label>
                            <label class="form-group">Biaya<input v-model="repair.cost" :name="`additional_repairs[${index}][cost]`" type="number" min="0" step="0.01" required></label>
                            <label class="form-group">Status pembayaran<select v-model="repair.payment_status" :name="`additional_repairs[${index}][payment_status]`"><option value="lunas">Lunas</option><option value="belum_lunas">Belum lunas</option><option value="panjar">Panjar</option><option value="lainnya">Lainnya</option></select></label>
                            <label class="form-group">Catatan<input v-model="repair.payment_note" :name="`additional_repairs[${index}][payment_note]`" maxlength="255"></label>
                            <button class="btn btn-secondary remove-item" type="button" :disabled="isSaving" @click="removeRepair(index)">Hapus</button>
                        </fieldset>
                    </div>
                    <p v-if="!form.additional_repairs.length" class="form-help">Belum ada rincian perbaikan.</p>
                    <p v-if="fieldErrors.additional_repairs" class="form-help">{{ fieldErrors.additional_repairs[0] }}</p>
                    <div class="form-group calculated-repair-cost"><label>Total biaya perbaikan</label><input :value="formatted(totalRepairCost)" readonly aria-label="Total biaya perbaikan"></div>
                </fieldset>
            </section>

            <section class="form-section marketing-section">
                <header class="section-heading"><span class="section-number">03</span><div><h2>Strategi pemasaran</h2></div></header>
                <fieldset class="section-content marketing-fields" :disabled="isSaving" aria-label="Strategi pemasaran dan pembayaran">
                    <div v-if="canViewSensitiveData" class="form-group"><label for="calculated-total-capital">Total pengeluaran</label><input id="calculated-total-capital" :value="formatted(totalCapital)" readonly aria-label="Total pengeluaran modal kendaraan (Rupiah)"></div>
                    <div class="form-group setor-price-group"><label for="setor-price">Harga setor (Rp)</label><div class="setor-price-inputs" :class="{ 'price-inputs--restricted': !canViewSensitiveData }">
                        <input id="setor-price" v-model="form.setor_price" name="setor_price" type="number" min="0" step="0.01" aria-label="Harga setor (Rupiah)">
                        <template v-if="canViewSensitiveData"><input id="calculated-setor-profit" :value="formatted(setorProfit)" readonly aria-label="Selisih harga setor dengan modal (Rupiah)"><input id="calculated-setor-profit-percentage" :value="formattedPercentage(setorProfitPercentage)" readonly aria-label="Persentase selisih harga setor dari modal"></template>
                    </div></div>
                    <div class="form-group opening-price-group"><label for="opening-price">Harga buka (Rp)</label><div class="opening-price-inputs" :class="{ 'price-inputs--restricted': !canViewSensitiveData }">
                        <input id="opening-price" v-model="form.opening_price" name="opening_price" type="number" min="0" step="0.01" aria-label="Harga buka (Rupiah)">
                        <template v-if="canViewSensitiveData"><input id="calculated-opening-profit" :value="formatted(openingProfit)" readonly aria-label="Selisih harga buka dengan modal (Rupiah)"><input id="calculated-opening-profit-percentage" :value="formattedPercentage(openingProfitPercentage)" readonly aria-label="Persentase selisih harga buka dari modal"></template>
                    </div></div>
                    <div class="form-group selling-price-group"><label for="selling-price">Harga jual (Rp)</label><div class="selling-price-inputs" :class="{ 'selling-price-inputs--restricted': !canViewSensitiveData }" role="group" :aria-label="canViewSensitiveData ? 'Harga jual, laba, dan tanggal jual' : 'Harga jual dan tanggal jual'">
                        <div v-if="canViewSensitiveData" class="selling-price-amounts"><input id="selling-price" v-model="form.selling_price" name="selling_price" type="number" min="0" step="0.01" aria-label="Harga jual (Rupiah)"><input id="calculated-sale-profit" :value="formatted(saleProfit)" readonly aria-label="Laba jual (Rupiah)"></div>
                        <input v-else id="selling-price" v-model="form.selling_price" name="selling_price" type="number" min="0" step="0.01" aria-label="Harga jual (Rupiah)">
                        <input id="sell-date" v-model="form.sell_date" name="sell_date" type="date" aria-label="Tanggal jual">
                    </div></div>
                    <div class="sales-fields-row">
                        <div v-for="[name, label] in [['down_payment','DP (Rp)'],['settlement_amount','Pelunasan (Rp)'],['leasing_disbursement','Pencairan leasing (Rp)']]" :key="name" class="form-group">
                            <label :for="name">{{ label }}</label><div class="payment-inputs"><input :id="name" v-model="form[name]" :name="name" type="number" min="0" step="0.01"><input v-model="form[`${name}_date`]" :name="`${name}_date`" type="date" :aria-label="`Tanggal ${label}`"></div>
                        </div>
                        <div class="form-group"><label for="remaining-payment">Sisa pembayaran (Rp)</label><div class="payment-inputs payment-inputs--remaining"><input id="remaining-payment" :value="formatted(remainingPayment)" readonly aria-label="Sisa pembayaran (Rupiah)"><input id="planned-payment-date" v-model="form.planned_payment_date" name="planned_payment_date" type="date" aria-label="Tanggal rencana pembayaran (jatuh tempo)" :required="remainingPayment > 0"></div><small v-if="fieldErrors.planned_payment_date" class="form-help">{{ fieldErrors.planned_payment_date[0] }}</small></div>
                    </div>
                </fieldset>
            </section>

            <section v-if="canViewSensitiveData" class="form-section sales-section">
                <header class="section-heading"><span class="section-number">04</span><div><h2>Penjualan dan pembayaran</h2></div></header>
                <fieldset class="section-content field-grid two-column" :disabled="isSaving" aria-label="Penjualan dan pembayaran">
                    <div class="sales-fields">
                        <label class="form-group">Ripan leasing (Rp)<input v-model="form.leasing_ripan" name="leasing_ripan" type="number" min="0" step="0.01" aria-label="Ripan leasing (Rupiah)"></label>
                        <label class="form-group">Ripan asuransi (Rp)<input v-model="form.insurance_ripan" name="insurance_ripan" type="number" min="0" step="0.01" aria-label="Ripan asuransi (Rupiah)"></label>
                        <label class="form-group">Ripan booster (Rp)<input v-model="form.booster_ripan" name="booster_ripan" type="number" min="0" step="0.01" aria-label="Ripan booster (Rupiah)"></label>
                    </div>
                    <div class="form-group"><label for="calculated-total-profit">Total laba</label><input id="calculated-total-profit" :value="formatted(totalProfit)" readonly aria-label="Total laba (Rupiah)"></div>
                    <div class="form-group"><label for="calculated-showroom-fee">Fee Showroom (Rp)</label><input id="calculated-showroom-fee" :value="formatted(showroomFee)" readonly aria-label="Fee Showroom (Rupiah)"></div>
                    <div class="sales-fields">
                        <label v-for="(expense, index) in form.intertine_expenses.slice(1)" :key="expense.name" class="form-group"><input type="hidden" :name="`intertine_expenses[${index + 1}][name]`" :value="expense.name">{{ expense.name }}<input v-model="expense.cost" :name="`intertine_expenses[${index + 1}][cost]`" type="number" min="0" step="0.01"></label>
                    </div>
                    <div class="form-group"><label for="calculated-total-intertine-expenses">Total Pengeluaran Intertine (Rp)</label><input id="calculated-total-intertine-expenses" :value="formatted(totalIntertineExpenses)" readonly aria-label="Total Pengeluaran Intertine (Rupiah)"></div>
                    <div class="form-group"><label for="calculated-net-profit">Laba Bersih (Rp)</label><input id="calculated-net-profit" :value="formatted(netProfit)" readonly aria-label="Laba Bersih (Rupiah)"></div>
                </fieldset>
            </section>

            <div class="leasing-photo-layout">
                <div class="leasing-layout-column leasing-layout-column--vehicle-admin vehicle-admin-photo-layout">
                    <section class="form-section vehicle-photo-card">
                        <header class="section-heading"><span class="section-number">06</span><div><h2 id="vehicle-photo-title">Foto kendaraan</h2></div></header>
                        <fieldset class="section-content vehicle-photo-content" :disabled="isSaving" aria-label="Foto kendaraan">
                            <button id="vehicle-photo-button" class="vehicle-photo-button" type="button" aria-controls="vehicle-photo-input" @click="newPhotoInput?.click()">{{ photos.length || newPhotos.length ? 'Tambah foto' : 'Pilih foto kendaraan' }}</button>
                            <input id="vehicle-photo-input" ref="newPhotoInput" class="vehicle-photo-input" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple :disabled="photos.length + newPhotos.length >= 20" @change="addPhotos">
                            <small class="form-help">Format JPG, PNG, atau WEBP. Maksimal 5 MB per foto.</small>
                            <div class="vehicle-photo-preview-frame">
                                <img v-if="photos.find((photo) => `existing:${photo.id}` === coverPhoto)?.url" class="vehicle-photo-preview" :src="photos.find((photo) => `existing:${photo.id}` === coverPhoto)?.url" alt="Pratinjau foto utama kendaraan">
                                <img v-else-if="newPhotos[Number(coverPhoto.slice(4))]?.url" class="vehicle-photo-preview" :src="newPhotos[Number(coverPhoto.slice(4))]?.url" alt="Pratinjau foto utama kendaraan">
                                <p v-else class="vehicle-photo-placeholder">Pratinjau foto akan ditampilkan di sini.</p>
                            </div>
                            <div class="vehicle-photo-gallery">
                                <article v-for="photo in photos" :key="photo.id" class="vehicle-photo-item">
                                    <img :src="photo.url" alt="Foto kendaraan">
                                    <div class="vehicle-photo-item-actions"><label class="vehicle-photo-cover-choice"><input v-model="coverPhoto" type="radio" name="cover" :value="`existing:${photo.id}`"> Foto utama</label><button class="vehicle-photo-remove" type="button" @click="removeExistingPhoto(photo)">Hapus</button></div>
                                </article>
                                <article v-for="(photo, index) in newPhotos" :key="`${photo.file.name}-${index}`" class="vehicle-photo-item">
                                    <img :src="photo.url" :alt="`Pratinjau ${photo.file.name}`">
                                    <div class="vehicle-photo-item-actions"><label class="vehicle-photo-cover-choice"><input v-model="coverPhoto" type="radio" name="cover" :value="`new:${index}`"> Foto utama</label><button class="vehicle-photo-remove" type="button" @click="removeNewPhoto(index)">Hapus</button></div>
                                </article>
                            </div>
                        </fieldset>
                    </section>
                    <section class="form-section vehicle-administration-card">
                        <header class="section-heading"><span class="section-number">07</span><div><h2 id="vehicle-administration-title">Administrasi mobil</h2></div></header>
                        <fieldset class="section-content vehicle-administration-content" :disabled="isSaving" aria-label="Administrasi mobil">
                            <h3 class="vehicle-administration-subheading">Data penjual</h3>
                            <div class="vehicle-administration-fields">
                                <label v-for="(label, name) in { nama_penjual: 'Nama penjual', nik_penjual: 'NIK penjual', no_hp_penjual: 'No. HP penjual', alamat_penjual: 'Alamat penjual' }" :key="name" class="form-group vehicle-administration-field">{{ label }}<input v-model="form[name]" :name="name" :required="name === 'nama_penjual'" :maxlength="name === 'alamat_penjual' ? 5000 : 255"></label>
                            </div>
                            <label class="form-group vehicle-administration-field">Nama broker<input v-model="form.nama_broker" name="nama_broker" maxlength="255"></label>
                        </fieldset>
                    </section>
                </div>
                <div class="leasing-layout-column leasing-layout-column--leasing">
                    <section class="form-section leasing-card-wrapper" aria-labelledby="leasing-card-title">
                        <header class="section-heading"><span class="section-number">05</span><div><h2 id="leasing-card-title">Pilihan leasing</h2></div><div class="leasing-vehicle-summary"><span>Kendaraan</span><strong id="leasing-vehicle-name">{{ leasingVehicleName }}</strong></div></header>
                        <fieldset class="section-content leasing-section-content" :disabled="isSaving" aria-label="Pilihan leasing">
                            <div class="leasing-table-actions"><button id="add-leasing-option" class="btn btn-secondary btn-small" type="button" :disabled="form.leasing_options.length >= 50" @click="addLeasing"><span aria-hidden="true">+</span> Tambah leasing</button></div>
                            <div class="leasing-table-responsive" role="region" aria-label="Tabel simulasi leasing" tabindex="0">
                                <table class="leasing-custom-table"><thead><tr><th scope="col" rowspan="2" class="th-leasing-name">Nama leasing</th><th scope="col" rowspan="2" class="th-disbursement">Nilai pencairan (Rp)</th><th scope="col" rowspan="2" class="th-sale-price">Harga jual (Rp)</th><th scope="col" rowspan="2" class="th-dp">DP (Rp)</th><th scope="colgroup" colspan="3" class="th-installment-group">Angsuran (Rp/bulan)</th></tr><tr><th scope="col" class="th-sub">3 tahun</th><th scope="col" class="th-sub">4 tahun</th><th scope="col" class="th-sub">5 tahun</th></tr></thead>
                                    <tbody><tr v-for="(option, index) in form.leasing_options" :key="index">
                                        <th scope="row" class="td-leasing-label"><label class="leasing-sr-only" :for="`leasing-${index}-name`">Nama leasing</label><input :id="`leasing-${index}-name`" v-model="option.name" class="leasing-input leasing-name-input" :name="`leasing_options[${index}][name]`" type="text" maxlength="100" placeholder="Nama leasing" autocomplete="off"><button class="icon-button remove-item leasing-remove-button" type="button" :aria-label="`Hapus leasing ${option.name || index + 1}`" title="Hapus leasing" @click="form.leasing_options.splice(index, 1)">×</button></th>
                                        <td v-for="[name, label] in [['disbursement','Nilai pencairan'],['selling_price','Harga jual'],['dp','DP'],['installment_3yr','Angsuran 3 tahun'],['installment_4yr','Angsuran 4 tahun'],['installment_5yr','Angsuran 5 tahun']]" :key="name"><label class="leasing-sr-only" :for="`leasing-${index}-${name}`">{{ option.name || `Leasing ${index + 1}` }} — {{ label }}</label><input :id="`leasing-${index}-${name}`" v-model="option[name]" class="leasing-input" :name="`leasing_options[${index}][${name}]`" type="number" min="0" step="0.01" :readonly="name === 'dp'" @input="['selling_price','disbursement'].includes(name) && updateLeasingDownPayment(option)"></td>
                                    </tr></tbody>
                                </table>
                            </div>
                        </fieldset>
                    </section>
                </div>
            </div>
            <div class="form-actions"><RouterLink class="btn btn-secondary" to="/dashboard">Batal</RouterLink><button class="btn btn-accent" type="submit" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : isEditing ? 'Simpan perubahan' : 'Simpan transaksi' }}</button></div>
        </form>
    </section>
</template>
