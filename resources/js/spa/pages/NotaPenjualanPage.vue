<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const DEFAULT_DESIGN_CONFIG = Object.freeze({
    preset: 'modern-clean',
    headerLayout: 'split',
    logoUrl: '',
    logoVisible: false,
    logoSize: 'medium',
    sectionOrder: ['buyer', 'vehicle', 'payment', 'notes', 'signatures'],
    dataLayout: 'grid',
    sectionLabels: {
        buyer: 'Data Pembeli',
        vehicle: 'Identitas Kendaraan',
        payment: 'Rincian Pembayaran',
        notes: 'Catatan',
        signatures: 'Tanda Tangan',
    },
    fieldLabels: {
        buyerName: 'Nama',
        buyerNik: 'NIK',
        buyerPhone: 'No. HP',
        buyerAddress: 'Alamat',
        vehiclePlate: 'Plat Nomor',
        vehicleModel: 'Merek / Model',
        vehicleYear: 'Tahun',
        vehicleColor: 'Warna',
        vehicleChassis: 'Nomor Rangka',
        vehicleEngine: 'Nomor Mesin',
        sellingPrice: 'Harga Jual',
        downPayment: 'DP',
        settlement: 'Pelunasan',
        leasing: 'Pencairan Leasing',
        totalPaid: 'Total Dibayar',
        remainingPayment: 'Sisa Pembayaran',
    },
    customRows: [],
    fontFamily: 'sans',
    accentColor: '#047857',
    headerBackground: '#ecfdf5',
    borderStyle: 'thin',
    spacing: 'normal',
    paperSize: 'a4',
    signatureColumns: 2,
});
const DEFAULT_NOTA_SETTINGS = Object.freeze({
    showroomName: 'Cempaka Jaya Mobilindo',
    subtitle: 'Bukti transaksi penjualan kendaraan',
    showroomAddress: '',
    showroomPhone: '',
    invoicePrefix: 'NJM',
    footerNote: '',
    signatoryName: 'Pihak Showroom',
    signatoryTitle: '',
    showBuyerNik: true,
    showVehicleIdentifiers: true,
    showPaymentBreakdown: true,
    showPaymentStatus: true,
    showSignatures: true,
    accentTheme: 'emerald',
    paperSize: 'a4',
    design_config: DEFAULT_DESIGN_CONFIG,
});
const SETTINGS_STORAGE_KEY = 'spa-nota-settings';
const RECEIPT_SETTINGS_STORAGE_KEY = 'spa-nota-settings-by-receipt';
const DESIGN_SECTIONS = [
    { key: 'buyer', title: 'Data Pembeli' },
    { key: 'vehicle', title: 'Identitas Kendaraan' },
    { key: 'payment', title: 'Rincian Pembayaran' },
    { key: 'notes', title: 'Catatan / Syarat' },
    { key: 'signatures', title: 'Tanda Tangan' },
];
const DESIGN_FIELD_LABELS = [
    { key: 'buyerName', title: 'Nama pembeli' },
    { key: 'buyerNik', title: 'NIK pembeli' },
    { key: 'buyerPhone', title: 'Telepon pembeli' },
    { key: 'buyerAddress', title: 'Alamat pembeli' },
    { key: 'vehiclePlate', title: 'Plat nomor' },
    { key: 'vehicleModel', title: 'Merek / model' },
    { key: 'vehicleYear', title: 'Tahun kendaraan' },
    { key: 'vehicleColor', title: 'Warna kendaraan' },
    { key: 'vehicleChassis', title: 'Nomor rangka' },
    { key: 'vehicleEngine', title: 'Nomor mesin' },
    { key: 'sellingPrice', title: 'Harga jual' },
    { key: 'downPayment', title: 'DP' },
    { key: 'settlement', title: 'Pelunasan' },
    { key: 'leasing', title: 'Pencairan leasing' },
    { key: 'totalPaid', title: 'Total dibayar' },
    { key: 'remainingPayment', title: 'Sisa pembayaran' },
];
const DESIGN_PRESETS = {
    'modern-clean': {
        headerLayout: 'split',
        dataLayout: 'grid',
        fontFamily: 'sans',
        accentColor: '#047857',
        headerBackground: '#ecfdf5',
        borderStyle: 'thin',
        spacing: 'normal',
        paperSize: 'a4',
    },
    'classic-formal': {
        headerLayout: 'center',
        dataLayout: 'table',
        fontFamily: 'serif',
        accentColor: '#111827',
        headerBackground: '#f8fafc',
        borderStyle: 'double',
        spacing: 'relaxed',
        paperSize: 'a4',
    },
    'minimal-receipt': {
        headerLayout: 'center',
        dataLayout: 'table',
        fontFamily: 'mono',
        accentColor: '#334155',
        headerBackground: '#ffffff',
        borderStyle: 'thin',
        spacing: 'compact',
        paperSize: 'a5-landscape',
    },
};
const PRESET_OPTIONS = [
    { key: 'modern-clean', title: 'Modern Clean', description: 'Showroom bersih dan modern' },
    { key: 'classic-formal', title: 'Klasik Formal', description: 'Faktur resmi berbingkai' },
    { key: 'minimal-receipt', title: 'Minimalis Ringkas', description: 'Kwitansi padat ukuran A5' },
    { key: 'custom', title: 'Custom Bebas', description: 'Atur semua parameter desain' },
];
const COLOR_PRESETS = [
    { title: 'Emerald', value: '#047857', background: '#ecfdf5' },
    { title: 'Hitam', value: '#111827', background: '#f8fafc' },
    { title: 'Navy', value: '#1e3a8a', background: '#eff6ff' },
    { title: 'Slate', value: '#334155', background: '#f1f5f9' },
    { title: 'Marun', value: '#7f1d1d', background: '#fef2f2' },
];

const route = useRoute();
const router = useRouter();
const receipts = ref([]);
const selectedReceipt = ref(null);
const isListLoading = ref(true);
const isReceiptLoading = ref(false);
const errorMessage = ref('');
const settingsError = ref('');
const settingsMessage = ref('');
const isSettingsLoading = ref(true);
const isSettingsSaving = ref(false);
const isCustomizationOpen = ref(false);
const noteSettings = reactive({
    ...DEFAULT_NOTA_SETTINGS,
    design_config: defaultDesignConfig(),
});
const globalNotaSettings = ref({
    ...DEFAULT_NOTA_SETTINGS,
    design_config: defaultDesignConfig(),
});
const isApplyingNotaSettings = ref(false);
const isApplyingPreset = ref(false);
const isInitialized = ref(false);
let receiptRequestVersion = 0;
let settingsLoadPromise = Promise.resolve();

const selectedReceiptId = computed(() => String(route.query.nota ?? ''));
const designConfig = computed(() => noteSettings.design_config);
const receiptPageClass = computed(() => `receipt-page--${designConfig.value.paperSize}`);
const safeLogoUrl = computed(() => {
    const url = designConfig.value.logoUrl;

    return /^https?:\/\/\S+$/i.test(url)
        || /^data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2}$/i.test(url)
        ? url
        : '';
});
const logoSizeClass = computed(() => ({
    small: 'receipt-logo--small',
    medium: 'receipt-logo--medium',
    large: 'receipt-logo--large',
}[designConfig.value.logoSize]));
const orderedSections = computed(() => designConfig.value.sectionOrder
    .map((key) => DESIGN_SECTIONS.find((section) => section.key === key))
    .filter(Boolean));
const buyerFields = computed(() => {
    if (!selectedReceipt.value) {
        return [];
    }

    const buyer = selectedReceipt.value.buyer;
    const fields = [
        { key: 'buyerName', value: buyer.name },
        ...(noteSettings.showBuyerNik ? [{ key: 'buyerNik', value: buyer.nik || '—' }] : []),
        { key: 'buyerPhone', value: buyer.phone || '—' },
        { key: 'buyerAddress', value: buyer.address || '—' },
    ];

    return fields.map((field) => ({
        ...field,
        label: designConfig.value.fieldLabels[field.key],
    }));
});
const vehicleFields = computed(() => {
    if (!selectedReceipt.value) {
        return [];
    }

    const vehicle = selectedReceipt.value.vehicle;
    const fields = [
        { key: 'vehiclePlate', value: vehicle.plate_number },
        { key: 'vehicleModel', value: `${vehicle.brand} ${vehicle.model}`.trim() },
        { key: 'vehicleYear', value: vehicle.year },
        { key: 'vehicleColor', value: vehicle.color || '—' },
        ...(noteSettings.showVehicleIdentifiers
            ? [
                { key: 'vehicleChassis', value: vehicle.chassis_number || '—' },
                { key: 'vehicleEngine', value: vehicle.engine_number || '—' },
            ]
            : []),
    ];

    return fields.map((field) => ({
        ...field,
        label: designConfig.value.fieldLabels[field.key],
    }));
});
const paymentFields = computed(() => {
    if (!selectedReceipt.value) {
        return [];
    }

    const payment = selectedReceipt.value.payment;
    const fields = [
        { key: 'sellingPrice', value: payment.selling_price, emphasized: true },
    ];

    if (noteSettings.showPaymentBreakdown) {
        fields.push(
            { key: 'downPayment', value: payment.down_payment },
            { key: 'settlement', value: payment.settlement_amount },
            { key: 'leasing', value: payment.leasing_disbursement },
            { key: 'totalPaid', value: payment.total_paid, emphasized: true },
            { key: 'remainingPayment', value: payment.remaining_payment, emphasized: true },
        );
    }

    return fields.map((field) => ({
        ...field,
        label: designConfig.value.fieldLabels[field.key],
        displayValue: formatCurrency(field.value),
    }));
});
const formattedReceiptNumber = computed(() => {
    if (!selectedReceipt.value) {
        return '';
    }

    const year = String(selectedReceipt.value.transaction_date ?? '').slice(0, 4) || '0000';

    return `${noteSettings.invoicePrefix}/${year}/${String(selectedReceipt.value.id).padStart(6, '0')}`;
});

function formatCurrency(value) {
    return `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

function paymentStatusLabel(status) {
    return status === 'lunas' ? 'Lunas' : 'Belum Lunas';
}

function defaultDesignConfig() {
    return {
        ...DEFAULT_DESIGN_CONFIG,
        sectionOrder: [...DEFAULT_DESIGN_CONFIG.sectionOrder],
        sectionLabels: { ...DEFAULT_DESIGN_CONFIG.sectionLabels },
        fieldLabels: { ...DEFAULT_DESIGN_CONFIG.fieldLabels },
        customRows: [],
    };
}

function normalizeDesignConfig(candidate, base = defaultDesignConfig()) {
    const value = candidate && typeof candidate === 'object' && !Array.isArray(candidate)
        ? candidate
        : {};
    const config = {
        ...base,
        ...value,
        sectionLabels: {
            ...base.sectionLabels,
            ...(value.sectionLabels && typeof value.sectionLabels === 'object' ? value.sectionLabels : {}),
        },
        fieldLabels: {
            ...base.fieldLabels,
            ...(value.fieldLabels && typeof value.fieldLabels === 'object' ? value.fieldLabels : {}),
        },
        sectionOrder: Array.isArray(value.sectionOrder) ? [...value.sectionOrder] : [...base.sectionOrder],
        customRows: Array.isArray(value.customRows)
            ? value.customRows
                .filter((row) => row && typeof row === 'object')
                .slice(0, 20)
                .map((row) => ({
                    label: typeof row.label === 'string' ? row.label.slice(0, 80) : '',
                    value: typeof row.value === 'string' ? row.value.slice(0, 400) : '',
                }))
            : [...base.customRows],
    };
    const validSections = DESIGN_SECTIONS.map((section) => section.key);

    if (config.sectionOrder.length !== validSections.length
        || new Set(config.sectionOrder).size !== validSections.length
        || config.sectionOrder.some((key) => !validSections.includes(key))) {
        config.sectionOrder = [...DEFAULT_DESIGN_CONFIG.sectionOrder];
    }

    if (!['modern-clean', 'classic-formal', 'minimal-receipt', 'custom'].includes(config.preset)) {
        config.preset = DEFAULT_DESIGN_CONFIG.preset;
    }
    if (!['left', 'center', 'split'].includes(config.headerLayout)) {
        config.headerLayout = DEFAULT_DESIGN_CONFIG.headerLayout;
    }
    if (!['small', 'medium', 'large'].includes(config.logoSize)) {
        config.logoSize = DEFAULT_DESIGN_CONFIG.logoSize;
    }
    if (!['grid', 'table'].includes(config.dataLayout)) {
        config.dataLayout = DEFAULT_DESIGN_CONFIG.dataLayout;
    }
    if (!['sans', 'serif', 'mono'].includes(config.fontFamily)) {
        config.fontFamily = DEFAULT_DESIGN_CONFIG.fontFamily;
    }
    if (!['none', 'thin', 'double'].includes(config.borderStyle)) {
        config.borderStyle = DEFAULT_DESIGN_CONFIG.borderStyle;
    }
    if (!['compact', 'normal', 'relaxed'].includes(config.spacing)) {
        config.spacing = DEFAULT_DESIGN_CONFIG.spacing;
    }
    if (!['a4', 'a5-landscape', 'f4'].includes(config.paperSize)) {
        config.paperSize = DEFAULT_DESIGN_CONFIG.paperSize;
    }
    if (![2, 3].includes(config.signatureColumns)) {
        config.signatureColumns = DEFAULT_DESIGN_CONFIG.signatureColumns;
    }
    if (typeof config.logoUrl !== 'string' || config.logoUrl.length > 420000) {
        config.logoUrl = '';
    }
    if (typeof config.logoVisible !== 'boolean') {
        config.logoVisible = DEFAULT_DESIGN_CONFIG.logoVisible;
    }
    for (const field of ['accentColor', 'headerBackground']) {
        if (typeof config[field] !== 'string' || !/^#[0-9a-f]{6}$/i.test(config[field])) {
            config[field] = DEFAULT_DESIGN_CONFIG[field];
        }
    }

    for (const section of DESIGN_SECTIONS) {
        if (typeof config.sectionLabels[section.key] !== 'string') {
            config.sectionLabels[section.key] = DEFAULT_DESIGN_CONFIG.sectionLabels[section.key];
        }
    }
    for (const field of DESIGN_FIELD_LABELS) {
        if (typeof config.fieldLabels[field.key] !== 'string') {
            config.fieldLabels[field.key] = DEFAULT_DESIGN_CONFIG.fieldLabels[field.key];
        }
    }

    return config;
}

function normalizeNotaSettings(candidate, baseSettings = DEFAULT_NOTA_SETTINGS) {
    const value = candidate && typeof candidate === 'object' && !Array.isArray(candidate)
        ? candidate
        : {};
    const normalized = { ...baseSettings };

    for (const field of [
        'showroomName',
        'subtitle',
        'showroomAddress',
        'showroomPhone',
        'invoicePrefix',
        'footerNote',
        'signatoryName',
        'signatoryTitle',
    ]) {
        if (typeof value[field] === 'string') {
            normalized[field] = value[field];
        }
    }

    for (const field of [
        'showBuyerNik',
        'showVehicleIdentifiers',
        'showPaymentBreakdown',
        'showPaymentStatus',
        'showSignatures',
    ]) {
        if (typeof value[field] === 'boolean') {
            normalized[field] = value[field];
        }
    }

    if (['emerald', 'classic', 'navy'].includes(value.accentTheme)) {
        normalized.accentTheme = value.accentTheme;
    }
    if (['a4', 'a5-landscape'].includes(value.paperSize)) {
        normalized.paperSize = value.paperSize;
    }
    normalized.design_config = normalizeDesignConfig(
        value.design_config,
        baseSettings.design_config ? normalizeDesignConfig(baseSettings.design_config) : defaultDesignConfig(),
    );

    return normalized;
}

function applyNotaSettings(candidate) {
    isApplyingNotaSettings.value = true;
    Object.assign(noteSettings, normalizeNotaSettings(candidate));
    isApplyingNotaSettings.value = false;
}

function applyDesignPreset(preset) {
    if (preset === 'custom') {
        designConfig.value.preset = 'custom';

        return;
    }

    isApplyingPreset.value = true;
    Object.assign(designConfig.value, DESIGN_PRESETS[preset], { preset });
    isApplyingPreset.value = false;
}

function moveSection(sectionKey, direction) {
    const sectionOrder = [...designConfig.value.sectionOrder];
    const currentIndex = sectionOrder.indexOf(sectionKey);
    const targetIndex = currentIndex + direction;

    if (targetIndex < 0 || targetIndex >= sectionOrder.length) {
        return;
    }

    [sectionOrder[currentIndex], sectionOrder[targetIndex]] = [
        sectionOrder[targetIndex],
        sectionOrder[currentIndex],
    ];
    designConfig.value.sectionOrder = sectionOrder;
}

function addCustomRow() {
    if (designConfig.value.customRows.length < 20) {
        designConfig.value.customRows.push({ label: '', value: '' });
    }
}

function removeCustomRow(index) {
    designConfig.value.customRows.splice(index, 1);
}

async function uploadLogo(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 300 * 1024) {
        settingsError.value = 'Logo harus berupa PNG, JPEG, atau WebP dengan ukuran maksimal 300 KB.';

        return;
    }

    try {
        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(reader.error ?? new Error('File logo tidak dapat dibaca.'));
            reader.readAsDataURL(file);
        });

        if (typeof dataUrl !== 'string' || dataUrl.length > 420000) {
            throw new Error('Data logo melewati batas penyimpanan.');
        }

        designConfig.value.logoUrl = dataUrl;
        designConfig.value.logoVisible = true;
        settingsError.value = '';
    } catch (error) {
        settingsError.value = `Logo tidak dapat dimuat: ${error.message}`;
    }
}

function exportNotaTemplate() {
    const template = { ...noteSettings, design_config: normalizeDesignConfig(designConfig.value) };
    const fileUrl = URL.createObjectURL(new Blob([JSON.stringify(template, null, 2)], {
        type: 'application/json',
    }));
    const link = document.createElement('a');
    link.href = fileUrl;
    link.download = 'template-nota-showroom.json';
    link.click();
    URL.revokeObjectURL(fileUrl);
}

async function importNotaTemplate(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    try {
        const imported = JSON.parse(await file.text());
        const candidate = imported.design_config
            ? imported
            : { ...noteSettings, design_config: imported };

        applyNotaSettings(candidate);
        settingsError.value = '';
        settingsMessage.value = 'Template dimuat ke preview. Simpan desain ke server untuk menerapkannya sebagai default.';
    } catch (error) {
        settingsError.value = `Template tidak dapat diimpor: ${error.message}`;
        settingsMessage.value = '';
    }
}

function readLocalNotaSettings() {
    try {
        const storedSettings = localStorage.getItem(SETTINGS_STORAGE_KEY);

        if (!storedSettings) {
            return false;
        }

        const normalized = normalizeNotaSettings(JSON.parse(storedSettings));
        globalNotaSettings.value = normalized;
        applyNotaSettings(normalized);

        return true;
    } catch (error) {
        settingsError.value = `Pengaturan nota lokal tidak dapat dibaca: ${error.message}`;

        return false;
    }
}

function readReceiptSettings() {
    try {
        const storedSettings = localStorage.getItem(RECEIPT_SETTINGS_STORAGE_KEY);

        if (!storedSettings) {
            return {};
        }

        const parsedSettings = JSON.parse(storedSettings);

        if (!parsedSettings || typeof parsedSettings !== 'object' || Array.isArray(parsedSettings)) {
            throw new Error('Data kustomisasi per nota tidak valid.');
        }

        return parsedSettings;
    } catch (error) {
        settingsError.value = `Kustomisasi per nota tidak dapat dibaca: ${error.message}`;

        return {};
    }
}

function clearReceiptSettings(receiptId) {
    try {
        const storedSettings = readReceiptSettings();

        delete storedSettings[String(receiptId)];
        if (Object.keys(storedSettings).length === 0) {
            localStorage.removeItem(RECEIPT_SETTINGS_STORAGE_KEY);
        } else {
            localStorage.setItem(RECEIPT_SETTINGS_STORAGE_KEY, JSON.stringify(storedSettings));
        }

        return null;
    } catch (error) {
        return error.message;
    }
}

async function applyReceiptSettings(receiptId) {
    await settingsLoadPromise;
    applyNotaSettings(globalNotaSettings.value);

    const storedSettings = readReceiptSettings();
    const receiptSettings = storedSettings[String(receiptId)];

    if (receiptSettings) {
        applyNotaSettings({
            ...globalNotaSettings.value,
            ...normalizeNotaSettings(receiptSettings, globalNotaSettings.value),
        });
    }
}

function writeLocalNotaSettings(settings) {
    try {
        localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(settings));

        return null;
    } catch (error) {
        return error.message;
    }
}

async function loadNotaSettings() {
    const hasLocalSettings = readLocalNotaSettings();

    try {
        const response = await apiRequest('/api/spa/nota-settings');
        globalNotaSettings.value = normalizeNotaSettings(response.data);
        applyNotaSettings(globalNotaSettings.value);
        const localStorageError = writeLocalNotaSettings({ ...noteSettings });

        if (localStorageError) {
            settingsError.value = `Pengaturan dari server dimuat, tetapi cache browser gagal disimpan: ${localStorageError}`;
        }
    } catch (error) {
        if (hasLocalSettings) {
            settingsMessage.value = 'Server pengaturan tidak tersedia; menggunakan salinan lokal browser ini.';
            settingsError.value = `Pengaturan global tidak dapat dimuat: ${error.message}`;
        } else {
            settingsError.value = `Pengaturan global tidak dapat dimuat dan belum ada salinan lokal: ${error.message}`;
        }
    } finally {
        isSettingsLoading.value = false;
    }
}

async function saveNotaSettings(candidate = { ...noteSettings }, successMessage = 'Default nota berhasil disimpan.') {
    if (isSettingsSaving.value) {
        return;
    }

    const settingsToSave = normalizeNotaSettings(candidate);
    applyNotaSettings(settingsToSave);
    isSettingsSaving.value = true;
    settingsError.value = '';
    settingsMessage.value = '';

    try {
        const response = await apiRequest('/api/spa/nota-settings', {
            method: 'PUT',
            body: settingsToSave,
        });
        globalNotaSettings.value = normalizeNotaSettings(response.data);
        applyNotaSettings(globalNotaSettings.value);
        const localStorageError = writeLocalNotaSettings({ ...noteSettings });
        const receiptSettingsError = selectedReceipt.value
            ? clearReceiptSettings(selectedReceipt.value.id)
            : null;

        if (localStorageError || receiptSettingsError) {
            settingsError.value = [
                localStorageError ? `cache browser gagal disimpan: ${localStorageError}` : '',
                receiptSettingsError ? `kustomisasi nota lokal gagal dihapus: ${receiptSettingsError}` : '',
            ].filter(Boolean).join('; ');
        } else {
            settingsMessage.value = successMessage;
        }
    } catch (error) {
        globalNotaSettings.value = settingsToSave;
        applyNotaSettings(settingsToSave);
        const localStorageError = writeLocalNotaSettings(settingsToSave);
        const receiptSettingsError = selectedReceipt.value
            ? clearReceiptSettings(selectedReceipt.value.id)
            : null;

        if (localStorageError || receiptSettingsError) {
            const fallbackErrors = [
                localStorageError ? `cache browser gagal disimpan: ${localStorageError}` : '',
                receiptSettingsError ? `kustomisasi nota lokal gagal dihapus: ${receiptSettingsError}` : '',
            ].filter(Boolean).join('; ');
            settingsError.value = `Gagal menyimpan ke D1: ${error.message}. ${fallbackErrors}`;
        } else {
            settingsMessage.value = 'Perubahan disimpan hanya di browser ini karena server tidak dapat dijangkau.';
            settingsError.value = `Pengaturan global belum tersimpan di D1: ${error.message}`;
        }
    } finally {
        isSettingsSaving.value = false;
    }
}

function saveNotaSettingsForReceipt() {
    if (!selectedReceipt.value) {
        return;
    }

    try {
        const storedSettings = readReceiptSettings();

        storedSettings[String(selectedReceipt.value.id)] = { ...noteSettings };
        localStorage.setItem(RECEIPT_SETTINGS_STORAGE_KEY, JSON.stringify(storedSettings));
        settingsError.value = '';
        settingsMessage.value = 'Kustomisasi disimpan khusus untuk nota ini di browser ini.';
    } catch (error) {
        settingsError.value = `Kustomisasi nota tidak dapat disimpan: ${error.message}`;
        settingsMessage.value = '';
    }
}

async function useShowroomDefaultForReceipt() {
    if (!selectedReceipt.value) {
        return;
    }

    const storageError = clearReceiptSettings(selectedReceipt.value.id);
    applyNotaSettings(globalNotaSettings.value);

    if (storageError) {
        settingsError.value = `Default showroom diterapkan pada preview, tetapi kustomisasi lokal gagal dihapus: ${storageError}`;
        settingsMessage.value = '';
    } else {
        settingsError.value = '';
        settingsMessage.value = 'Nota ini sekarang menggunakan template default showroom.';
    }
}

async function resetNotaSettings() {
    await saveNotaSettings(
        { ...DEFAULT_NOTA_SETTINGS },
        'Template showroom dikembalikan ke default dan tersimpan.',
    );
}

async function loadReceipt(id) {
    const requestVersion = ++receiptRequestVersion;

    if (!id) {
        selectedReceipt.value = null;
        return;
    }

    isReceiptLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(`/api/spa/nota-penjualan/${encodeURIComponent(id)}`);
        if (requestVersion === receiptRequestVersion) {
            selectedReceipt.value = response.data;
            await applyReceiptSettings(response.data.id);
        }
    } catch (error) {
        if (requestVersion === receiptRequestVersion) {
            selectedReceipt.value = null;
            errorMessage.value = error.message;
        }
    } finally {
        if (requestVersion === receiptRequestVersion) {
            isReceiptLoading.value = false;
        }
    }
}

async function selectReceipt(receiptId) {
    await router.replace({ query: { ...route.query, nota: String(receiptId) } });
}

function printReceipt() {
    window.print();
}

onMounted(async () => {
    settingsLoadPromise = loadNotaSettings();
    isListLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest('/api/spa/nota-penjualan');
        receipts.value = response.data ?? [];
        const requestedReceipt = receipts.value.find(
            (receipt) => String(receipt.id) === selectedReceiptId.value,
        );
        const firstReceipt = receipts.value[0];
        const initialReceipt = requestedReceipt ?? firstReceipt;

        if (initialReceipt && String(initialReceipt.id) !== selectedReceiptId.value) {
            await router.replace({ query: { nota: String(initialReceipt.id) } });
        }

        isInitialized.value = true;
        if (initialReceipt) {
            await loadReceipt(initialReceipt.id);
        }
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isListLoading.value = false;
    }
});

watch(
    selectedReceiptId,
    async (receiptId) => {
        if (!isInitialized.value) {
            return;
        }

        await loadReceipt(receiptId);
    },
);

watch(
    designConfig,
    () => {
        if (!isApplyingNotaSettings.value
            && !isApplyingPreset.value
            && designConfig.value.preset !== 'custom') {
            designConfig.value.preset = 'custom';
        }
    },
    { deep: true, flush: 'sync' },
);
</script>

<template>
    <section
        class="receipt-page showroom-content w-full max-w-full min-w-0 space-y-4 overflow-x-hidden px-4 py-4 sm:px-6 lg:px-8"
        :class="receiptPageClass"
    >
        <header class="flex min-w-0 flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Laporan</p>
                <h1 class="text-xl! font-bold text-slate-900! sm:text-2xl!">Nota Penjualan</h1>
            </div>
            <div class="receipt-actions flex flex-wrap gap-2">
                <button
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    type="button"
                    :aria-expanded="isCustomizationOpen"
                    @click="isCustomizationOpen = !isCustomizationOpen"
                >
                    <svg aria-hidden="true" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                        <path d="m19.4 15 .1.1a1.8 1.8 0 0 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3 .9v.2a1.8 1.8 0 0 1-3.6 0v-.2a1.8 1.8 0 0 0-3-.9l-.1.1a1.8 1.8 0 0 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-.9-3H3.7a1.8 1.8 0 0 1 0-3.6h.2a1.8 1.8 0 0 0 .9-3l-.1-.1a1.8 1.8 0 0 1 2.5-2.5l.1.1a1.8 1.8 0 0 0 3-.9v-.2a1.8 1.8 0 0 1 3.6 0v.2a1.8 1.8 0 0 0 3 .9l.1-.1a1.8 1.8 0 0 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 .9 3h.2a1.8 1.8 0 0 1 0 3.6h-.2a1.8 1.8 0 0 0-.9 3Z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" />
                    </svg>
                    {{ isCustomizationOpen ? 'Tutup Kustomisasi' : 'Kustomisasi Nota' }}
                </button>
                <button class="btn btn-accent" type="button" :disabled="!selectedReceipt || isReceiptLoading" @click="printReceipt">
                    Cetak Nota
                </button>
            </div>
        </header>

        <div v-if="settingsMessage || settingsError" class="receipt-settings-notice space-y-2" aria-live="polite">
            <p v-if="settingsMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ settingsMessage }}
            </p>
            <p v-if="settingsError" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                {{ settingsError }}
            </p>
        </div>
        <p v-if="errorMessage" class="error-panel" role="alert">{{ errorMessage }}</p>
        <div v-if="isListLoading" class="spa-loading" role="status">
            <span class="spa-spinner" aria-hidden="true"></span>
            Memuat daftar nota...
        </div>
        <div
            v-else
            class="receipt-layout grid w-full min-w-0 grid-cols-1 gap-4"
            :class="isCustomizationOpen
                ? 'xl:grid-cols-[minmax(0,0.65fr)_minmax(19rem,0.9fr)_minmax(0,1.45fr)]'
                : 'xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]'"
        >
            <aside class="receipt-list min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="receipt-list-title">
                <header class="border-b border-slate-200 px-4 py-3">
                    <h2 id="receipt-list-title" class="text-base! font-semibold text-slate-900">Daftar Nota Penjualan</h2>
                </header>
                <div v-if="receipts.length" class="max-h-[70vh] min-w-0 space-y-2 overflow-y-auto p-3">
                    <button
                        v-for="receipt in receipts"
                        :key="receipt.id"
                        class="w-full min-w-0 rounded-xl border p-3 text-left transition-colors"
                        :class="String(receipt.id) === selectedReceiptId
                            ? 'border-emerald-600 bg-emerald-50'
                            : 'border-slate-200 bg-white hover:bg-slate-50'"
                        type="button"
                        :aria-current="String(receipt.id) === selectedReceiptId ? 'true' : undefined"
                        @click="selectReceipt(receipt.id)"
                    >
                        <span class="flex min-w-0 items-center justify-between gap-2">
                            <strong class="truncate text-sm font-semibold text-slate-900">{{ receipt.receipt_number }}</strong>
                            <span class="shrink-0 text-xs text-slate-500">{{ formatDate(receipt.transaction_date) }}</span>
                        </span>
                        <span class="mt-1 block truncate text-sm text-slate-700">{{ receipt.buyer.name }}</span>
                        <span class="mt-1 block truncate text-xs text-slate-500">
                            {{ receipt.vehicle.plate_number }} · {{ receipt.vehicle.brand }} {{ receipt.vehicle.model }}
                        </span>
                        <span
                            class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="receipt.payment.status === 'lunas'
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-amber-50 text-amber-700'"
                        >
                            {{ paymentStatusLabel(receipt.payment.status) }}
                        </span>
                    </button>
                </div>
                <p v-else class="px-4 py-8 text-center text-sm text-slate-500">
                    Belum ada transaksi penjualan untuk dibuatkan nota.
                </p>
            </aside>

            <aside
                v-if="isCustomizationOpen"
                class="receipt-customization min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:max-h-[calc(100vh-12rem)] xl:overflow-y-auto"
                aria-labelledby="customization-title"
            >
                <header class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Template showroom</p>
                        <h2 id="customization-title" class="text-base! font-semibold text-slate-900">Kustomisasi Nota</h2>
                    </div>
                    <button
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        type="button"
                        aria-label="Tutup kustomisasi"
                        @click="isCustomizationOpen = false"
                    >
                        <svg aria-hidden="true" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" stroke-width="1.8" />
                        </svg>
                    </button>
                </header>

                <div class="space-y-5 p-4">
                    <p v-if="isSettingsLoading" class="text-sm text-slate-500" role="status">
                        Memuat preferensi nota...
                    </p>

                    <fieldset class="space-y-3" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Identitas kop showroom</legend>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Nama Showroom</span>
                            <input v-model="noteSettings.showroomName" class="form-input w-full" maxlength="120" type="text">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Tagline / Subjudul</span>
                            <input v-model="noteSettings.subtitle" class="form-input w-full" maxlength="180" type="text">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Alamat Showroom</span>
                            <textarea v-model="noteSettings.showroomAddress" class="form-input w-full" maxlength="500" rows="2"></textarea>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Nomor Telepon / WhatsApp</span>
                            <input v-model="noteSettings.showroomPhone" class="form-input w-full" maxlength="50" type="text">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Prefix Nomor Nota</span>
                            <input v-model="noteSettings.invoicePrefix" class="form-input w-full" maxlength="12" type="text">
                        </label>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Visibilitas bagian</legend>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="noteSettings.showBuyerNik" class="size-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" type="checkbox">
                            Tampilkan NIK pembeli
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="noteSettings.showVehicleIdentifiers" class="size-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" type="checkbox">
                            Tampilkan nomor rangka dan mesin
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="noteSettings.showPaymentBreakdown" class="size-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" type="checkbox">
                            Tampilkan rincian skema pembayaran
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="noteSettings.showPaymentStatus" class="size-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" type="checkbox">
                            Tampilkan status pembayaran
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="noteSettings.showSignatures" class="size-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600" type="checkbox">
                            Tampilkan blok tanda tangan
                        </label>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Catatan dan penandatangan</legend>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Catatan kaki / Syarat & Ketentuan</span>
                            <textarea v-model="noteSettings.footerNote" class="form-input w-full" maxlength="1500" rows="3"></textarea>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Nama Penandatangan Showroom</span>
                            <input v-model="noteSettings.signatoryName" class="form-input w-full" maxlength="120" type="text">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Jabatan</span>
                            <input v-model="noteSettings.signatoryTitle" class="form-input w-full" maxlength="120" type="text">
                        </label>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Preset gaya desain</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="preset in PRESET_OPTIONS"
                                :key="preset.key"
                                class="rounded-xl border p-3 text-left transition"
                                :class="designConfig.preset === preset.key
                                    ? 'border-emerald-700 bg-emerald-50 ring-1 ring-emerald-700'
                                    : 'border-slate-200 bg-white hover:bg-slate-50'"
                                type="button"
                                :aria-pressed="designConfig.preset === preset.key"
                                @click="applyDesignPreset(preset.key)"
                            >
                                <span class="block text-xs font-semibold text-slate-900">{{ preset.title }}</span>
                                <span class="mt-1 block text-xs leading-4 text-slate-500">{{ preset.description }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Kop, logo, dan susunan blok</legend>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Posisi Kop Surat</span>
                            <select v-model="designConfig.headerLayout" class="form-input w-full">
                                <option value="left">Rata kiri</option>
                                <option value="center">Rata tengah</option>
                                <option value="split">Sejajar 2 kolom</option>
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">URL Logo Showroom</span>
                            <input v-model="designConfig.logoUrl" class="form-input w-full" type="url" placeholder="https://...">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Upload Logo (PNG/JPEG/WebP, maks. 300 KB)</span>
                            <input class="form-input w-full" type="file" accept="image/png,image/jpeg,image/webp" @change="uploadLogo">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Ukuran Logo</span>
                                <select v-model="designConfig.logoSize" class="form-input w-full">
                                    <option value="small">Kecil</option>
                                    <option value="medium">Sedang</option>
                                    <option value="large">Besar</option>
                                </select>
                            </label>
                            <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                                <input v-model="designConfig.logoVisible" class="size-4 rounded border-slate-300 text-emerald-700" type="checkbox">
                                Tampilkan logo
                            </label>
                        </div>
                        <button
                            class="text-xs font-semibold text-rose-700 hover:text-rose-900 disabled:opacity-50"
                            type="button"
                            :disabled="!designConfig.logoUrl"
                            @click="designConfig.logoUrl = ''; designConfig.logoVisible = false"
                        >
                            Hapus logo
                        </button>

                        <div class="space-y-2 border-t border-slate-100 pt-3">
                            <p class="text-xs font-medium text-slate-600">Urutan blok nota</p>
                            <div
                                v-for="(section, index) in orderedSections"
                                :key="section.key"
                                class="flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 px-2 py-2"
                            >
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-700">
                                    {{ index + 1 }}. {{ designConfig.sectionLabels[section.key] }}
                                </span>
                                <button
                                    class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 disabled:opacity-40"
                                    type="button"
                                    :disabled="index === 0"
                                    :aria-label="`Naikkan ${designConfig.sectionLabels[section.key]}`"
                                    @click="moveSection(section.key, -1)"
                                >
                                    ↑
                                </button>
                                <button
                                    class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 disabled:opacity-40"
                                    type="button"
                                    :disabled="index === orderedSections.length - 1"
                                    :aria-label="`Turunkan ${designConfig.sectionLabels[section.key]}`"
                                    @click="moveSection(section.key, 1)"
                                >
                                    ↓
                                </button>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Format data dan label kustom</legend>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Tampilan data</span>
                            <select v-model="designConfig.dataLayout" class="form-input w-full">
                                <option value="grid">Grid 2/3 kolom</option>
                                <option value="table">Tabel berbingkai</option>
                            </select>
                        </label>
                        <div class="space-y-2">
                            <label v-for="section in DESIGN_SECTIONS" :key="section.key" class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Judul {{ section.title }}</span>
                                <input v-model="designConfig.sectionLabels[section.key]" class="form-input w-full" maxlength="100">
                            </label>
                        </div>
                        <details class="rounded-xl border border-slate-200 p-3">
                            <summary class="cursor-pointer text-xs font-semibold text-slate-700">Ubah label field nota</summary>
                            <div class="mt-3 space-y-2">
                                <label v-for="field in DESIGN_FIELD_LABELS" :key="field.key" class="block space-y-1">
                                    <span class="text-xs text-slate-500">{{ field.title }}</span>
                                    <input v-model="designConfig.fieldLabels[field.key]" class="form-input w-full" maxlength="80">
                                </label>
                            </div>
                        </details>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Baris keterangan tambahan</legend>
                        <div
                            v-for="(row, index) in designConfig.customRows"
                            :key="index"
                            class="space-y-2 rounded-xl border border-slate-200 p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-600">Baris {{ index + 1 }}</span>
                                <button class="text-xs font-semibold text-rose-700" type="button" @click="removeCustomRow(index)">
                                    Hapus
                                </button>
                            </div>
                            <input v-model="row.label" class="form-input w-full" maxlength="80" placeholder="Label, mis. Kelengkapan">
                            <textarea v-model="row.value" class="form-input w-full" maxlength="400" rows="2" placeholder="Keterangan"></textarea>
                        </div>
                        <button
                            class="w-full rounded-xl border border-dashed border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            type="button"
                            :disabled="designConfig.customRows.length >= 20"
                            @click="addCustomRow"
                        >
                            + Tambah Baris Keterangan
                        </button>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Visual, warna, dan tipografi</legend>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Font Dokumen</span>
                            <select v-model="designConfig.fontFamily" class="form-input w-full">
                                <option value="sans">Sans-Serif Modern (Inter)</option>
                                <option value="serif">Serif Formal (Georgia / Times)</option>
                                <option value="mono">Monospace Faktur (Courier)</option>
                            </select>
                        </label>
                        <div class="space-y-2">
                            <span class="text-xs font-medium text-slate-600">Preset Warna</span>
                            <div class="grid grid-cols-5 gap-2">
                                <button
                                    v-for="color in COLOR_PRESETS"
                                    :key="color.title"
                                    class="flex min-w-0 flex-col items-center gap-1 rounded-lg border border-slate-200 p-2 text-xs text-slate-600 hover:bg-slate-50"
                                    type="button"
                                    :aria-label="`Gunakan warna ${color.title}`"
                                    @click="designConfig.accentColor = color.value; designConfig.headerBackground = color.background"
                                >
                                    <span class="size-5 rounded-full border border-black/10" :style="{ backgroundColor: color.value }"></span>
                                    <span class="truncate">{{ color.title }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Warna Utama</span>
                                <input v-model="designConfig.accentColor" class="h-10 w-full rounded-lg border border-slate-300 p-1" type="color">
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Latar Kop</span>
                                <input v-model="designConfig.headerBackground" class="h-10 w-full rounded-lg border border-slate-300 p-1" type="color">
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Bingkai Kertas</span>
                                <select v-model="designConfig.borderStyle" class="form-input w-full">
                                    <option value="none">Tanpa Bingkai</option>
                                    <option value="thin">Garis Tipis</option>
                                    <option value="double">Garis Ganda Klasik</option>
                                </select>
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-medium text-slate-600">Kepadatan Spasi</span>
                                <select v-model="designConfig.spacing" class="form-input w-full">
                                    <option value="compact">Rapat / Compact</option>
                                    <option value="normal">Normal</option>
                                    <option value="relaxed">Lega</option>
                                </select>
                            </label>
                        </div>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Ukuran Kertas Cetak</span>
                            <select v-model="designConfig.paperSize" class="form-input w-full">
                                <option value="a4">A4 Portrait</option>
                                <option value="a5-landscape">A5 Landscape / Setengah A4</option>
                                <option value="f4">F4 / Legal</option>
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Kolom Tanda Tangan</span>
                            <select v-model.number="designConfig.signatureColumns" class="form-input w-full">
                                <option :value="2">2 kolom: Pembeli & Showroom</option>
                                <option :value="3">3 kolom: Pembeli, Saksi/Sales, Pimpinan</option>
                            </select>
                        </label>
                    </fieldset>

                    <fieldset class="space-y-3 border-t border-slate-100 pt-4" :disabled="isSettingsLoading || isSettingsSaving">
                        <legend class="text-sm font-semibold text-slate-900">Impor dan ekspor template</legend>
                        <button
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            type="button"
                            @click="exportNotaTemplate"
                        >
                            Ekspor Template JSON
                        </button>
                        <label class="block space-y-1">
                            <span class="text-xs font-medium text-slate-600">Impor Template JSON</span>
                            <input class="form-input w-full" type="file" accept="application/json,.json" @change="importNotaTemplate">
                        </label>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-4">
                        <button
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="isSettingsSaving"
                            @click="resetNotaSettings"
                        >
                            Reset Default
                        </button>
                        <button
                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="!selectedReceipt || isReceiptLoading || isSettingsSaving"
                            @click="saveNotaSettingsForReceipt"
                        >
                            Simpan untuk Nota Ini
                        </button>
                        <button
                            class="btn btn-accent col-span-2 justify-center disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="isSettingsLoading || isSettingsSaving"
                            @click="saveNotaSettings()"
                        >
                            {{ isSettingsSaving ? 'Menyimpan...' : 'Simpan Desain ke Server' }}
                        </button>
                        <button
                            class="col-span-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            type="button"
                            :disabled="!selectedReceipt || isReceiptLoading || isSettingsSaving"
                            @click="useShowroomDefaultForReceipt"
                        >
                            Gunakan Default Showroom untuk Nota Ini
                        </button>
                    </div>
                    <p class="text-xs leading-5 text-slate-500">
                        Perubahan pada preview tampil langsung. Default showroom disinkronkan lewat D1; pengaturan khusus nota disimpan di browser ini.
                    </p>
                </div>
            </aside>

            <section class="receipt-preview min-w-0 rounded-2xl border border-slate-200 bg-slate-100 p-3 sm:p-5" aria-label="Preview nota penjualan">
                <div v-if="isReceiptLoading" class="spa-loading" role="status">
                    <span class="spa-spinner" aria-hidden="true"></span>
                    Memuat nota...
                </div>
                <article
                    v-else-if="selectedReceipt"
                    class="receipt-document mx-auto w-full max-w-3xl min-w-0 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-7"
                    :class="[
                        `receipt-font--${designConfig.fontFamily}`,
                        `receipt-border--${designConfig.borderStyle}`,
                        `receipt-spacing--${designConfig.spacing}`,
                        `receipt-page-paper--${designConfig.paperSize}`,
                    ]"
                    :style="{
                        '--receipt-accent': designConfig.accentColor,
                        '--receipt-header-background': designConfig.headerBackground,
                        '--receipt-signature-columns': designConfig.signatureColumns,
                    }"
                >
                    <header
                        class="receipt-document__header flex min-w-0 flex-wrap items-center justify-between gap-4 border-b-2 px-3 py-4"
                        :class="`receipt-header--${designConfig.headerLayout}`"
                    >
                        <div class="receipt-brand flex min-w-0 items-center gap-3">
                            <img
                                v-if="designConfig.logoVisible && safeLogoUrl"
                                :src="safeLogoUrl"
                                :alt="`Logo ${noteSettings.showroomName}`"
                                class="receipt-logo shrink-0 object-contain"
                                :class="logoSizeClass"
                            >
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Showroom Mobil</p>
                            <h2 class="receipt-accent-text mt-1 text-xl! font-bold">{{ noteSettings.showroomName }}</h2>
                            <p v-if="noteSettings.subtitle" class="mt-1 text-sm text-slate-600">{{ noteSettings.subtitle }}</p>
                            <p v-if="noteSettings.showroomAddress" class="mt-1 whitespace-pre-line text-xs leading-5 text-slate-600">
                                {{ noteSettings.showroomAddress }}
                            </p>
                            <p v-if="noteSettings.showroomPhone" class="text-xs text-slate-600">
                                Telp / WhatsApp: {{ noteSettings.showroomPhone }}
                            </p>
                            </div>
                        </div>
                        <div class="receipt-invoice-meta shrink-0 text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor Nota</p>
                            <p class="receipt-accent-text mt-1 text-sm font-bold">{{ formattedReceiptNumber }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ formatDate(selectedReceipt.transaction_date) }}</p>
                        </div>
                    </header>

                    <template v-for="sectionKey in designConfig.sectionOrder" :key="sectionKey">
                        <section v-if="sectionKey === 'buyer'" class="receipt-section" aria-labelledby="receipt-buyer-title">
                            <h3 id="receipt-buyer-title" class="receipt-accent-text text-sm! font-semibold uppercase tracking-wide">
                                {{ designConfig.sectionLabels.buyer }}
                            </h3>
                            <dl v-if="designConfig.dataLayout === 'grid'" class="receipt-data-grid grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-2">
                                <div v-for="field in buyerFields" :key="field.key" class="receipt-data-cell min-w-0 rounded-lg p-2">
                                    <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ field.value }}</dd>
                                </div>
                            </dl>
                            <table v-else class="receipt-data-table mt-3 w-full border-collapse text-sm">
                                <tbody>
                                    <tr v-for="field in buyerFields" :key="field.key">
                                        <th class="w-1/3 text-left font-medium text-slate-600">{{ field.label }}</th>
                                        <td class="break-words text-slate-900">{{ field.value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        <section v-else-if="sectionKey === 'vehicle'" class="receipt-section" aria-labelledby="receipt-vehicle-title">
                            <h3 id="receipt-vehicle-title" class="receipt-accent-text text-sm! font-semibold uppercase tracking-wide">
                                {{ designConfig.sectionLabels.vehicle }}
                            </h3>
                            <dl v-if="designConfig.dataLayout === 'grid'" class="receipt-data-grid grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3">
                                <div v-for="field in vehicleFields" :key="field.key" class="receipt-data-cell min-w-0 rounded-lg p-2">
                                    <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ field.value }}</dd>
                                </div>
                            </dl>
                            <table v-else class="receipt-data-table mt-3 w-full border-collapse text-sm">
                                <tbody>
                                    <tr v-for="field in vehicleFields" :key="field.key">
                                        <th class="w-1/3 text-left font-medium text-slate-600">{{ field.label }}</th>
                                        <td class="break-words text-slate-900">{{ field.value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        <section v-else-if="sectionKey === 'payment'" class="receipt-section" aria-labelledby="receipt-payment-title">
                            <h3 id="receipt-payment-title" class="receipt-accent-text text-sm! font-semibold uppercase tracking-wide">
                                {{ designConfig.sectionLabels.payment }}
                            </h3>
                            <table class="receipt-payment-table mt-3 w-full border-collapse text-sm">
                                <tbody>
                                    <tr v-for="field in paymentFields" :key="field.key">
                                        <th class="text-left text-slate-600" :class="{ 'receipt-total-row': field.emphasized }">{{ field.label }}</th>
                                        <td class="text-right tabular-nums text-slate-900" :class="{ 'receipt-total-row': field.emphasized }">{{ field.displayValue }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="designConfig.customRows.length" class="receipt-custom-rows mt-4">
                                <div v-for="(row, index) in designConfig.customRows" :key="index" class="flex flex-wrap justify-between gap-2 border-b border-dashed border-slate-200 py-2 text-sm">
                                    <strong class="text-slate-600">{{ row.label }}</strong>
                                    <span class="max-w-[70%] whitespace-pre-line text-right text-slate-900">{{ row.value }}</span>
                                </div>
                            </div>
                            <p
                                v-if="noteSettings.showPaymentStatus"
                                class="receipt-payment-status mt-3 inline-flex rounded-full px-3 py-1.5 text-xs font-semibold"
                                :class="selectedReceipt.payment.status === 'lunas'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'"
                            >
                                Status: {{ paymentStatusLabel(selectedReceipt.payment.status) }}
                            </p>
                        </section>

                        <section v-else-if="sectionKey === 'notes' && noteSettings.footerNote" class="receipt-section">
                            <h3 class="receipt-accent-text text-sm! font-semibold uppercase tracking-wide">
                                {{ designConfig.sectionLabels.notes }}
                            </h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ noteSettings.footerNote }}</p>
                        </section>

                        <footer
                            v-else-if="sectionKey === 'signatures' && noteSettings.showSignatures"
                            class="receipt-section receipt-signatures grid min-w-0 gap-6 text-center text-sm text-slate-700"
                        >
                            <div>
                                <p>Pembeli,</p>
                                <div class="receipt-signature-line"></div>
                                <p class="mt-2 font-medium">{{ selectedReceipt.buyer.name }}</p>
                            </div>
                            <div v-if="designConfig.signatureColumns === 3">
                                <p>Saksi / Sales,</p>
                                <div class="receipt-signature-line"></div>
                                <p class="mt-2 font-medium">________________</p>
                            </div>
                            <div>
                                <p>{{ designConfig.signatureColumns === 3 ? 'Pimpinan Showroom,' : `${noteSettings.showroomName},` }}</p>
                                <div class="receipt-signature-line"></div>
                                <p class="mt-2 font-medium">{{ noteSettings.signatoryName || 'Pihak Showroom' }}</p>
                                <p v-if="noteSettings.signatoryTitle" class="text-xs text-slate-500">{{ noteSettings.signatoryTitle }}</p>
                            </div>
                        </footer>
                    </template>
                </article>
                <div v-else class="flex min-h-60 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                    Pilih transaksi penjualan untuk melihat preview nota.
                </div>
            </section>
        </div>
    </section>
</template>

<style scoped>
.receipt-accent-text {
    color: var(--receipt-accent);
}

.receipt-document {
    font-family: Inter, "Plus Jakarta Sans", system-ui, sans-serif;
}

.receipt-font--serif {
    font-family: Georgia, "Times New Roman", serif;
}

.receipt-font--mono {
    font-family: "Courier New", Courier, monospace;
}

.receipt-document__header {
    background: var(--receipt-header-background);
    border-color: var(--receipt-accent);
}

.receipt-header--left {
    flex-direction: column;
    align-items: flex-start;
}

.receipt-header--left .receipt-invoice-meta {
    text-align: left;
}

.receipt-header--center {
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.receipt-header--center .receipt-brand {
    flex-direction: column;
}

.receipt-header--center .receipt-invoice-meta {
    text-align: center;
}

.receipt-logo--small {
    width: 2.5rem;
    height: 2.5rem;
}

.receipt-logo--medium {
    width: 4rem;
    height: 4rem;
}

.receipt-logo--large {
    width: 5.5rem;
    height: 5.5rem;
}

.receipt-section {
    margin-top: 1.25rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1rem;
    break-inside: avoid;
}

.receipt-data-cell {
    border: 1px solid #e2e8f0;
}

.receipt-data-table th,
.receipt-data-table td,
.receipt-payment-table th,
.receipt-payment-table td {
    border: 1px solid #cbd5e1;
    padding: 0.5rem 0.625rem;
    vertical-align: top;
}

.receipt-total-row {
    color: var(--receipt-accent) !important;
    font-weight: 700;
    background: color-mix(in srgb, var(--receipt-accent) 8%, white);
}

.receipt-signatures {
    grid-template-columns: repeat(var(--receipt-signature-columns), minmax(0, 1fr));
    break-inside: avoid;
}

.receipt-signature-line {
    height: 4rem;
    border-bottom: 1px solid #94a3b8;
}

.receipt-border--none {
    border: 0 !important;
}

.receipt-border--thin {
    border: 1px solid var(--receipt-accent);
}

.receipt-border--double {
    border: 4px double var(--receipt-accent);
}

.receipt-spacing--compact {
    padding: 0.75rem !important;
}

.receipt-spacing--compact .receipt-section {
    margin-top: 0.75rem;
    padding-top: 0.625rem;
}

.receipt-spacing--compact .receipt-data-table th,
.receipt-spacing--compact .receipt-data-table td,
.receipt-spacing--compact .receipt-payment-table th,
.receipt-spacing--compact .receipt-payment-table td {
    padding: 0.25rem 0.4rem;
}

.receipt-spacing--relaxed {
    padding: 2rem !important;
}

.receipt-spacing--relaxed .receipt-section {
    margin-top: 1.75rem;
    padding-top: 1.25rem;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    @page receipt-a5-landscape {
        size: A5 landscape;
        margin: 8mm;
    }

    @page receipt-f4 {
        size: 8.5in 13in portrait;
        margin: 0.4in;
    }

    .receipt-page--a5-landscape .receipt-document {
        page: receipt-a5-landscape;
    }

    .receipt-page--f4 .receipt-document {
        page: receipt-f4;
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

    .receipt-page {
        width: 100% !important;
        max-width: none !important;
        padding: 0 !important;
    }

    .receipt-page > header,
    .receipt-actions,
    .receipt-settings-notice,
    .receipt-list {
        display: none !important;
    }

    .receipt-customization {
        display: none !important;
    }

    .receipt-preview {
        display: block !important;
        border: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }

    .receipt-layout {
        display: block !important;
    }

    .receipt-document {
        width: 100% !important;
        max-width: none !important;
        box-shadow: none !important;
    }

    .receipt-page--a5-landscape .receipt-document {
        font-size: 12px !important;
    }

    .receipt-page--a5-landscape .receipt-document section {
        margin-top: 0.75rem !important;
    }
}
</style>
