<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiRequest, resolvePhotoUrl } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const filters = ref({ q: '', transmission: '', year_from: '', year_to: '' });
const isLoading = ref(true);
const errorMessage = ref('');
const unavailablePhotos = ref([]);
const vehicles = computed(() => payload.value?.data ?? []);
const pagination = computed(() => payload.value?.meta ?? null);
const vehicle = computed(() => route.params.transaction
    ? payload.value?.data ?? null
    : null);
const leasingOptions = computed(() => vehicle.value?.leasing_options ?? []);

function formatCurrency(value) {
    return Number(value) > 0
        ? `Rp ${Number(value).toLocaleString('id-ID')}`
        : 'Hubungi marketing';
}

function formatAmount(value) {
    return value === undefined || value === null || value === ''
        ? '—'
        : `Rp ${Number(value).toLocaleString('id-ID')}`;
}

function photoUnavailable(url) {
    const resolvedUrl = resolvePhotoUrl(url);

    return !resolvedUrl || unavailablePhotos.value.includes(resolvedUrl);
}

function markPhotoUnavailable(url) {
    const resolvedUrl = resolvePhotoUrl(url);

    if (resolvedUrl && !unavailablePhotos.value.includes(resolvedUrl)) {
        unavailablePhotos.value.push(resolvedUrl);
    }
}

function applyFilters() {
    const query = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== ''),
    );
    router.push({ name: 'marketing-stock', query });
}

function goToPage(page) {
    router.push({ query: { ...route.query, page: String(page) } });
}

watch(
    () => route.fullPath,
    async (_path, _previousPath, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        isLoading.value = true;
        errorMessage.value = '';
        unavailablePhotos.value = [];
        filters.value = {
            q: route.query.q ?? '',
            transmission: route.query.transmission ?? '',
            year_from: route.query.year_from ?? '',
            year_to: route.query.year_to ?? '',
        };
        const detailId = route.params.transaction;
        const query = new URLSearchParams(route.query);
        const endpoint = detailId
            ? `/api/spa/marketing/vehicles/${encodeURIComponent(detailId)}`
            : `/api/spa/marketing/vehicles?${query}`;

        try {
            payload.value = await apiRequest(endpoint, { signal: controller.signal });
        } catch (error) {
            if (error.name !== 'AbortError') errorMessage.value = error.message;
        } finally {
            if (!controller.signal.aborted) isLoading.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <section class="spa-dashboard" :aria-labelledby="vehicle ? 'vehicle-title' : 'catalog-title'">
        <template v-if="vehicle">
            <RouterLink class="spa-catalog-back" :to="{ name: 'marketing-stock' }">← Kembali ke katalog</RouterLink>
            <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat detail kendaraan...</div>
            <div v-else-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</div>
            <article v-else class="spa-vehicle-detail">
                <div class="spa-vehicle-hero">
                    <img
                        v-if="vehicle.vehicle_photo_url && !photoUnavailable(vehicle.vehicle_photo_url)"
                        :src="resolvePhotoUrl(vehicle.vehicle_photo_url)"
                        :alt="`${vehicle.brand} ${vehicle.model}`"
                        @error="markPhotoUnavailable(vehicle.vehicle_photo_url)"
                    >
                    <div v-else class="spa-vehicle-no-photo">
                        {{ vehicle.vehicle_photo_url ? 'Foto tidak tersedia' : 'Foto kendaraan belum tersedia' }}
                    </div>
                    <div class="spa-vehicle-info">
                        <p class="spa-eyebrow">{{ vehicle.plate_number }}</p>
                        <h1 id="vehicle-title">{{ vehicle.brand }} {{ vehicle.model }}</h1>
                        <p>{{ vehicle.year }} · {{ vehicle.transmission }} · Pajak {{ vehicle.tax_status.label }}</p>
                        <div class="spa-price-card"><span>Harga penawaran / buka</span><strong>{{ formatCurrency(vehicle.opening_price) }}</strong></div>
                        <dl class="spa-spec-list">
                            <div><dt>Warna</dt><dd>{{ vehicle.color || '—' }}</dd></div>
                            <div><dt>Kilometer</dt><dd>{{ vehicle.mileage_km ? Number(vehicle.mileage_km).toLocaleString('id-ID') : '—' }}</dd></div>
                            <div><dt>Nomor rangka</dt><dd>{{ vehicle.chassis_number || '—' }}</dd></div>
                            <div><dt>Nomor mesin</dt><dd>{{ vehicle.engine_number || '—' }}</dd></div>
                            <div><dt>Nama pemilik</dt><dd>{{ vehicle.owner_name || '—' }}</dd></div>
                            <div><dt>Tanggal masuk</dt><dd>{{ vehicle.buy_date || '—' }}</dd></div>
                        </dl>
                    </div>
                </div>
                <section class="spa-leasing" aria-labelledby="leasing-title">
                    <h2 id="leasing-title">Simulasi angsuran leasing</h2>
                    <p class="spa-muted">Nilai berikut merupakan referensi berdasarkan data kendaraan.</p>
                    <div v-if="leasingOptions.length" class="spa-table-scroll">
                        <table>
                            <thead><tr><th>Leasing</th><th>Pencairan</th><th>Harga jual</th><th>DP</th><th>3 tahun</th><th>4 tahun</th><th>5 tahun</th></tr></thead>
                            <tbody>
                                <tr v-for="(option, index) in leasingOptions" :key="`${option.name}-${index}`">
                                    <td>{{ option.name || '—' }}</td>
                                    <td>{{ formatAmount(option.disbursement) }}</td>
                                    <td>{{ formatAmount(option.selling_price) }}</td>
                                    <td>{{ formatAmount(option.dp) }}</td>
                                    <td>{{ formatAmount(option.installment_3yr) }}</td>
                                    <td>{{ formatAmount(option.installment_4yr) }}</td>
                                    <td>{{ formatAmount(option.installment_5yr) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="spa-empty">Belum ada simulasi leasing untuk kendaraan ini.</p>
                </section>
            </article>
        </template>

        <template v-else>
            <div class="spa-heading">
                <div><p class="spa-eyebrow">Stok tersedia</p><h1 id="catalog-title">Katalog mobil</h1><p>Informasi kendaraan untuk referensi penawaran.</p></div>
                <span>{{ payload?.summary?.available_count ?? '—' }} unit tersedia</span>
            </div>

            <form class="spa-filters spa-catalog-filters" @submit.prevent="applyFilters">
                <label class="spa-search"><span>Cari plat, merek, atau model</span><input v-model="filters.q" type="search" :disabled="isLoading"></label>
                <label><span>Transmisi</span><select v-model="filters.transmission" :disabled="isLoading"><option value="">Semua</option><option value="manual">Manual</option><option value="otomatis">Otomatis</option></select></label>
                <label><span>Tahun dari</span><input v-model="filters.year_from" type="number" min="1900" max="2100" :disabled="isLoading"></label>
                <label><span>Tahun sampai</span><input v-model="filters.year_to" type="number" min="1900" max="2100" :disabled="isLoading"></label>
                <button type="submit" :disabled="isLoading">Cari stok</button>
                <RouterLink class="spa-secondary-action" :to="{ name: 'marketing-stock' }">Reset filter</RouterLink>
            </form>

            <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat katalog...</div>
            <div v-else-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</div>
            <div v-else-if="vehicles.length" class="spa-catalog-grid">
                <RouterLink v-for="item in vehicles" :key="item.id" class="spa-vehicle-card" :to="{ name: 'marketing-vehicle', params: { transaction: item.id } }">
                    <img
                        v-if="item.vehicle_photo_url && !photoUnavailable(item.vehicle_photo_url)"
                        :src="resolvePhotoUrl(item.vehicle_photo_url)"
                        :alt="`${item.brand} ${item.model}`"
                        loading="lazy"
                        @error="markPhotoUnavailable(item.vehicle_photo_url)"
                    >
                    <div v-else class="spa-vehicle-no-photo">
                        {{ item.vehicle_photo_url ? 'Foto tidak tersedia' : 'Foto kendaraan belum tersedia' }}
                    </div>
                    <div class="spa-vehicle-card-content">
                        <div class="spa-vehicle-title"><div><h2>{{ item.brand }} {{ item.model }}</h2><p>{{ item.plate_number }} · {{ item.year }}</p></div><span>Tersedia</span></div>
                        <div class="spa-vehicle-tags"><span>{{ item.transmission }}</span><span>{{ item.color }}</span><span>Pajak {{ item.tax_status.label }}</span></div>
                        <div class="spa-vehicle-price"><span>Harga penawaran / buka</span><strong>{{ formatCurrency(item.opening_price) }}</strong></div>
                        <span class="spa-vehicle-cta">Lihat spesifikasi &amp; simulasi leasing →</span>
                    </div>
                </RouterLink>
            </div>
            <div v-else class="spa-empty-card"><h2>Stok tidak ditemukan</h2><p>Coba ubah kata pencarian atau filter kendaraan.</p></div>
            <footer v-if="pagination && pagination.last_page > 1" class="spa-pagination">
                <button type="button" :disabled="isLoading || pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">Sebelumnya</button>
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <button type="button" :disabled="isLoading || pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">Berikutnya</button>
            </footer>
        </template>
    </section>
</template>
