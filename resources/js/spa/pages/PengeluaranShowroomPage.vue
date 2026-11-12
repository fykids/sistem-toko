<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { apiFormRequest, apiRequest } from '../services/api.js';

const categories = [
    'Operasional Kantor',
    'Listrik, Air & Internet',
    'Gaji & Uang Makan',
    'Perawatan & Kebersihan',
    'Pajak & Administrasi',
    'Pemasaran & Iklan',
    'Lainnya',
];
const paymentMethods = ['Tunai', 'Transfer Bank', 'QRIS / E-Wallet'];
const expenses = ref([]);
const summary = ref({
    total_amount: 0,
    this_month_amount: 0,
    transaction_count: 0,
    top_category: null,
    top_category_amount: 0,
});
const filters = reactive({
    search: '',
    category: '',
    payment_method: '',
    start_date: '',
    end_date: '',
});
const form = reactive({
    expense_date: todayDate(),
    category: '',
    title: '',
    amount: '',
    payment_method: 'Tunai',
    recipient_name: '',
    notes: '',
});
const amountDisplay = ref('');
const isLoading = ref(false);
const isSaving = ref(false);
const isFormOpen = ref(false);
const errorMessage = ref('');
const formErrorMessage = ref('');
const receiptFile = ref(null);
const receiptPreviewUrl = ref(null);
const existingReceiptUrl = ref(null);
const removeReceipt = ref(false);
const editingExpenseId = ref(null);
const previewExpense = ref(null);
const deletingExpenseId = ref(null);

const grandTotal = computed(() => expenses.value.reduce(
    (total, expense) => total + Number(expense.amount ?? 0),
    0,
));
const kpis = computed(() => [
    { label: 'Total Pengeluaran', value: formatCurrency(summary.value.total_amount), detail: 'Sesuai filter aktif' },
    { label: 'Pengeluaran Bulan Ini', value: formatCurrency(summary.value.this_month_amount), detail: 'Bulan berjalan' },
    { label: 'Jumlah Transaksi', value: new Intl.NumberFormat('id-ID').format(summary.value.transaction_count), detail: 'Catatan pengeluaran' },
    { label: 'Kategori Terbesar', value: summary.value.top_category ?? '—', detail: formatCurrency(summary.value.top_category_amount) },
]);

function todayDate() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function formatCurrency(value) {
    return `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value ?? 0))}`;
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

function categoryBadgeClass(category) {
    const classes = {
        'Operasional Kantor': 'bg-blue-50 text-blue-700',
        'Listrik, Air & Internet': 'bg-cyan-50 text-cyan-700',
        'Gaji & Uang Makan': 'bg-violet-50 text-violet-700',
        'Perawatan & Kebersihan': 'bg-emerald-50 text-emerald-700',
        'Pajak & Administrasi': 'bg-amber-50 text-amber-700',
        'Pemasaran & Iklan': 'bg-pink-50 text-pink-700',
        Lainnya: 'bg-slate-100 text-slate-700',
    };

    return classes[category] ?? classes.Lainnya;
}

async function loadExpenses() {
    isLoading.value = true;
    errorMessage.value = '';
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(filters)) {
        if (value.trim()) {
            query.set(key, value.trim());
        }
    }

    try {
        const queryString = query.toString();
        const response = await apiRequest(`/api/spa/pengeluaran${queryString ? `?${queryString}` : ''}`);
        expenses.value = response.data ?? [];
        summary.value = response.summary ?? summary.value;
    } catch (error) {
        errorMessage.value = error.message || 'Daftar pengeluaran tidak dapat dimuat.';
    } finally {
        isLoading.value = false;
    }
}

function applyQuickFilter(period) {
    if (period === 'all') {
        filters.search = '';
        filters.category = '';
        filters.payment_method = '';
        filters.start_date = '';
        filters.end_date = '';
    } else if (period === 'today') {
        filters.start_date = todayDate();
        filters.end_date = todayDate();
    } else {
        const now = new Date();
        filters.start_date = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
        filters.end_date = todayDate();
    }

    loadExpenses();
}

function updateAmount(value) {
    const digits = String(value).replace(/\D/g, '');
    form.amount = digits;
    amountDisplay.value = digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
}

function releasePreviewUrl() {
    if (receiptPreviewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(receiptPreviewUrl.value);
    }
    receiptPreviewUrl.value = null;
}

function openCreateForm() {
    editingExpenseId.value = null;
    Object.assign(form, {
        expense_date: todayDate(),
        category: '',
        title: '',
        amount: '',
        payment_method: 'Tunai',
        recipient_name: '',
        notes: '',
    });
    amountDisplay.value = '';
    existingReceiptUrl.value = null;
    receiptFile.value = null;
    removeReceipt.value = false;
    formErrorMessage.value = '';
    releasePreviewUrl();
    isFormOpen.value = true;
}

function openEditForm(expense) {
    editingExpenseId.value = expense.id;
    Object.assign(form, {
        expense_date: expense.expense_date,
        category: expense.category,
        title: expense.title,
        amount: String(expense.amount),
        payment_method: expense.payment_method,
        recipient_name: expense.recipient_name ?? '',
        notes: expense.notes ?? '',
    });
    amountDisplay.value = new Intl.NumberFormat('id-ID').format(Number(expense.amount));
    existingReceiptUrl.value = expense.receipt_url;
    receiptFile.value = null;
    removeReceipt.value = false;
    formErrorMessage.value = '';
    releasePreviewUrl();
    isFormOpen.value = true;
}

function closeForm() {
    isFormOpen.value = false;
    releasePreviewUrl();
}

function selectReceipt(event) {
    const file = event.target.files?.[0] ?? null;
    receiptFile.value = file;
    removeReceipt.value = false;
    releasePreviewUrl();
    receiptPreviewUrl.value = file ? URL.createObjectURL(file) : null;
}

async function saveExpense() {
    formErrorMessage.value = '';
    isSaving.value = true;
    const body = new FormData();

    for (const [key, value] of Object.entries(form)) {
        body.append(key, value);
    }
    body.append('remove_receipt', removeReceipt.value ? 'true' : 'false');
    if (receiptFile.value) {
        body.append('receipt', receiptFile.value);
    }

    try {
        const path = editingExpenseId.value
            ? `/api/spa/pengeluaran/${editingExpenseId.value}`
            : '/api/spa/pengeluaran';
        await apiFormRequest(path, {
            method: editingExpenseId.value ? 'PUT' : 'POST',
            body,
        });
        closeForm();
        await loadExpenses();
    } catch (error) {
        formErrorMessage.value = error.message || 'Pengeluaran tidak dapat disimpan.';
    } finally {
        isSaving.value = false;
    }
}

async function deleteExpense(expense) {
    if (!window.confirm(`Hapus catatan "${expense.title}"?`)) {
        return;
    }

    deletingExpenseId.value = expense.id;
    errorMessage.value = '';
    try {
        await apiRequest(`/api/spa/pengeluaran/${expense.id}`, { method: 'DELETE' });
        await loadExpenses();
    } catch (error) {
        errorMessage.value = error.message || 'Pengeluaran tidak dapat dihapus.';
    } finally {
        deletingExpenseId.value = null;
    }
}

function csvCell(value) {
    const text = String(value ?? '');
    const safeText = /^[=+@\-]/.test(text.trim()) ? `'${text}` : text;

    return `"${safeText.replaceAll('"', '""')}"`;
}

function exportCsv() {
    const rows = [
        ['Tanggal', 'Kategori', 'Judul', 'Nominal', 'Metode Pembayaran', 'Penerima', 'Keterangan'],
        ...expenses.value.map((expense) => [
            expense.expense_date,
            expense.category,
            expense.title,
            expense.amount,
            expense.payment_method,
            expense.recipient_name,
            expense.notes,
        ]),
    ];
    const csv = `\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\r\n')}`;
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `pengeluaran-showroom-${todayDate()}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function printReport() {
    window.print();
}

onMounted(loadExpenses);
onBeforeUnmount(releasePreviewUrl);
</script>

<template>
    <section class="min-w-0 w-full space-y-5 p-4 sm:p-6">
        <header class="flex min-w-0 flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">Pengeluaran Showroom</h1>
                <p class="mt-1 text-sm text-slate-500">Catat dan pantau biaya operasional showroom.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="exportCsv">Export CSV</button>
                <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="printReport">Cetak Rekap</button>
                <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" type="button" @click="openCreateForm">+ Catat Pengeluaran</button>
            </div>
        </header>

        <p v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ errorMessage }}</p>

        <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="kpi in kpis" :key="kpi.label" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ kpi.label }}</p>
                <p class="mt-2 truncate text-lg font-semibold text-slate-900">{{ kpi.value }}</p>
                <p class="mt-1 truncate text-xs text-slate-500">{{ kpi.detail }}</p>
            </article>
        </div>

        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" @submit.prevent="loadExpenses">
                <label class="min-w-0 text-xs font-medium text-slate-600 sm:col-span-2 xl:col-span-1">
                    Pencarian
                    <input v-model="filters.search" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900" placeholder="Judul, penerima, keterangan">
                </label>
                <label class="min-w-0 text-xs font-medium text-slate-600">
                    Kategori
                    <select v-model="filters.category" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                        <option value="">Semua kategori</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                </label>
                <label class="min-w-0 text-xs font-medium text-slate-600">
                    Metode pembayaran
                    <select v-model="filters.payment_method" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                        <option value="">Semua metode</option>
                        <option v-for="method in paymentMethods" :key="method" :value="method">{{ method }}</option>
                    </select>
                </label>
                <label class="min-w-0 text-xs font-medium text-slate-600">
                    Dari
                    <input v-model="filters.start_date" type="date" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                </label>
                <label class="min-w-0 text-xs font-medium text-slate-600">
                    Sampai
                    <input v-model="filters.end_date" type="date" class="mt-1 w-full min-w-0 rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal text-slate-900">
                </label>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-5">
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700" type="submit">Terapkan Filter</button>
                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" type="button" @click="applyQuickFilter('today')">Hari Ini</button>
                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" type="button" @click="applyQuickFilter('month')">Bulan Ini</button>
                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" type="button" @click="applyQuickFilter('all')">Semua</button>
                </div>
            </form>
        </section>

        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">Daftar Pengeluaran</h2>
                <span class="text-xs text-slate-500">{{ expenses.length }} transaksi</span>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Judul &amp; Penerima</th>
                            <th class="px-4 py-3">Metode Bayar</th>
                            <th class="px-4 py-3">Bukti</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="isLoading">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Memuat pengeluaran...</td>
                        </tr>
                        <tr v-else-if="expenses.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Belum ada pengeluaran yang sesuai filter.</td>
                        </tr>
                        <tr v-for="expense in expenses" v-else :key="expense.id" class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ formatDate(expense.expense_date) }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="categoryBadgeClass(expense.category)">{{ expense.category }}</span></td>
                            <td class="max-w-64 px-4 py-3">
                                <p class="font-medium text-slate-900">{{ expense.title }}</p>
                                <p v-if="expense.recipient_name" class="mt-1 text-xs text-slate-500">{{ expense.recipient_name }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ expense.payment_method }}</td>
                            <td class="px-4 py-3">
                                <button v-if="expense.receipt_url" class="text-sm font-medium text-emerald-700 hover:text-emerald-800" type="button" @click="previewExpense = expense">Lihat struk</button>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">{{ formatCurrency(expense.amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button class="font-medium text-sky-700 hover:text-sky-900" type="button" @click="openEditForm(expense)">Edit</button>
                                <button class="ml-3 font-medium text-rose-700 hover:text-rose-900 disabled:opacity-50" type="button" :disabled="deletingExpenseId === expense.id" @click="deleteExpense(expense)">{{ deletingExpenseId === expense.id ? 'Menghapus...' : 'Hapus' }}</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <th colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Grand Total</th>
                            <th class="px-4 py-3 text-right text-sm font-bold text-slate-900">{{ formatCurrency(grandTotal) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div v-if="isFormOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/40 p-3 sm:p-6" @click.self="closeForm">
            <section class="my-auto max-h-[95vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl sm:p-6" role="dialog" aria-modal="true" aria-labelledby="expense-form-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="expense-form-title" class="text-lg font-semibold text-slate-900">{{ editingExpenseId ? 'Edit Pengeluaran' : 'Catat Pengeluaran' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi informasi biaya operasional showroom.</p>
                    </div>
                    <button class="rounded-lg px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" type="button" aria-label="Tutup" @click="closeForm">×</button>
                </div>
                <p v-if="formErrorMessage" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert">{{ formErrorMessage }}</p>
                <form class="mt-5 space-y-3" @submit.prevent="saveExpense">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600">Tanggal Pengeluaran
                            <input v-model="form.expense_date" required type="date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                        </label>
                        <label class="text-xs font-medium text-slate-600">Kategori
                            <select v-model="form.category" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                                <option disabled value="">Pilih kategori</option>
                                <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                            </select>
                        </label>
                    </div>
                    <label class="block text-xs font-medium text-slate-600">Judul Pengeluaran
                        <input v-model="form.title" required maxlength="255" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="Contoh: Tagihan listrik showroom">
                    </label>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600">Nominal (Rp)
                            <input :value="amountDisplay" required inputmode="numeric" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="0" @input="updateAmount($event.target.value)">
                        </label>
                        <label class="text-xs font-medium text-slate-600">Metode Pembayaran
                            <select v-model="form.payment_method" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900">
                                <option v-for="method in paymentMethods" :key="method" :value="method">{{ method }}</option>
                            </select>
                        </label>
                    </div>
                    <label class="block text-xs font-medium text-slate-600">Penerima / Vendor
                        <input v-model="form.recipient_name" maxlength="255" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="Nama penerima, vendor, atau toko">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">Keterangan
                        <textarea v-model="form.notes" rows="3" maxlength="4000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" placeholder="Rincian tambahan (opsional)"></textarea>
                    </label>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Foto Bukti Struk / Kwitansi
                            <input accept="image/jpeg,image/png,image/webp" type="file" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold" @change="selectReceipt">
                        </label>
                        <div v-if="receiptPreviewUrl || (existingReceiptUrl && !removeReceipt)" class="mt-3 flex items-center gap-3">
                            <img :src="receiptPreviewUrl || existingReceiptUrl" alt="Preview bukti pengeluaran" class="h-20 w-28 rounded-lg border border-slate-200 object-cover">
                            <button v-if="existingReceiptUrl && !removeReceipt && !receiptFile" class="text-xs font-medium text-rose-700 hover:text-rose-900" type="button" @click="removeReceipt = true; existingReceiptUrl = null">Hapus bukti lama</button>
                            <button v-else-if="receiptFile" class="text-xs font-medium text-rose-700 hover:text-rose-900" type="button" @click="receiptFile = null; releasePreviewUrl()">Batalkan foto baru</button>
                        </div>
                        <p v-if="removeReceipt" class="mt-2 text-xs text-amber-700">Bukti lama akan dihapus saat perubahan disimpan.</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                        <button class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="closeForm">Batal</button>
                        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60" type="submit" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan Pengeluaran' }}</button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="previewExpense" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-label="Preview bukti pengeluaran" @click.self="previewExpense = null">
            <div class="max-h-full max-w-4xl overflow-auto rounded-2xl bg-white p-3 shadow-xl">
                <div class="mb-2 flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-slate-800">{{ previewExpense.title }}</p>
                    <button class="rounded-lg px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" type="button" aria-label="Tutup preview" @click="previewExpense = null">×</button>
                </div>
                <img :src="previewExpense.receipt_url" :alt="`Bukti ${previewExpense.title}`" class="max-h-[80vh] max-w-full rounded-lg object-contain">
            </div>
        </div>
    </section>
</template>
