<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { apiRequest } from '../services/api.js';

const route = useRoute();
const transaction = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');
const canEdit = window.spaUser?.role !== 'marketing';

onMounted(async () => {
    try {
        const response = await apiRequest(`/api/spa/transactions/${route.params.transaction}`);
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
</script>

<template>
    <section class="showroom-content transaction-detail-page">
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat detail kendaraan...</div>
        <div v-else-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</div>
        <template v-else-if="transaction">
            <div class="page-heading receipt-page-heading">
                <div><p class="page-eyebrow">Dokumen transaksi</p><h1>{{ transaction.can_view_sensitive_data ? 'Nota Kendaraan' : transaction.plate_number }}</h1><span class="cell-secondary">{{ transaction.plate_number }} · {{ transaction.brand }} {{ transaction.model }}</span></div>
                <div class="receipt-page-actions">
                    <RouterLink v-if="canEdit" class="btn btn-accent" :to="`/transactions/${transaction.id}/edit`">Edit data</RouterLink>
                    <RouterLink class="btn btn-secondary receipt-back" to="/dashboard">← Kembali</RouterLink>
                </div>
            </div>
            <div class="vehicle-detail-layout">
                <section class="receipt">
                    <div class="receipt-header">
                        <table class="identity-table"><tbody>
                            <tr v-for="[label, key] in [['Plat','plate_number'],['Merek','brand'],['Model','model'],['Tahun','year'],['Transmisi','transmission'],['Warna','color'],['Nomor Rangka','chassis_number'],['Nomor Mesin','engine_number'],['Atas Nama','owner_name'],['Km','mileage_km']]" :key="key"><td>{{ label }}</td><td>:</td><td>{{ transaction[key] || '-' }}</td></tr>
                        </tbody></table>
                        <table class="meta-table"><tbody>
                            <tr><td>Pengurus</td><td>:</td><td>{{ transaction.manager_name || '-' }}</td></tr>
                            <tr><td>Tgl Beli</td><td>:</td><td>{{ transaction.buy_date }}</td></tr>
                            <tr><td>Tgl Jual</td><td>:</td><td>{{ transaction.sell_date || '-' }}</td></tr>
                        </tbody></table>
                    </div>
                    <div v-if="transaction.can_view_sensitive_data" class="receipt-body">
                        <div class="receipt-section-title receipt-section-total"><span>Modal beli</span><span>{{ currency(transaction.buy_price) }}</span></div>
                        <div class="receipt-grand-total"><span>Total Modal</span><span>{{ currency(transaction.total_capital) }}</span></div>
                        <div class="receipt-dark-section"><div class="receipt-section-title">Harga Jual & Pembayaran</div><table class="receipt-table"><tbody>
                            <tr v-for="[label, key] in [['Harga Jual','selling_price'],['DP','down_payment'],['Pelunasan','settlement_amount'],['Pencairan Leasing','leasing_disbursement'],['Sisa Pembayaran','remaining_payment']]" :key="key"><td class="description">{{ label }}</td><td class="amount">{{ currency(transaction[key]) }}</td></tr>
                        </tbody></table></div>
                        <div class="receipt-profit"><div class="receipt-section-title">Kalkulasi Laba</div><table class="receipt-table"><tbody>
                            <tr v-for="[label, key] in [['Laba jual','gross_profit'],['Ripan leasing','leasing_ripan'],['Ripan asuransi','insurance_ripan'],['Ripan booster','booster_ripan'],['Laba bersih','net_profit']]" :key="key"><td class="description">{{ label }}</td><td class="amount">{{ currency(transaction[key]) }}</td></tr>
                        </tbody></table></div>
                    </div>
                </section>
                <aside class="vehicle-leasing-panel" aria-labelledby="vehicle-leasing-title">
                    <img v-if="transaction.vehicle_photo_url" class="vehicle-photo-detail" :src="transaction.vehicle_photo_url" :alt="`Foto ${transaction.brand} ${transaction.model}`">
                    <div class="vehicle-leasing-heading"><div><p class="page-eyebrow">Informasi pelengkap kendaraan</p><h2 id="vehicle-leasing-title">Harga Leasing</h2></div><span class="leasing-currency-note">Nilai dalam Rupiah</span></div>
                    <div v-if="transaction.leasing_options?.length" class="leasing-table-wrap"><table class="leasing-table"><thead><tr><th>Leasing</th><th>Cair</th><th>Harga jual</th><th>DP</th><th>3 thn</th><th>4 thn</th><th>5 thn</th></tr></thead><tbody>
                        <tr v-for="(option, index) in transaction.leasing_options" :key="`${option.name}-${index}`"><th>{{ option.name }}</th><td>{{ currency(option.disbursement) }}</td><td>{{ currency(option.selling_price) }}</td><td>{{ currency(option.dp) }}</td><td>{{ currency(option.installment_3yr) }}</td><td>{{ currency(option.installment_4yr) }}</td><td>{{ currency(option.installment_5yr) }}</td></tr>
                    </tbody></table></div>
                    <p v-else class="leasing-empty-state">Belum ada detail harga leasing untuk kendaraan ini.</p>
                </aside>
            </div>
        </template>
    </section>
</template>
