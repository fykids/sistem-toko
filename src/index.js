import { compare, hash } from 'bcryptjs';
import { Hono } from 'hono';
import { cors } from 'hono/cors';

const SESSION_LIFETIME_SECONDS = 60 * 60 * 12;
const DUMMY_PASSWORD_HASH = '$2b$12$07QHaqxT19Xg.NqKtHerHOdLg9gt7n657GqjbJCWl1k9susmOEyxW';
const SHOWROOM_EXPENSE_CATEGORIES = [
    'Operasional Kantor',
    'Listrik, Air & Internet',
    'Gaji & Uang Makan',
    'Perawatan & Kebersihan',
    'Pajak & Administrasi',
    'Pemasaran & Iklan',
    'Lainnya',
];
const SHOWROOM_EXPENSE_PAYMENT_METHODS = [
    'Tunai',
    'Transfer Bank',
    'QRIS / E-Wallet',
];
const SHOWROOM_EXPENSE_RECEIPT_TYPES = new Map([
    ['image/jpeg', 'jpg'],
    ['image/png', 'png'],
    ['image/webp', 'webp'],
]);
const SHOWROOM_EXPENSE_RECEIPT_MAX_BYTES = 5 * 1024 * 1024;
const TRANSACTION_PHOTO_FIELDS = [
    'foto_kendaraan[]',
    'photos[]',
    'photos',
    'vehicle_photos[]',
    'vehicle_photos',
    'photo',
];
const TRANSACTION_PHOTO_FIELD_SET = new Set(TRANSACTION_PHOTO_FIELDS);
const DEFAULT_NOTA_DESIGN_CONFIG = Object.freeze({
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
    design_config: DEFAULT_NOTA_DESIGN_CONFIG,
});

const app = new Hono();

function isAllowedOrigin(origin) {
    if (!origin) {
        return false;
    }

    let url;

    try {
        url = new URL(origin);
    } catch {
        return false;
    }

    if (url.protocol === 'https:'
        && (url.hostname === 'sistem-toko.pages.dev'
            || /^[a-z0-9-]+\.sistem-toko\.pages\.dev$/i.test(url.hostname))) {
        return true;
    }

    return url.protocol === 'http:'
        && ['localhost:5173', 'localhost:8000'].includes(url.host);
}

app.use('/api/*', async (context, next) => {
    if (context.req.path.startsWith('/api/vehicle-photos/')) {
        return cors({
            origin: '*',
            allowMethods: ['GET', 'OPTIONS'],
            maxAge: 86400,
        })(context, next);
    }

    const corsMiddleware = cors({
        origin: (origin) => isAllowedOrigin(origin) ? origin : '',
        allowHeaders: [
            'Accept',
            'Authorization',
            'Content-Type',
            'X-Requested-With',
            'X-CSRF-TOKEN',
        ],
        allowMethods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        credentials: true,
        maxAge: 86400,
    });

    return corsMiddleware(context, next);
});

function toHex(bytes) {
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

async function hashToken(token) {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(token));

    return toHex(new Uint8Array(digest));
}

function createToken() {
    const bytes = crypto.getRandomValues(new Uint8Array(32));

    return btoa(String.fromCharCode(...bytes))
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replace(/=+$/, '');
}

async function requireSession(context, next) {
    const authorization = context.req.header('Authorization') ?? '';
    const token = authorization.startsWith('Bearer ')
        ? authorization.slice('Bearer '.length).trim()
        : '';

    if (!token) {
        return context.json({ message: 'Sesi tidak valid atau telah berakhir.' }, 401);
    }

    const session = await context.env.DB.prepare(
        `SELECT users.id, users.name, users.username, users.role
         FROM api_sessions
         INNER JOIN users ON users.id = api_sessions.user_id
         WHERE api_sessions.token_hash = ? AND api_sessions.expires_at > ?
         LIMIT 1`,
    ).bind(await hashToken(token), Math.floor(Date.now() / 1000)).first();

    if (!session) {
        return context.json({ message: 'Sesi tidak valid atau telah berakhir.' }, 401);
    }

    context.set('user', session);

    return next();
}

async function createSession(context, user) {
    const token = createToken();
    const now = Math.floor(Date.now() / 1000);
    const expiresAt = now + SESSION_LIFETIME_SECONDS;

    await context.env.DB.prepare(
        'DELETE FROM api_sessions WHERE expires_at <= ?',
    ).bind(now).run();

    await context.env.DB.prepare(
        'INSERT INTO api_sessions (token_hash, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)',
    ).bind(await hashToken(token), user.id, expiresAt, now).run();

    const safeUser = {
        id: user.id,
        name: user.name,
        username: user.username,
        role: user.role,
    };

    return {
        token,
        expires_at: expiresAt,
        user: safeUser,
        redirect: user.role === 'marketing' ? '/app/marketing' : '/app/dashboard',
    };
}

function validationError(message, fields) {
    return {
        message,
        errors: Object.fromEntries(fields.map((field) => [field, [message]])),
    };
}

async function requestJson(context) {
    try {
        return await context.req.json();
    } catch {
        return null;
    }
}

function isMaster(context) {
    return context.get('user').role === 'master';
}

function validateAccountFields(account, { passwordRequired = false } = {}) {
    const errors = {};
    const name = typeof account?.name === 'string' ? account.name.trim() : '';
    const username = typeof account?.username === 'string' ? account.username.trim() : '';
    const role = account?.role;
    const password = typeof account?.password === 'string' ? account.password : '';

    if (!name || name.length > 255) {
        errors.name = ['Nama wajib diisi dan maksimal 255 karakter.'];
    }
    if (!username || username.length > 255 || !/^[A-Za-z0-9._-]+$/.test(username)) {
        errors.username = ['Username wajib diisi dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.'];
    }
    if (!['admin', 'marketing'].includes(role)) {
        errors.role = ['Peran akun harus Admin atau Marketing.'];
    }
    if ((passwordRequired || password) && (password.length < 8 || password.length > 1024)) {
        errors.password = ['Kata sandi harus memiliki panjang 8 hingga 1024 karakter.'];
    }

    return { errors, name, username, role, password };
}

async function getUsers(context) {
    if (!isMaster(context)) {
        return context.json({ message: 'Hanya akun Master yang dapat mengelola pengguna.' }, 403);
    }

    const search = (context.req.query('q') ?? '').trim();
    const pageValue = context.req.query('page') ?? '1';
    const page = Number(pageValue);

    if (search.length > 255 || !/^\d+$/.test(pageValue) || !Number.isSafeInteger(page) || page < 1) {
        return context.json({
            message: 'Filter pengguna tidak valid.',
            errors: {
                ...(search.length > 255 ? { q: ['Pencarian maksimal 255 karakter.'] } : {}),
                ...(!/^\d+$/.test(pageValue) || !Number.isSafeInteger(page) || page < 1
                    ? { page: ['Halaman harus berupa bilangan bulat minimal 1.'] }
                    : {}),
            },
        }, 422);
    }

    const conditions = [];
    const parameters = [];

    if (search) {
        conditions.push('(name LIKE ? OR username LIKE ? OR role LIKE ?)');
        const searchPattern = `%${search}%`;
        parameters.push(searchPattern, searchPattern, searchPattern);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const offset = (page - 1) * 10;

    try {
        const [usersResult, countResult, masterCountResult] = await Promise.all([
            context.env.DB.prepare(
                `SELECT id, name, username, role, created_at FROM users ${whereClause}
                 ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`,
            ).bind(...parameters, 10, offset).all(),
            context.env.DB.prepare(
                `SELECT COUNT(*) AS total FROM users ${whereClause}`,
            ).bind(...parameters).first(),
            context.env.DB.prepare(
                "SELECT COUNT(*) AS total FROM users WHERE role = 'master'",
            ).first(),
        ]);
        const users = usersResult.results ?? [];
        const total = Number(countResult?.total ?? 0);
        const lastPage = Math.max(1, Math.ceil(total / 10));

        return context.json({
            data: users,
            meta: {
                current_page: page,
                from: total === 0 ? null : offset + 1,
                last_page: lastPage,
                per_page: 10,
                to: total === 0 ? null : offset + users.length,
                total,
            },
            summary: { master_count: Number(masterCountResult?.total ?? 0) },
        });
    } catch (error) {
        console.error('API users query failed.', error);

        return context.json({ message: 'Daftar pengguna tidak dapat dimuat saat ini.' }, 500);
    }
}

async function createUser(context) {
    if (!isMaster(context)) {
        return context.json({ message: 'Hanya akun Master yang dapat mengelola pengguna.' }, 403);
    }

    const account = await requestJson(context);

    if (!account || typeof account !== 'object' || Array.isArray(account)) {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }

    const validated = validateAccountFields(account, { passwordRequired: true });

    if (Object.keys(validated.errors).length > 0) {
        return context.json({ message: 'Data akun tidak valid.', errors: validated.errors }, 422);
    }

    try {
        const existingUser = await context.env.DB.prepare(
            'SELECT id FROM users WHERE username = ? LIMIT 1',
        ).bind(validated.username).first();

        if (existingUser) {
            return context.json({
                message: 'Username sudah digunakan.',
                errors: { username: ['Username sudah digunakan.'] },
            }, 422);
        }

        const passwordHash = await hash(validated.password, 12);
        const createdAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const user = await context.env.DB.prepare(
            `INSERT INTO users (name, username, password, role, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             RETURNING id, name, username, role, created_at`,
        ).bind(validated.name, validated.username, passwordHash, validated.role, createdAt, createdAt).first();

        return context.json({ message: 'Akun berhasil dibuat.', data: user }, 201);
    } catch (error) {
        console.error('API user creation failed.', error);

        return context.json({ message: 'Akun tidak dapat dibuat saat ini.' }, 500);
    }
}

async function updateUser(context) {
    if (!isMaster(context)) {
        return context.json({ message: 'Hanya akun Master yang dapat mengelola pengguna.' }, 403);
    }

    const id = Number(context.req.param('id'));

    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'ID pengguna tidak valid.' }, 422);
    }

    const account = await requestJson(context);

    if (!account || typeof account !== 'object' || Array.isArray(account)) {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }

    const validated = validateAccountFields(account);

    if (Object.keys(validated.errors).length > 0) {
        return context.json({ message: 'Data akun tidak valid.', errors: validated.errors }, 422);
    }

    try {
        const existingUser = await context.env.DB.prepare(
            'SELECT id, role FROM users WHERE id = ? LIMIT 1',
        ).bind(id).first();

        if (!existingUser) {
            return context.json({ message: 'Pengguna tidak ditemukan.' }, 404);
        }
        if (existingUser.role === 'master') {
            return context.json({ message: 'Akun Master tidak dapat diubah melalui manajemen akun.' }, 403);
        }

        const duplicateUsername = await context.env.DB.prepare(
            'SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1',
        ).bind(validated.username, id).first();

        if (duplicateUsername) {
            return context.json({
                message: 'Username sudah digunakan.',
                errors: { username: ['Username sudah digunakan.'] },
            }, 422);
        }

        const updatedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
        let statement;

        if (validated.password) {
            statement = context.env.DB.prepare(
                `UPDATE users SET name = ?, username = ?, role = ?, password = ?, updated_at = ?
                 WHERE id = ? RETURNING id, name, username, role, created_at`,
            ).bind(
                validated.name,
                validated.username,
                validated.role,
                await hash(validated.password, 12),
                updatedAt,
                id,
            );
        } else {
            statement = context.env.DB.prepare(
                `UPDATE users SET name = ?, username = ?, role = ?, updated_at = ?
                 WHERE id = ? RETURNING id, name, username, role, created_at`,
            ).bind(validated.name, validated.username, validated.role, updatedAt, id);
        }

        const user = await statement.first();

        return context.json({ message: 'Akun berhasil diperbarui.', data: user });
    } catch (error) {
        console.error('API user update failed.', error);

        return context.json({ message: 'Akun tidak dapat diperbarui saat ini.' }, 500);
    }
}

async function deleteUser(context) {
    if (!isMaster(context)) {
        return context.json({ message: 'Hanya akun Master yang dapat mengelola pengguna.' }, 403);
    }

    const id = Number(context.req.param('id'));

    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'ID pengguna tidak valid.' }, 422);
    }

    try {
        const targetUser = await context.env.DB.prepare(
            'SELECT id, role FROM users WHERE id = ? LIMIT 1',
        ).bind(id).first();

        if (!targetUser) {
            return context.json({ message: 'Pengguna tidak ditemukan.' }, 404);
        }
        if (targetUser.role === 'master') {
            return context.json({ message: 'Akun Master tidak dapat dihapus.' }, 403);
        }

        await context.env.DB.prepare('DELETE FROM users WHERE id = ?').bind(id).run();

        return context.json({ message: 'Akun berhasil dihapus.' });
    } catch (error) {
        console.error('API user deletion failed.', error);

        return context.json({ message: 'Akun tidak dapat dihapus saat ini.' }, 500);
    }
}

async function getProfile(context) {
    return context.json({ data: context.get('user') });
}

async function updateProfile(context) {
    const user = context.get('user');
    const profile = await requestJson(context);

    if (!profile || typeof profile !== 'object' || Array.isArray(profile)) {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }

    const name = typeof profile.name === 'string' ? profile.name.trim() : '';
    const username = typeof profile.username === 'string' ? profile.username.trim() : '';
    const currentPassword = typeof profile.current_password === 'string' ? profile.current_password : '';
    const password = typeof profile.password === 'string' ? profile.password : '';
    const passwordConfirmation = typeof profile.password_confirmation === 'string'
        ? profile.password_confirmation
        : '';
    const errors = {};

    if (!name || name.length > 255) {
        errors.name = ['Nama wajib diisi dan maksimal 255 karakter.'];
    }
    if (!username || username.length > 255 || !/^[A-Za-z0-9._-]+$/.test(username)) {
        errors.username = ['Username wajib diisi dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.'];
    }
    if (password || passwordConfirmation || currentPassword) {
        if (!password || password.length < 8 || password.length > 1024) {
            errors.password = ['Kata sandi baru harus memiliki panjang 8 hingga 1024 karakter.'];
        }
        if (password !== passwordConfirmation) {
            errors.password_confirmation = ['Konfirmasi kata sandi tidak cocok.'];
        }
        if (!currentPassword) {
            errors.current_password = ['Kata sandi saat ini wajib diisi untuk mengganti kata sandi.'];
        }
    }
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data profil tidak valid.', errors }, 422);
    }

    try {
        const duplicateUsername = await context.env.DB.prepare(
            'SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1',
        ).bind(username, user.id).first();

        if (duplicateUsername) {
            return context.json({
                message: 'Username sudah digunakan.',
                errors: { username: ['Username sudah digunakan.'] },
            }, 422);
        }

        let passwordHash = null;

        if (password) {
            const existingUser = await context.env.DB.prepare(
                'SELECT password FROM users WHERE id = ? LIMIT 1',
            ).bind(user.id).first();

            if (!existingUser || !await compare(currentPassword, existingUser.password)) {
                return context.json({
                    message: 'Kata sandi saat ini salah.',
                    errors: { current_password: ['Kata sandi saat ini salah.'] },
                }, 422);
            }

            passwordHash = await hash(password, 12);
        }

        const updatedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const statement = passwordHash
            ? context.env.DB.prepare(
                `UPDATE users SET name = ?, username = ?, password = ?, updated_at = ?
                 WHERE id = ? RETURNING id, name, username, role`,
            ).bind(name, username, passwordHash, updatedAt, user.id)
            : context.env.DB.prepare(
                `UPDATE users SET name = ?, username = ?, updated_at = ?
                 WHERE id = ? RETURNING id, name, username, role`,
            ).bind(name, username, updatedAt, user.id);
        const updatedProfile = await statement.first();

        return context.json({ message: 'Pengaturan akun berhasil diperbarui.', data: updatedProfile });
    } catch (error) {
        console.error('API profile update failed.', error);

        return context.json({ message: 'Profil tidak dapat diperbarui saat ini.' }, 500);
    }
}

function isValidDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00.000Z`);

    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function transactionPayloadFromFormData(formData) {
    const payload = {};

    for (const [name, value] of formData.entries()) {
        const arrayField = name.match(/^([a-z_]+)\[(\d+)\]\[([a-z_]+)\]$/);

        if (arrayField) {
            const [, collection, indexValue, field] = arrayField;
            const index = Number(indexValue);
            payload[collection] ??= [];
            payload[collection][index] ??= {};

            if (typeof value === 'string') {
                payload[collection][index][field] = value;
            }

            continue;
        }

        if (name !== '_method'
            && !TRANSACTION_PHOTO_FIELD_SET.has(name)
            && name !== 'foto_utama'
            && typeof value === 'string') {
            payload[name] = value;
        }
    }

    return payload;
}

function transactionPhotosFromFormData(formData) {
    const files = [];
    const collectedFiles = new Set();
    const appendFile = (value) => {
        if (value instanceof File && value.size > 0 && !collectedFiles.has(value)) {
            collectedFiles.add(value);
            files.push(value);
        }
    };

    for (const field of TRANSACTION_PHOTO_FIELDS) {
        for (const value of formData.getAll(field)) {
            appendFile(value);
        }
    }

    for (const [, value] of formData.entries()) {
        appendFile(value);
    }

    return files;
}

function transactionValue(source, ...keys) {
    for (const key of keys) {
        if (source[key] !== undefined) {
            return source[key];
        }
    }

    return undefined;
}

function transactionText(source, key, errors, { aliases = [], maxLength = 255, required = false } = {}) {
    const value = transactionValue(source, key, ...aliases);
    const text = typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';

    if ((required && !text) || text.length > maxLength) {
        errors[key] = [`${required ? 'Wajib diisi' : 'Tidak valid'} dan maksimal ${maxLength} karakter.`];
    }

    return text || null;
}

function transactionNumber(source, key, errors, { defaultValue = 0, required = false, integer = false } = {}) {
    const value = source[key] === undefined || source[key] === '' || source[key] === null
        ? defaultValue
        : parseNumber(source[key]);

    if (!Number.isFinite(value) || value < 0 || (integer && !Number.isInteger(value))) {
        errors[key] = [integer ? 'Harus berupa bilangan bulat minimal 0.' : 'Harus berupa angka minimal 0.'];
        return defaultValue;
    }

    if (required && source[key] === undefined) {
        errors[key] = ['Wajib diisi.'];
    }

    return value;
}

function transactionDate(source, key, errors, { required = false } = {}) {
    const value = typeof source[key] === 'string' ? source[key].trim() : '';

    if ((required && !value) || (value && !isValidDate(value))) {
        errors[key] = [required ? 'Tanggal wajib diisi dengan format yang valid.' : 'Tanggal tidak valid.'];
        return null;
    }

    return value || null;
}

function transactionCollection(source, key, errors, { maxItems = 50, type = 'generic' } = {}) {
    const items = source[key] ?? [];

    if (!Array.isArray(items) || items.length > maxItems) {
        errors[key] = [`Maksimal ${maxItems} data.`];
        return [];
    }

    const normalized = [];

    for (const [index, item] of items.entries()) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            errors[key] = ['Format rincian tidak valid.'];
            return [];
        }

        const name = typeof item.name === 'string' ? item.name.trim() : '';

        if (name.length > 100) {
            errors[key] = [`Nama rincian ke-${index + 1} maksimal 100 karakter.`];
            return [];
        }

        const cost = parseNumber(item.cost ?? 0);

        if (!Number.isFinite(cost) || cost < 0) {
            errors[key] = [`Biaya rincian ke-${index + 1} harus berupa angka minimal 0.`];
            return [];
        }

        if (type === 'repair') {
            const date = typeof item.date === 'string' ? item.date.trim() : '';
            const paymentStatus = item.payment_status ?? 'lunas';

            if (!name || !isValidDate(date) || !['lunas', 'belum_lunas', 'panjar', 'lainnya'].includes(paymentStatus)) {
                errors[key] = [`Data perbaikan ke-${index + 1} tidak valid.`];
                return [];
            }

            normalized.push({
                name,
                date,
                cost,
                payment_status: paymentStatus,
                payment_note: typeof item.payment_note === 'string' ? item.payment_note.slice(0, 255) : '',
            });
            continue;
        }

        if (type === 'tax') {
            const estimatedCost = item.estimated_cost === '' || item.estimated_cost === null
                || item.estimated_cost === undefined
                ? null
                : parseNumber(item.estimated_cost);
            const date = typeof item.date === 'string' ? item.date.trim() : '';

            if ((estimatedCost !== null && (!Number.isFinite(estimatedCost) || estimatedCost < 0))
                || (date && !isValidDate(date))) {
                errors[key] = [`Data pajak ke-${index + 1} tidak valid.`];
                return [];
            }

            normalized.push({
                name,
                cost,
                estimated_cost: estimatedCost,
                date: date || null,
            });
            continue;
        }

        if (type === 'leasing') {
            const option = { name };

            for (const field of ['disbursement', 'selling_price', 'dp', 'installment_3yr', 'installment_4yr', 'installment_5yr']) {
                const amount = item[field] === '' || item[field] === null || item[field] === undefined
                    ? 0
                    : parseNumber(item[field]);

                if (!Number.isFinite(amount) || amount < 0) {
                    errors[key] = [`Nilai leasing ke-${index + 1} tidak valid.`];
                    return [];
                }

                option[field] = amount;
            }

            normalized.push(option);
            continue;
        }

        normalized.push({ name, cost });
    }

    return normalized;
}

function validateTransaction(source, user, existingTransaction = null) {
    const errors = {};
    const values = {
        plate_number: transactionText(source, 'plate_number', errors, { required: true, maxLength: 30 }),
        manager_name: transactionText(source, 'manager_name', errors),
        brand: transactionText(source, 'brand', errors, { required: true }),
        model: transactionText(source, 'model', errors, { required: true }),
        year: transactionNumber(source, 'year', errors, { required: true, integer: true }),
        transmission: transactionText(source, 'transmission', errors, { required: true }),
        color: transactionText(source, 'color', errors, { required: true }),
        chassis_number: transactionText(source, 'chassis_number', errors),
        engine_number: transactionText(source, 'engine_number', errors),
        mileage_km: transactionNumber(source, 'mileage_km', errors, { integer: true }),
        owner_name: transactionText(source, 'owner_name', errors),
        buy_date: transactionDate(source, 'buy_date', errors, { required: true }),
        sell_date: transactionDate(source, 'sell_date', errors),
        buy_price: transactionNumber(source, 'buy_price', errors),
        purchase_operational: transactionNumber(source, 'purchase_operational', errors),
        other_costs: transactionNumber(source, 'other_costs', errors),
        selling_price: transactionNumber(source, 'selling_price', errors),
        down_payment: transactionNumber(source, 'down_payment', errors),
        down_payment_date: transactionDate(source, 'down_payment_date', errors),
        settlement_amount: transactionNumber(source, 'settlement_amount', errors),
        settlement_date: transactionDate(source, 'settlement_date', errors),
        leasing_disbursement: transactionNumber(source, 'leasing_disbursement', errors),
        leasing_disbursement_date: transactionDate(source, 'leasing_disbursement_date', errors),
        planned_payment_date: transactionDate(source, 'planned_payment_date', errors),
        setor_price: transactionNumber(source, 'setor_price', errors),
        opening_price: transactionNumber(source, 'opening_price', errors),
        leasing_ripan: transactionNumber(source, 'leasing_ripan', errors),
        insurance_ripan: transactionNumber(source, 'insurance_ripan', errors),
        booster_ripan: transactionNumber(source, 'booster_ripan', errors),
        seller_name: transactionText(source, 'seller_name', errors, { aliases: ['nama_penjual'], required: true }),
        seller_nik: transactionText(source, 'seller_nik', errors, { aliases: ['nik_penjual'] }),
        seller_phone: transactionText(source, 'seller_phone', errors, { aliases: ['no_hp_penjual'] }),
        seller_address: transactionText(source, 'seller_address', errors, { aliases: ['alamat_penjual'], maxLength: 5000 }),
        broker_name: transactionText(source, 'broker_name', errors, { aliases: ['nama_broker'] }),
    };

    if (values.year < 1886 || values.year > new Date().getUTCFullYear() + 1) {
        errors.year = ['Tahun kendaraan tidak valid.'];
    }
    if (values.buy_date && values.sell_date && values.sell_date < values.buy_date) {
        errors.sell_date = ['Tanggal jual tidak boleh sebelum tanggal beli.'];
    }

    values.additional_repairs = transactionCollection(source, 'additional_repairs', errors, { type: 'repair' });
    values.tax_items = transactionCollection(source, 'tax_items', errors, { type: 'tax' });
    values.intertine_expenses = transactionCollection(source, 'intertine_expenses', errors);
    values.leasing_options = transactionCollection(source, 'leasing_options', errors, { type: 'leasing' });

    if (Object.keys(errors).length > 0) {
        return { errors, values: null };
    }

    if (user.role !== 'master') {
        values.buy_price = Number(existingTransaction?.buy_price ?? 0);
        values.leasing_ripan = Number(existingTransaction?.leasing_ripan ?? 0);
        values.insurance_ripan = Number(existingTransaction?.insurance_ripan ?? 0);
        values.booster_ripan = Number(existingTransaction?.booster_ripan ?? 0);
    }

    const totalRepairCost = values.additional_repairs.reduce((total, item) => total + item.cost, 0);
    const totalCapital = values.buy_price + values.purchase_operational + values.other_costs
        + values.tax_items.reduce((total, item) => total + item.cost, 0)
        + totalRepairCost;
    const grossProfit = values.selling_price - totalCapital;
    const showroomFee = Math.round(grossProfit * 0.1 * 100) / 100;
    const totalIntertineExpense = showroomFee
        + values.intertine_expenses.reduce((total, item) => total + item.cost, 0);

    Object.assign(values, {
        total_repair_cost: totalRepairCost,
        total_capital: totalCapital,
        remaining_payment: Math.max(
            values.selling_price - values.down_payment - values.settlement_amount - values.leasing_disbursement,
            0,
        ),
        gross_profit: grossProfit,
        showroom_fee: showroomFee,
        total_intertine_expense: totalIntertineExpense,
        net_profit: grossProfit - totalIntertineExpense,
    });

    return { errors, values };
}

async function createTransaction(context) {
    if (!['master', 'admin'].includes(context.get('user').role)) {
        return context.json({ message: 'Anda tidak diizinkan membuat transaksi kendaraan.' }, 403);
    }

    const contentType = context.req.header('Content-Type') ?? '';
    let payload;
    let files = [];
    let coverPhoto = '';

    if (contentType.includes('application/json')) {
        try {
            payload = await context.req.json();
        } catch {
            return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
        }
    } else if (contentType.includes('multipart/form-data')) {
        let formData;

        try {
            formData = await context.req.formData();
        } catch {
            return context.json({ message: 'Form transaksi tidak valid.' }, 400);
        }

        payload = transactionPayloadFromFormData(formData);
        files = transactionPhotosFromFormData(formData);
        coverPhoto = String(formData.get('foto_utama') ?? '');
    } else {
        return context.json({ message: 'Content-Type harus JSON atau multipart/form-data.' }, 415);
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Data transaksi tidak valid.' }, 422);
    }

    const errors = {};

    if (files.length > 20) {
        errors.foto_kendaraan = ['Maksimal 20 foto kendaraan.'];
    }
    if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
        errors.foto_kendaraan = ['Foto harus berformat JPG, PNG, atau WEBP.'];
    }
    if (files.some((file) => file.size > 5 * 1024 * 1024)) {
        errors.foto_kendaraan = ['Ukuran setiap foto maksimal 5 MB.'];
    }
    if (files.length > 0 && !context.env.VEHICLE_PHOTOS) {
        return context.json({ message: 'Penyimpanan foto kendaraan belum dikonfigurasi.' }, 503);
    }
    if (files.length > 0 && coverPhoto && !/^new:\d+$/.test(coverPhoto)) {
        errors.foto_utama = ['Foto utama tidak valid.'];
    }
    if (files.length > 0 && coverPhoto.startsWith('new:') && Number(coverPhoto.slice(4)) >= files.length) {
        errors.foto_utama = ['Foto utama tidak ditemukan.'];
    }
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data transaksi tidak valid.', errors }, 422);
    }

    const { errors: validationErrors, values } = validateTransaction(payload, context.get('user'));

    if (Object.keys(validationErrors).length > 0) {
        return context.json({ message: 'Data transaksi tidak valid.', errors: validationErrors }, 422);
    }

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const photoKeys = [];
    let transactionId;
    let failureStage = 'D1 transaction insert';

    try {
        const result = await context.env.DB.prepare(
            `INSERT INTO car_transactions (
                plate_number, manager_name, brand, model, year, transmission, color,
                chassis_number, engine_number, mileage_km, owner_name, buy_date, sell_date,
                buy_price, purchase_operational, other_costs, total_repair_cost, total_capital,
                selling_price, down_payment, remaining_payment, settlement_amount, gross_profit,
                showroom_fee, total_intertine_expense, net_profit, additional_repairs, tax_items,
                intertine_expenses, leasing_disbursement, setor_price, opening_price, leasing_ripan,
                insurance_ripan, booster_ripan, down_payment_date, settlement_date,
                leasing_disbursement_date, planned_payment_date, leasing_options, seller_name,
                seller_nik, seller_phone, seller_address, broker_name, created_at, updated_at
            ) VALUES (${Array(47).fill('?').join(', ')})`,
        ).bind(
            values.plate_number, values.manager_name, values.brand, values.model, values.year,
            values.transmission, values.color, values.chassis_number, values.engine_number,
            values.mileage_km, values.owner_name, values.buy_date, values.sell_date,
            values.buy_price, values.purchase_operational, values.other_costs,
            values.total_repair_cost, values.total_capital, values.selling_price,
            values.down_payment, values.remaining_payment, values.settlement_amount,
            values.gross_profit, values.showroom_fee, values.total_intertine_expense,
            values.net_profit, JSON.stringify(values.additional_repairs), JSON.stringify(values.tax_items),
            JSON.stringify(values.intertine_expenses), values.leasing_disbursement,
            values.setor_price, values.opening_price, values.leasing_ripan, values.insurance_ripan,
            values.booster_ripan, values.down_payment_date, values.settlement_date,
            values.leasing_disbursement_date, values.planned_payment_date,
            JSON.stringify(values.leasing_options), values.seller_name, values.seller_nik,
            values.seller_phone, values.seller_address, values.broker_name, now, now,
        ).run();
        transactionId = Number(result.meta?.last_row_id);

        if (!Number.isSafeInteger(transactionId) || transactionId < 1) {
            throw new Error('D1 did not return the created transaction ID.');
        }

        const selectedCoverIndex = coverPhoto.startsWith('new:')
            ? Number(coverPhoto.slice(4))
            : 0;
        const uploadedPhotos = [];

        for (const [index, file] of files.entries()) {
            const extension = file.type === 'image/jpeg' ? 'jpg' : file.type.slice('image/'.length);
            const key = `vehicles/${transactionId}/${crypto.randomUUID()}.${extension}`;

            failureStage = 'R2 photo upload';
            photoKeys.push(key);
            await context.env.VEHICLE_PHOTOS.put(key, await file.arrayBuffer(), {
                httpMetadata: { contentType: file.type },
            });
            uploadedPhotos.push({ key, isCover: index === selectedCoverIndex });
        }

        const photos = [];

        for (const [position, photo] of uploadedPhotos.entries()) {
            failureStage = 'D1 vehicle photo insert';
            const photoResult = await context.env.DB.prepare(
                `INSERT INTO vehicle_photos (
                    car_transaction_id, path, is_cover, position, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?)`,
            ).bind(transactionId, photo.key, photo.isCover ? 1 : 0, position, now, now).run();

            photos.push({
                id: Number(photoResult.meta?.last_row_id),
                path: photo.key,
                url: publicPhotoUrl(context, photo.key),
                is_cover: photo.isCover,
                is_primary: photo.isCover,
                position,
            });
        }

        const coverPath = photos.find((photo) => photo.is_cover)?.path ?? null;

        if (coverPath) {
            failureStage = 'D1 transaction cover update';
            await context.env.DB.prepare(
                'UPDATE car_transactions SET vehicle_photo_path = ?, updated_at = ? WHERE id = ?',
            ).bind(coverPath, now, transactionId).run();
        }

        return context.json({
            message: 'Transaksi berhasil disimpan',
            data: {
                id: transactionId,
                ...values,
                vehicle_photo_path: coverPath,
                vehicle_photos: photos,
            },
        }, 201);
    } catch (error) {
        console.error(`API transaction creation failed during ${failureStage}.`, error);

        for (const key of photoKeys) {
            try {
                await context.env.VEHICLE_PHOTOS.delete(key);
            } catch (cleanupError) {
                console.error('API transaction photo cleanup failed.', cleanupError);
            }
        }

        if (transactionId) {
            try {
                await context.env.DB.prepare(
                    'DELETE FROM car_transactions WHERE id = ?',
                ).bind(transactionId).run();
            } catch (cleanupError) {
                console.error('API transaction database cleanup failed.', cleanupError);
            }
        }

        return context.json({ message: 'Transaksi tidak dapat disimpan saat ini.' }, 500);
    }
}

function storedArray(value) {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    const parsed = JSON.parse(value);

    if (!Array.isArray(parsed)) {
        throw new Error('Stored vehicle data must be a JSON array.');
    }

    return parsed;
}

function publicPhotoUrl(context, path) {
    const baseUrl = context.env.VEHICLE_PHOTOS_PUBLIC_URL;
    const cleanKey = String(path).replace(/^\/+/, '');
    const encodedPath = cleanKey.split('/').map(encodeURIComponent).join('/');

    if (typeof baseUrl === 'string' && baseUrl.trim()) {
        try {
            const parsedBaseUrl = new URL(baseUrl);
            const isUsableBaseUrl = ['http:', 'https:'].includes(parsedBaseUrl.protocol)
                && !parsedBaseUrl.username
                && !parsedBaseUrl.password
                && !parsedBaseUrl.search
                && !parsedBaseUrl.hash
                && !/GANTI_DENGAN|PLACEHOLDER|<[^>]*>/i.test(baseUrl);

            if (isUsableBaseUrl) {
                return `${baseUrl.replace(/\/+$/, '')}/${encodedPath}`;
            }
        } catch {
            // Fall back to the Worker proxy when the configured URL is invalid.
        }
    }

    return `${new URL(context.req.url).origin}/api/vehicle-photos/${encodedPath}`;
}

async function streamVehiclePhoto(context) {
    const pathPrefix = '/api/vehicle-photos/';
    const pathname = new URL(context.req.url).pathname;
    let key;

    try {
        const encodedSegments = pathname.startsWith(pathPrefix)
            ? pathname.slice(pathPrefix.length).split('/')
            : [];
        const decodedSegments = encodedSegments.map((segment) => decodeURIComponent(segment));

        if (decodedSegments.some((segment) => segment.includes('/') || segment.includes('\\'))) {
            return context.json({ message: 'Foto tidak ditemukan.' }, 404);
        }

        key = decodedSegments.join('/').replace(/^\/+/, '');
    } catch {
        return context.json({ message: 'Foto tidak ditemukan.' }, 404);
    }

    const expenseReceiptMatch = key.match(/^expenses\/([1-9]\d*)\/([A-Za-z0-9._-]+)$/);
    if (expenseReceiptMatch && !expenseReceiptMatch[2].includes('..')) {
        const expenseId = Number(expenseReceiptMatch[1]);
        if (!Number.isSafeInteger(expenseId)) {
            return context.json({ message: 'Foto tidak ditemukan.' }, 404);
        }

        try {
            const registeredReceipt = await context.env.DB.prepare(
                'SELECT receipt_path FROM showroom_expenses WHERE id = ? AND receipt_path = ?',
            ).bind(expenseId, key).first();
            if (!registeredReceipt || !context.env.VEHICLE_PHOTOS) {
                return context.json({ message: 'Foto tidak ditemukan.' }, 404);
            }

            const object = await context.env.VEHICLE_PHOTOS.get(key);
            if (!object) {
                return context.json({ message: 'Foto tidak ditemukan.' }, 404);
            }

            const contentType = object.httpMetadata?.contentType?.toLowerCase();
            if (!SHOWROOM_EXPENSE_RECEIPT_TYPES.has(contentType)) {
                return context.json({ message: 'Format foto tidak didukung.' }, 415);
            }

            return new Response(object.body, {
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Cache-Control': 'public, max-age=86400',
                    'Content-Type': contentType,
                    'X-Content-Type-Options': 'nosniff',
                },
            });
        } catch (error) {
            console.error('Showroom expense receipt streaming failed.', error);

            return context.json({ message: 'Bukti pengeluaran tidak dapat dimuat saat ini.' }, 500);
        }
    }

    const keyMatch = key.match(/^(?:(?:vehicle-photos|vehicles|transactions)\/)?([1-9]\d*)\/([A-Za-z0-9._-]+)$/);

    if (!keyMatch || keyMatch[2].includes('..')) {
        return context.json({ message: 'Foto tidak ditemukan.' }, 404);
    }

    const transactionId = Number(keyMatch[1]);
    if (!Number.isSafeInteger(transactionId)) {
        return context.json({ message: 'Foto tidak ditemukan.' }, 404);
    }

    const canonicalKey = `vehicles/${transactionId}/${keyMatch[2]}`;
    const folderlessKey = `${transactionId}/${keyMatch[2]}`;
    const keyCandidates = [...new Set([
        key,
        canonicalKey,
        folderlessKey,
        `transactions/${transactionId}/${keyMatch[2]}`,
        `vehicle-photos/${canonicalKey}`,
        `vehicle-photos/${folderlessKey}`,
        `vehicle-photos/transactions/${transactionId}/${keyMatch[2]}`,
        `/${key}`,
        `/${canonicalKey}`,
        `/${folderlessKey}`,
    ])];

    try {
        const pathPlaceholders = keyCandidates.map(() => '?').join(', ');
        const registeredPhoto = await context.env.DB.prepare(
            `SELECT path FROM vehicle_photos
             WHERE car_transaction_id = ? AND path IN (${pathPlaceholders})
             UNION ALL
             SELECT vehicle_photo_path AS path
             FROM car_transactions
             WHERE id = ? AND vehicle_photo_path IN (${pathPlaceholders})
             LIMIT 1`,
        ).bind(transactionId, ...keyCandidates, transactionId, ...keyCandidates).first();

        if (!registeredPhoto) {
            return context.json({ message: 'Foto tidak ditemukan.' }, 404);
        }

        let object = await context.env.VEHICLE_PHOTOS.get(key);

        if (!object) {
            for (const candidateKey of keyCandidates) {
                if (candidateKey === key) {
                    continue;
                }

                object = await context.env.VEHICLE_PHOTOS.get(candidateKey);
                if (object) {
                    break;
                }
            }
        }

        if (!object) {
            return context.json({ message: 'Foto tidak ditemukan.' }, 404);
        }

        const contentTypesByExtension = {
            jpg: 'image/jpeg',
            jpeg: 'image/jpeg',
            png: 'image/png',
            webp: 'image/webp',
        };
        const extension = keyMatch[2].split('.').pop()?.toLowerCase() ?? '';
        const fallbackContentType = contentTypesByExtension[extension];
        const metadataContentType = object.httpMetadata?.contentType?.toLowerCase();
        const contentType = ['image/jpeg', 'image/png', 'image/webp'].includes(metadataContentType)
            ? metadataContentType
            : fallbackContentType;

        if (!contentType) {
            return context.json({ message: 'Format foto tidak didukung.' }, 415);
        }

        return new Response(object.body, {
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Cache-Control': 'public, max-age=86400',
                'Content-Type': contentType,
                'X-Content-Type-Options': 'nosniff',
            },
        });
    } catch (error) {
        console.error('API vehicle photo streaming failed.', error);

        return context.json({ message: 'Foto tidak dapat dimuat saat ini.' }, 500);
    }
}

function transactionResponse(context, transaction, photos, saleNote = null) {
    const canViewSensitiveData = context.get('user').role === 'master';
    const summary = dashboardRow(
        context,
        transaction,
        canViewSensitiveData,
        new Date().toISOString().slice(0, 10),
    );
    const additionalRepairs = storedArray(transaction.additional_repairs);
    const taxItems = storedArray(transaction.tax_items);
    const intertineExpenses = storedArray(transaction.intertine_expenses);
    const leasingOptions = storedArray(transaction.leasing_options);
    const coverPhotoPath = transaction.vehicle_photo_path
        || photos.find((photo) => Boolean(photo.is_cover))?.path
        || photos[0]?.path
        || null;
    const data = {
        ...transaction,
        vehicle_status: transaction.sell_date ? 'sold' : 'available',
        total_paid: Number(transaction.down_payment ?? 0)
            + Number(transaction.settlement_amount ?? 0)
            + Number(transaction.leasing_disbursement ?? 0),
        tax_status: summary.tax_status,
        showroom_age_status: summary.showroom_age_status,
        additional_repairs: additionalRepairs,
        repairs: additionalRepairs,
        tax_items: taxItems,
        tax_amount: taxItems.reduce((total, item) => {
            const amount = Number(item.cost ?? 0);

            return total + (Number.isFinite(amount) ? amount : 0);
        }, 0),
        intertine_expenses: intertineExpenses,
        leasing_options: leasingOptions,
        leasing_prices: leasingOptions,
        purchase_operation: transaction.purchase_operational,
        vehicle_photos: photos.map((photo) => ({
            id: photo.id,
            path: photo.path,
            url: publicPhotoUrl(context, photo.path),
            is_cover: Boolean(photo.is_cover),
            is_primary: Boolean(photo.is_primary ?? photo.is_cover),
            position: photo.position,
        })),
        vehicle_photo_url: coverPhotoPath
            ? publicPhotoUrl(context, coverPhotoPath)
            : null,
        legacy_photo_url: coverPhotoPath
            ? publicPhotoUrl(context, coverPhotoPath)
            : null,
        sale_note: saleNote
            ? {
                id: saleNote.id,
                nama_konsumen: saleNote.nama_konsumen,
                buyer_nik: saleNote.buyer_nik,
                buyer_phone: saleNote.buyer_phone,
                buyer_address: saleNote.buyer_address,
            }
            : null,
        can_view_sensitive_data: canViewSensitiveData,
    };

    if (canViewSensitiveData) {
        Object.assign(data, {
            purchase_price: transaction.buy_price,
            total_cost: transaction.total_capital,
            laba_jual: transaction.gross_profit,
            ripan_leasing: transaction.leasing_ripan,
            ripan_asuransi: transaction.insurance_ripan,
            ripan_booster: transaction.booster_ripan,
        });
    } else {
        for (const field of [
            'buy_price',
            'total_capital',
            'gross_profit',
            'showroom_fee',
            'total_intertine_expense',
            'net_profit',
            'leasing_ripan',
            'insurance_ripan',
            'booster_ripan',
            'purchase_price',
            'total_cost',
            'laba_jual',
            'ripan_leasing',
            'ripan_asuransi',
            'ripan_booster',
        ]) {
            delete data[field];
        }
    }

    return data;
}

async function getTransaction(context) {
    const user = context.get('user');
    const id = Number(context.req.param('id'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat data kendaraan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
    }

    try {
        const transaction = await context.env.DB.prepare(
            'SELECT * FROM car_transactions WHERE id = ? LIMIT 1',
        ).bind(id).first();

        if (!transaction) {
            return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
        }

        const photos = await context.env.DB.prepare(
            `SELECT id, path, is_cover, position
             FROM vehicle_photos
             WHERE car_transaction_id = ?
             ORDER BY position, id`,
        ).bind(id).all();
        const saleNoteConditions = ['car_transaction_id = ?'];
        const saleNoteParameters = [id];
        if (user.role !== 'master') {
            saleNoteConditions.push('created_by = ?');
            saleNoteParameters.push(user.id);
        }
        const saleNote = await context.env.DB.prepare(
            `SELECT id, nama_konsumen, buyer_nik, buyer_phone, buyer_address
             FROM sale_notes
             WHERE ${saleNoteConditions.join(' AND ')}
             LIMIT 1`,
        ).bind(...saleNoteParameters).first();

        return context.json({
            data: transactionResponse(context, transaction, photos.results ?? [], saleNote),
        });
    } catch (error) {
        console.error('API transaction detail query failed.', error);

        return context.json({ message: 'Detail transaksi tidak dapat dimuat saat ini.' }, 500);
    }
}

async function updateTransaction(context) {
    const user = context.get('user');
    const id = Number(context.req.param('id'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan memperbarui transaksi kendaraan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
    }

    const contentType = context.req.header('Content-Type') ?? '';
    let payload;
    let files = [];
    let coverPhoto = '';
    let removedPhotoIds = [];
    let removeLegacyPhoto = false;

    if (contentType.includes('application/json')) {
        try {
            payload = await context.req.json();
        } catch {
            return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
        }
    } else if (contentType.includes('multipart/form-data')) {
        let formData;

        try {
            formData = await context.req.formData();
        } catch {
            return context.json({ message: 'Form transaksi tidak valid.' }, 400);
        }

        payload = transactionPayloadFromFormData(formData);
        files = transactionPhotosFromFormData(formData);
        coverPhoto = String(formData.get('foto_utama') ?? '');
        removedPhotoIds = formData.getAll('hapus_foto[]').map((value) => String(value));
        removeLegacyPhoto = formData.get('hapus_foto_legacy') === '1';
    } else {
        return context.json({ message: 'Content-Type harus JSON atau multipart/form-data.' }, 415);
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Data transaksi tidak valid.' }, 422);
    }

    let transaction;
    let existingPhotos;

    try {
        transaction = await context.env.DB.prepare(
            'SELECT * FROM car_transactions WHERE id = ? LIMIT 1',
        ).bind(id).first();

        if (!transaction) {
            return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
        }

        const photosResult = await context.env.DB.prepare(
            `SELECT id, path, is_cover, position
             FROM vehicle_photos
             WHERE car_transaction_id = ?
             ORDER BY position, id`,
        ).bind(id).all();
        existingPhotos = photosResult.results ?? [];
    } catch (error) {
        console.error('API transaction update lookup failed.', error);

        return context.json({ message: 'Data transaksi tidak dapat diperbarui saat ini.' }, 500);
    }

    const errors = {};

    if (files.length > 20 || existingPhotos.length - removedPhotoIds.length + files.length > 20) {
        errors.foto_kendaraan = ['Maksimal 20 foto kendaraan.'];
    }
    if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
        errors.foto_kendaraan = ['Foto harus berformat JPG, PNG, atau WEBP.'];
    }
    if (files.some((file) => file.size > 5 * 1024 * 1024)) {
        errors.foto_kendaraan = ['Ukuran setiap foto maksimal 5 MB.'];
    }
    if (files.length > 0 && !context.env.VEHICLE_PHOTOS) {
        return context.json({ message: 'Penyimpanan foto kendaraan belum dikonfigurasi.' }, 503);
    }
    const photosChanged = files.length > 0 || removedPhotoIds.length > 0 || removeLegacyPhoto;
    if (photosChanged && !context.env.VEHICLE_PHOTOS) {
        return context.json({ message: 'Penyimpanan foto kendaraan belum dikonfigurasi.' }, 503);
    }

    const keptPhotos = existingPhotos.filter((photo) => !removedPhotoIds.includes(String(photo.id)));

    if (keptPhotos.length !== existingPhotos.length - new Set(removedPhotoIds).size) {
        errors.hapus_foto = ['Foto yang akan dihapus tidak ditemukan.'];
    }

    const keptLegacyPath = removeLegacyPhoto ? null : transaction.vehicle_photo_path;
    const availableCoverIds = new Set(keptPhotos.map((photo) => String(photo.id)));
    if (keptLegacyPath) {
        availableCoverIds.add('legacy');
    }
    const isNewCover = /^new:\d+$/.test(coverPhoto);
    const isExistingCover = /^existing:(legacy|\d+)$/.test(coverPhoto);
    const selectedNewCover = isNewCover ? Number(coverPhoto.slice(4)) : -1;

    if (coverPhoto && ((!isNewCover && !isExistingCover)
        || (isNewCover && selectedNewCover >= files.length)
        || (isExistingCover && !availableCoverIds.has(coverPhoto.slice('existing:'.length))))) {
        errors.foto_utama = ['Foto utama tidak ditemukan.'];
    }

    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data transaksi tidak valid.', errors }, 422);
    }

    const { errors: validationErrors, values } = validateTransaction(payload, user, transaction);

    if (Object.keys(validationErrors).length > 0) {
        return context.json({ message: 'Data transaksi tidak valid.', errors: validationErrors }, 422);
    }

    const uploadedKeys = [];
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const removedPhotos = existingPhotos.filter((photo) => removedPhotoIds.includes(String(photo.id)));
    let databaseUpdated = false;
    let failureStage = 'R2 photo upload';
    const retainedCover = coverPhoto.startsWith('existing:')
        ? coverPhoto.slice('existing:'.length)
        : '';
    let coverPath = retainedCover === 'legacy'
        ? keptLegacyPath
        : keptPhotos.find((photo) => String(photo.id) === retainedCover)?.path ?? null;

    if (removeLegacyPhoto && coverPath === transaction.vehicle_photo_path) {
        coverPath = null;
    }

    try {
        for (const [index, file] of files.entries()) {
            const extension = file.type === 'image/jpeg' ? 'jpg' : file.type.slice('image/'.length);
            const key = `vehicles/${id}/${crypto.randomUUID()}.${extension}`;

            uploadedKeys.push(key);
            await context.env.VEHICLE_PHOTOS.put(key, await file.arrayBuffer(), {
                httpMetadata: { contentType: file.type },
            });

            if (!coverPath && index === 0) {
                coverPath = key;
            }
        }

        if (isNewCover) {
            coverPath = uploadedKeys[selectedNewCover];
        }
        if (!coverPath) {
            coverPath = keptPhotos[0]?.path ?? keptLegacyPath ?? uploadedKeys[0] ?? null;
        }

        const retainedCoverPhoto = keptPhotos.find((photo) => photo.path === coverPath);
        const columns = [
            'plate_number', 'manager_name', 'brand', 'model', 'year', 'transmission', 'color',
            'chassis_number', 'engine_number', 'mileage_km', 'owner_name', 'buy_date', 'sell_date',
            'buy_price', 'purchase_operational', 'other_costs', 'total_repair_cost', 'total_capital',
            'selling_price', 'down_payment', 'remaining_payment', 'settlement_amount', 'gross_profit',
            'showroom_fee', 'total_intertine_expense', 'net_profit', 'additional_repairs', 'tax_items',
            'intertine_expenses', 'leasing_disbursement', 'setor_price', 'opening_price', 'leasing_ripan',
            'insurance_ripan', 'booster_ripan', 'down_payment_date', 'settlement_date',
            'leasing_disbursement_date', 'planned_payment_date', 'leasing_options', 'seller_name',
            'seller_nik', 'seller_phone', 'seller_address', 'broker_name',
        ];
        const valuesByColumn = {
            ...values,
            additional_repairs: JSON.stringify(values.additional_repairs),
            tax_items: JSON.stringify(values.tax_items),
            intertine_expenses: JSON.stringify(values.intertine_expenses),
            leasing_options: JSON.stringify(values.leasing_options),
        };
        const statements = [
            context.env.DB.prepare(
                `UPDATE car_transactions
                 SET ${columns.map((column) => `${column} = ?`).join(', ')},
                     vehicle_photo_path = ?, updated_at = ?
                 WHERE id = ?`,
            ).bind(...columns.map((column) => valuesByColumn[column]), coverPath, now, id),
        ];

        for (const photo of removedPhotos) {
            statements.push(context.env.DB.prepare(
                'DELETE FROM vehicle_photos WHERE id = ? AND car_transaction_id = ?',
            ).bind(photo.id, id));
        }
        statements.push(context.env.DB.prepare(
            'UPDATE vehicle_photos SET is_cover = 0, updated_at = ? WHERE car_transaction_id = ?',
        ).bind(now, id));
        for (const [index, key] of uploadedKeys.entries()) {
            statements.push(context.env.DB.prepare(
                `INSERT INTO vehicle_photos (
                    car_transaction_id, path, is_cover, position, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?)`,
            ).bind(id, key, key === coverPath ? 1 : 0, keptPhotos.length + index, now, now));
        }
        if (retainedCoverPhoto) {
            statements.push(context.env.DB.prepare(
                'UPDATE vehicle_photos SET is_cover = 1, updated_at = ? WHERE id = ? AND car_transaction_id = ?',
            ).bind(now, retainedCoverPhoto.id, id));
        }

        failureStage = 'D1 vehicle and photo metadata batch';
        const results = await context.env.DB.batch(statements);
        if (Number(results[0]?.meta?.changes) !== 1) {
            throw new Error('D1 did not update the requested transaction.');
        }
        databaseUpdated = true;

        for (const photo of removedPhotos) {
            await context.env.VEHICLE_PHOTOS.delete(photo.path);
        }
        if (removeLegacyPhoto && transaction.vehicle_photo_path
            && !existingPhotos.some((photo) => photo.path === transaction.vehicle_photo_path)) {
            await context.env.VEHICLE_PHOTOS.delete(transaction.vehicle_photo_path);
        }

        return context.json({ message: 'Data transaksi berhasil diperbarui.' });
    } catch (error) {
        console.error(`API transaction update failed during ${failureStage}.`, error);

        if (!databaseUpdated) {
            for (const key of uploadedKeys) {
                try {
                    await context.env.VEHICLE_PHOTOS.delete(key);
                } catch (cleanupError) {
                    console.error('API transaction photo cleanup failed.', cleanupError);
                }
            }
        }

        return context.json({ message: 'Transaksi tidak dapat diperbarui saat ini.' }, 500);
    }
}

async function deleteTransaction(context) {
    const user = context.get('user');
    const id = Number(context.req.param('id'));

    if (user.role !== 'master') {
        return context.json({ message: 'Anda tidak diizinkan menghapus transaksi kendaraan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
    }

    try {
        const transaction = await context.env.DB.prepare(
            'SELECT id, vehicle_photo_path FROM car_transactions WHERE id = ? LIMIT 1',
        ).bind(id).first();

        if (!transaction) {
            return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
        }

        const photos = await context.env.DB.prepare(
            'SELECT path FROM vehicle_photos WHERE car_transaction_id = ?',
        ).bind(id).all();
        const keys = [...new Set([
            ...(photos.results ?? []).map((photo) => photo.path),
            transaction.vehicle_photo_path,
        ].filter(Boolean))];

        if (keys.length > 0 && !context.env.VEHICLE_PHOTOS) {
            return context.json({ message: 'Penyimpanan foto kendaraan belum dikonfigurasi.' }, 503);
        }

        const results = await context.env.DB.batch([
            context.env.DB.prepare(
                `DELETE FROM sale_payments
                 WHERE sale_note_id IN (
                    SELECT id FROM sale_notes WHERE car_transaction_id = ?
                 )`,
            ).bind(id),
            context.env.DB.prepare(
                'DELETE FROM sale_notes WHERE car_transaction_id = ?',
            ).bind(id),
            context.env.DB.prepare(
                'DELETE FROM vehicle_photos WHERE car_transaction_id = ?',
            ).bind(id),
            context.env.DB.prepare(
                'DELETE FROM car_transactions WHERE id = ?',
            ).bind(id),
        ]);

        if (Number(results[3]?.meta?.changes) !== 1) {
            return context.json({ message: 'Data transaksi tidak ditemukan.' }, 404);
        }

        await Promise.all(keys.map((key) => context.env.VEHICLE_PHOTOS.delete(key)));

        return context.json({ message: 'Transaksi berhasil dihapus', deleted_id: id });
    } catch (error) {
        console.error('API transaction deletion failed.', error);

        return context.json({ message: 'Transaksi tidak dapat dihapus saat ini.' }, 500);
    }
}

function dashboardRow(context, transaction, canViewSensitiveData, today, fallbackPhotoPath = null) {
    let taxItems = [];

    try {
        taxItems = Array.isArray(transaction.tax_items)
            ? transaction.tax_items
            : JSON.parse(transaction.tax_items || '[]');
    } catch {
        taxItems = [];
    }

    const validTaxItems = Array.isArray(taxItems)
        ? taxItems.filter((item) => item && typeof item === 'object')
        : [];
    const taxItem = validTaxItems.find((item) => String(item.name ?? '').trim().toLowerCase() === 'pajak')
        ?? validTaxItems[0];
    const photoPath = transaction.vehicle_photo_path || fallbackPhotoPath;
    const taxDate = typeof taxItem?.date === 'string' ? taxItem.date : '';
    const isTaxActive = isValidDate(taxDate) && taxDate >= today;
    const buyDate = typeof transaction.buy_date === 'string' ? transaction.buy_date : '';
    const buyTime = isValidDate(buyDate) ? Date.parse(`${buyDate}T00:00:00.000Z`) : Number.NaN;
    const todayTime = Date.parse(`${today}T00:00:00.000Z`);
    const days = Number.isNaN(buyTime) ? null : Math.max(0, Math.floor((todayTime - buyTime) / 86400000));
    const row = {
        id: transaction.id,
        plate_number: transaction.plate_number,
        brand: transaction.brand,
        model: transaction.model,
        year: transaction.year,
        status: transaction.sell_date ? 'sold' : 'stock',
        buy_date: transaction.buy_date,
        sell_date: transaction.sell_date,
        vehicle_photo_url: photoPath ? publicPhotoUrl(context, photoPath) : null,
        tax_status: {
            label: isTaxActive ? 'Hidup' : 'Mati',
            badgeClass: isTaxActive ? 'status-badge--success' : 'status-badge--danger',
        },
        showroom_age_status: {
            days,
            badgeClass: days === null
                ? 'status-badge--neutral'
                : days <= 25
                    ? 'status-badge--success'
                    : days <= 45
                        ? 'status-badge--warning'
                        : 'status-badge--danger',
        },
        selling_price: transaction.selling_price,
        setor_price: transaction.setor_price,
        opening_price: transaction.opening_price,
        can_update: true,
        can_delete: canViewSensitiveData,
    };

    if (canViewSensitiveData) {
        row.buy_price = transaction.buy_price;
        row.total_capital = transaction.total_capital;
        row.gross_profit = transaction.gross_profit;
        row.setor_profit = Number(transaction.setor_price ?? 0) - Number(transaction.total_capital ?? 0);
        row.total_profit = row.setor_profit
            + Number(transaction.leasing_ripan ?? 0)
            + Number(transaction.insurance_ripan ?? 0)
            + Number(transaction.booster_ripan ?? 0);
    }

    return row;
}

async function getDashboard(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat data kendaraan.' }, 403);
    }

    const search = (context.req.query('q') ?? '').trim();
    const view = context.req.query('view') ?? 'dashboard';
    const startDate = context.req.query('start_date') ?? '';
    const endDate = context.req.query('end_date') ?? '';
    const pageValue = context.req.query('page') ?? '1';
    const page = Number(pageValue);
    const errors = {};

    if (search.length > 255) {
        errors.q = ['Pencarian maksimal 255 karakter.'];
    }
    if (!['dashboard', 'inventory', 'sales', 'reports'].includes(view)) {
        errors.view = ['Filter tampilan tidak valid.'];
    }
    if (startDate && !isValidDate(startDate)) {
        errors.start_date = ['Tanggal mulai tidak valid.'];
    }
    if (endDate && !isValidDate(endDate)) {
        errors.end_date = ['Tanggal akhir tidak valid.'];
    }
    if (startDate && endDate && endDate < startDate) {
        errors.end_date = ['Tanggal akhir harus sama atau setelah tanggal mulai.'];
    }
    if (!/^\d+$/.test(pageValue) || !Number.isSafeInteger(page) || page < 1) {
        errors.page = ['Halaman harus berupa bilangan bulat minimal 1.'];
    }
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Filter dashboard tidak valid.', errors }, 422);
    }

    const canViewSensitiveData = user.role === 'master';
    const conditions = [];
    const parameters = [];

    if (view === 'inventory') {
        conditions.push('sell_date IS NULL');
    } else if (view === 'sales' || view === 'reports') {
        conditions.push('sell_date IS NOT NULL');
    }
    if (view === 'reports' && startDate) {
        conditions.push('sell_date >= ?');
        parameters.push(startDate);
    }
    if (view === 'reports' && endDate) {
        conditions.push('sell_date <= ?');
        parameters.push(endDate);
    }
    if (search) {
        conditions.push('(plate_number LIKE ? OR brand LIKE ? OR model LIKE ? OR CAST(year AS TEXT) LIKE ?)');
        const searchPattern = `%${search}%`;
        parameters.push(searchPattern, searchPattern, searchPattern, searchPattern);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';
    const offset = (page - 1) * 10;

    try {
        const [rowsResult, countResult, statistics] = await Promise.all([
            context.env.DB.prepare(
                `SELECT * FROM car_transactions ${whereClause}
                 ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?`,
            ).bind(...parameters, 10, offset).all(),
            context.env.DB.prepare(
                `SELECT COUNT(*) AS total FROM car_transactions ${whereClause}`,
            ).bind(...parameters).first(),
            context.env.DB.prepare(
                `SELECT COUNT(*) AS total,
                        SUM(CASE WHEN sell_date IS NULL THEN 1 ELSE 0 END) AS stock,
                        SUM(CASE WHEN sell_date IS NOT NULL THEN 1 ELSE 0 END) AS sold
                 FROM car_transactions`,
            ).first(),
        ]);
        const rows = rowsResult.results ?? [];
        const total = Number(countResult?.total ?? 0);
        const photosResult = rows.length > 0
            ? await context.env.DB.prepare(
                `SELECT car_transaction_id, path, is_cover, position
                 FROM vehicle_photos
                 WHERE car_transaction_id IN (${rows.map(() => '?').join(', ')})
                 ORDER BY car_transaction_id, position, id`,
            ).bind(...rows.map((row) => row.id)).all()
            : { results: [] };
        const coverPhotoPaths = new Map();
        const fallbackPhotoPaths = new Map();

        for (const photo of photosResult.results ?? []) {
            if (photo.is_cover && !coverPhotoPaths.has(photo.car_transaction_id)) {
                coverPhotoPaths.set(photo.car_transaction_id, photo.path);
            }
            if (!fallbackPhotoPaths.has(photo.car_transaction_id)) {
                fallbackPhotoPaths.set(photo.car_transaction_id, photo.path);
            }
        }
        const lastPage = Math.max(1, Math.ceil(total / 10));
        const today = new Date().toISOString().slice(0, 10);

        return context.json({
            data: rows.map((transaction) => dashboardRow(
                context,
                transaction,
                canViewSensitiveData,
                today,
                coverPhotoPaths.get(transaction.id) ?? fallbackPhotoPaths.get(transaction.id) ?? null,
            )),
            links: {},
            meta: {
                current_page: page,
                from: total === 0 ? null : offset + 1,
                last_page: lastPage,
                per_page: 10,
                to: total === 0 ? null : Math.min(offset + rows.length, total),
                total,
            },
            filters: {
                view,
                search,
                start_date: startDate || null,
                end_date: endDate || null,
            },
            permissions: {
                can_view_sensitive_data: canViewSensitiveData,
            },
            statistics: {
                total: Number(statistics?.total ?? 0),
                stock: Number(statistics?.stock ?? 0),
                sold: Number(statistics?.sold ?? 0),
            },
        });
    } catch (error) {
        console.error('API dashboard query failed.', error);

        return context.json({ message: 'Data dashboard tidak dapat dimuat saat ini.' }, 500);
    }
}

function saleStatusSql() {
    return `CASE
        WHEN car_transactions.remaining_payment <= 0 THEN 'paid'
        WHEN car_transactions.leasing_disbursement > 0
            OR EXISTS (
                SELECT 1 FROM sale_payments
                WHERE sale_payments.sale_note_id = sale_notes.id
                    AND sale_payments.payment_method = 'leasing'
            ) THEN 'leasing'
        ELSE 'unpaid'
    END`;
}

function salePaymentMethodSql() {
    return `CASE
        WHEN COALESCE(car_transactions.leasing_disbursement, 0) > 0
            OR EXISTS (
                SELECT 1 FROM sale_payments
                WHERE sale_payments.sale_note_id = sale_notes.id
                    AND sale_payments.payment_method = 'leasing'
            ) THEN 'leasing'
        ELSE 'cash'
    END`;
}

function saleVehicle(row, canViewSensitiveData) {
    const vehicle = {
        id: row.car_transaction_id,
        plate_number: row.plate_number,
        manager_name: row.manager_name,
        chassis_number: row.chassis_number,
        engine_number: row.engine_number,
        mileage_km: row.mileage_km,
        brand: row.brand,
        model: row.model,
        year: row.year,
        transmission: row.transmission,
        color: row.color,
        owner_name: row.owner_name,
        buy_date: row.buy_date,
        sell_date: row.sell_date,
        selling_price: row.selling_price,
        purchase_operational: row.purchase_operational,
        other_costs: row.other_costs,
        total_repair_cost: row.total_repair_cost,
        additional_repairs: storedArray(row.additional_repairs),
        tax_items: storedArray(row.tax_items),
        setor_price: row.setor_price,
        opening_price: row.opening_price,
        down_payment: row.down_payment,
        down_payment_date: row.down_payment_date,
        settlement_amount: row.settlement_amount,
        settlement_date: row.settlement_date,
        leasing_disbursement: row.leasing_disbursement,
        leasing_disbursement_date: row.leasing_disbursement_date,
        planned_payment_date: row.planned_payment_date,
        remaining_payment: row.remaining_payment,
        intertine_expenses: storedArray(row.intertine_expenses),
        leasing_options: storedArray(row.leasing_options),
    };

    if (canViewSensitiveData) {
        Object.assign(vehicle, {
            buy_price: row.buy_price,
            total_capital: row.total_capital,
            gross_profit: row.gross_profit,
            showroom_fee: row.showroom_fee,
            total_intertine_expense: row.total_intertine_expense,
            net_profit: row.net_profit,
            leasing_ripan: row.leasing_ripan,
            insurance_ripan: row.insurance_ripan,
            booster_ripan: row.booster_ripan,
        });
    }

    return vehicle;
}

function saleCommissionBreakdown(row) {
    const commissions = {
        kr_jual: 0,
        op_survei: 0,
        fee_cmo: 0,
    };
    const commissionNames = {
        krjual: 'kr_jual',
        opsurvei: 'op_survei',
        feecmo: 'fee_cmo',
    };

    for (const expense of storedArray(row.intertine_expenses)) {
        const key = String(expense.name ?? '')
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');
        const commissionKey = commissionNames[key];

        if (!commissionKey) {
            continue;
        }

        const amount = Number(expense.cost ?? expense.amount ?? 0);
        commissions[commissionKey] = Number.isFinite(amount) ? amount : 0;
    }

    return {
        ...commissions,
        total: commissions.kr_jual + commissions.op_survei + commissions.fee_cmo,
    };
}

function validReportDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00.000Z`);

    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function salesRowsQuery(whereClause) {
    return `SELECT sale_notes.id AS sale_note_id, sale_notes.nama_konsumen,
                   sale_notes.buyer_nik, sale_notes.buyer_phone, sale_notes.buyer_address,
                   sale_notes.created_by, car_transactions.*,
                   car_transactions.id AS car_transaction_id,
                   ${salePaymentMethodSql()} AS payment_method,
                   COALESCE(car_transactions.down_payment, 0)
                       + COALESCE(car_transactions.leasing_disbursement, 0)
                       + COALESCE(car_transactions.settlement_amount, 0) AS total_paid
            FROM sale_notes
            INNER JOIN car_transactions
                ON car_transactions.id = sale_notes.car_transaction_id
            ${whereClause}
            ORDER BY car_transactions.sell_date DESC, sale_notes.id DESC`;
}

function saleReceiptNumber(row) {
    const year = String(row.sell_date ?? '').slice(0, 4) || '0000';

    return `NJM/${year}/${String(row.sale_note_id).padStart(6, '0')}`;
}

function salesReportRow(row, canViewSensitiveData) {
    const vehicle = saleVehicle(row, canViewSensitiveData);
    const paymentStatus = Number(row.remaining_payment ?? 0) <= 0 ? 'lunas' : 'belum_lunas';
    const totalPaid = Number(row.total_paid ?? 0);

    return {
        id: row.sale_note_id,
        receipt_number: saleReceiptNumber(row),
        nama_konsumen: row.nama_konsumen,
        buyer_nik: row.buyer_nik,
        buyer_phone: row.buyer_phone,
        buyer_address: row.buyer_address,
        created_by: row.created_by,
        payment_status: paymentStatus,
        payment_method: row.payment_method,
        total_paid: totalPaid,
        can_view_sensitive_data: canViewSensitiveData,
        car_transaction: {
            ...vehicle,
            total_paid: totalPaid,
            payment_status: paymentStatus,
            payment_method: row.payment_method,
            commissions: canViewSensitiveData ? saleCommissionBreakdown(row) : null,
            net_profit: canViewSensitiveData ? Number(row.net_profit ?? 0) : null,
        },
    };
}

function saleReceiptResponse(row) {
    const remainingPayment = Number(row.remaining_payment ?? 0);

    return {
        id: row.sale_note_id,
        receipt_number: saleReceiptNumber(row),
        transaction_date: row.sell_date,
        buyer: {
            name: row.nama_konsumen,
            nik: row.buyer_nik,
            address: row.buyer_address,
            phone: row.buyer_phone,
        },
        vehicle: {
            id: row.car_transaction_id,
            plate_number: row.plate_number,
            brand: row.brand,
            model: row.model,
            year: row.year,
            color: row.color,
            chassis_number: row.chassis_number,
            engine_number: row.engine_number,
        },
        payment: {
            selling_price: Number(row.selling_price ?? 0),
            down_payment: Number(row.down_payment ?? 0),
            settlement_amount: Number(row.settlement_amount ?? 0),
            leasing_disbursement: Number(row.leasing_disbursement ?? 0),
            total_paid: Number(row.total_paid ?? 0),
            remaining_payment: remainingPayment,
            status: remainingPayment <= 0 ? 'lunas' : 'belum_lunas',
        },
    };
}

function defaultNotaDesignConfig() {
    return {
        ...DEFAULT_NOTA_DESIGN_CONFIG,
        sectionOrder: [...DEFAULT_NOTA_DESIGN_CONFIG.sectionOrder],
        sectionLabels: { ...DEFAULT_NOTA_DESIGN_CONFIG.sectionLabels },
        fieldLabels: { ...DEFAULT_NOTA_DESIGN_CONFIG.fieldLabels },
        customRows: [],
    };
}

function validateNotaDesignConfig(input) {
    const errors = {};

    if (!input || typeof input !== 'object' || Array.isArray(input)) {
        return {
            config: defaultNotaDesignConfig(),
            errors: { design_config: ['Konfigurasi desain nota harus berupa objek.'] },
        };
    }

    const knownSections = ['buyer', 'vehicle', 'payment', 'notes', 'signatures'];
    const defaultConfig = defaultNotaDesignConfig();
    const allowedKeys = new Set(Object.keys(defaultConfig));
    const unknownKeys = Object.keys(input).filter((key) => !allowedKeys.has(key));

    if (unknownKeys.length > 0) {
        errors.design_config = [`Properti desain tidak dikenal: ${unknownKeys.join(', ')}.`];
    }

    const config = {
        ...defaultConfig,
        ...input,
        sectionLabels: { ...defaultConfig.sectionLabels },
        fieldLabels: { ...defaultConfig.fieldLabels },
        sectionOrder: Array.isArray(input.sectionOrder)
            ? [...input.sectionOrder]
            : [...defaultConfig.sectionOrder],
        customRows: [],
    };

    if (input.sectionLabels && typeof input.sectionLabels === 'object' && !Array.isArray(input.sectionLabels)) {
        const unknownLabels = Object.keys(input.sectionLabels)
            .filter((key) => !knownSections.includes(key));

        if (unknownLabels.length > 0) {
            errors.sectionLabels = ['Terdapat label bagian yang tidak dikenal.'];
        }

        for (const section of knownSections) {
            if (input.sectionLabels[section] !== undefined) {
                config.sectionLabels[section] = input.sectionLabels[section];
            }
        }
    } else if (input.sectionLabels !== undefined) {
        errors.sectionLabels = ['Label bagian harus berupa objek.'];
    }

    const fieldNames = Object.keys(defaultConfig.fieldLabels);
    if (input.fieldLabels && typeof input.fieldLabels === 'object' && !Array.isArray(input.fieldLabels)) {
        const unknownLabels = Object.keys(input.fieldLabels)
            .filter((key) => !fieldNames.includes(key));

        if (unknownLabels.length > 0) {
            errors.fieldLabels = ['Terdapat label field yang tidak dikenal.'];
        }

        for (const field of fieldNames) {
            if (input.fieldLabels[field] !== undefined) {
                config.fieldLabels[field] = input.fieldLabels[field];
            }
        }
    } else if (input.fieldLabels !== undefined) {
        errors.fieldLabels = ['Label field harus berupa objek.'];
    }

    for (const [field, values] of [
        ['preset', ['modern-clean', 'classic-formal', 'minimal-receipt', 'custom']],
        ['headerLayout', ['left', 'center', 'split']],
        ['logoSize', ['small', 'medium', 'large']],
        ['dataLayout', ['grid', 'table']],
        ['fontFamily', ['sans', 'serif', 'mono']],
        ['borderStyle', ['none', 'thin', 'double']],
        ['spacing', ['compact', 'normal', 'relaxed']],
        ['paperSize', ['a4', 'a5-landscape', 'f4']],
    ]) {
        if (!values.includes(config[field])) {
            errors[field] = [`Pilihan ${field} tidak valid.`];
        }
    }

    if (typeof config.logoUrl !== 'string' || config.logoUrl.length > 420000) {
        errors.logoUrl = ['Logo harus berupa URL atau data URL gambar dengan ukuran maksimal 420 KB.'];
    } else if (config.logoUrl !== '') {
        let isValidLogoUrl = false;

        if (/^data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2}$/i.test(config.logoUrl)) {
            isValidLogoUrl = true;
        } else {
            try {
                const logoUrl = new URL(config.logoUrl);
                isValidLogoUrl = ['http:', 'https:'].includes(logoUrl.protocol)
                    && logoUrl.hostname !== '';
            } catch {
                isValidLogoUrl = false;
            }
        }

        if (!isValidLogoUrl) {
            errors.logoUrl = ['Logo harus menggunakan URL HTTP(S) atau data URL PNG, JPEG, atau WebP.'];
        }
    }

    for (const field of ['logoVisible']) {
        if (typeof config[field] !== 'boolean') {
            errors[field] = ['Visibilitas logo harus bernilai true atau false.'];
        }
    }

    if (input.sectionOrder !== undefined
        && (!Array.isArray(input.sectionOrder)
            || input.sectionOrder.length !== knownSections.length
            || new Set(input.sectionOrder).size !== knownSections.length
            || input.sectionOrder.some((section) => !knownSections.includes(section)))) {
        errors.sectionOrder = ['Urutan harus memuat setiap bagian nota tepat satu kali.'];
    }

    for (const [field, labels] of [
        ['sectionLabels', config.sectionLabels],
        ['fieldLabels', config.fieldLabels],
    ]) {
        const maxLength = field === 'sectionLabels' ? 100 : 80;

        for (const [label, value] of Object.entries(labels)) {
            if (typeof value !== 'string' || value.trim().length > maxLength) {
                errors[field] = [`Label ${label} harus berupa teks maksimal ${maxLength} karakter.`];
            } else {
                labels[label] = value.trim();
            }
        }
    }

    if (input.customRows !== undefined
        && (!Array.isArray(input.customRows) || input.customRows.length > 20)) {
        errors.customRows = ['Baris kustom harus berupa daftar maksimal 20 baris.'];
    } else if (Array.isArray(input.customRows)) {
        config.customRows = input.customRows.map((row, index) => {
            if (!row || typeof row !== 'object' || Array.isArray(row)) {
                errors.customRows = [`Baris kustom ke-${index + 1} tidak valid.`];

                return { label: '', value: '' };
            }

            const label = typeof row.label === 'string' ? row.label.trim() : '';
            const value = typeof row.value === 'string' ? row.value.trim() : '';

            if (Object.keys(row).some((key) => !['label', 'value'].includes(key))
                || label === ''
                || label.length > 80
                || value.length > 400) {
                errors.customRows = [`Label atau nilai baris kustom ke-${index + 1} tidak valid.`];
            }

            return { label, value };
        });
    }

    for (const field of ['accentColor', 'headerBackground']) {
        if (typeof config[field] !== 'string' || !/^#[0-9a-f]{6}$/i.test(config[field])) {
            errors[field] = [`${field} harus berupa kode warna hex enam digit.`];
        }
    }

    if (![2, 3].includes(config.signatureColumns)) {
        errors.signatureColumns = ['Kolom tanda tangan harus berjumlah dua atau tiga.'];
    }

    return { config, errors };
}

function notaSettingsResponse(row) {
    if (!row) {
        return {
            ...DEFAULT_NOTA_SETTINGS,
            design_config: defaultNotaDesignConfig(),
        };
    }

    let storedDesignConfig = defaultNotaDesignConfig();
    if (row.design_config) {
        const parsedDesignConfig = typeof row.design_config === 'string'
            ? JSON.parse(row.design_config)
            : row.design_config;
        const validatedDesignConfig = validateNotaDesignConfig(parsedDesignConfig);

        if (Object.keys(validatedDesignConfig.errors).length > 0) {
            throw new Error('Stored nota design configuration is invalid.');
        }

        storedDesignConfig = validatedDesignConfig.config;
    }

    return {
        showroomName: row.showroom_name,
        subtitle: row.subtitle,
        showroomAddress: row.showroom_address,
        showroomPhone: row.showroom_phone,
        invoicePrefix: row.invoice_prefix,
        footerNote: row.footer_note,
        signatoryName: row.signatory_name,
        signatoryTitle: row.signatory_title,
        showBuyerNik: Number(row.show_buyer_nik) === 1,
        showVehicleIdentifiers: Number(row.show_vehicle_identifiers) === 1,
        showPaymentBreakdown: Number(row.show_payment_breakdown) === 1,
        showPaymentStatus: Number(row.show_payment_status) === 1,
        showSignatures: Number(row.show_signatures) === 1,
        accentTheme: row.accent_theme,
        paperSize: row.paper_size,
        design_config: storedDesignConfig,
    };
}

function mergeNotaDesignConfig(current, incoming) {
    if (incoming === undefined) {
        return current;
    }
    if (!incoming || typeof incoming !== 'object' || Array.isArray(incoming)) {
        return incoming;
    }

    return {
        ...current,
        ...incoming,
        sectionLabels: incoming.sectionLabels === undefined
            ? current.sectionLabels
            : incoming.sectionLabels && typeof incoming.sectionLabels === 'object'
                && !Array.isArray(incoming.sectionLabels)
                ? { ...current.sectionLabels, ...incoming.sectionLabels }
                : incoming.sectionLabels,
        fieldLabels: incoming.fieldLabels === undefined
            ? current.fieldLabels
            : incoming.fieldLabels && typeof incoming.fieldLabels === 'object'
                && !Array.isArray(incoming.fieldLabels)
                ? { ...current.fieldLabels, ...incoming.fieldLabels }
                : incoming.fieldLabels,
        sectionOrder: incoming.sectionOrder === undefined
            ? current.sectionOrder
            : incoming.sectionOrder,
        customRows: incoming.customRows === undefined
            ? current.customRows
            : incoming.customRows,
    };
}

function validateNotaSettings(input) {
    const settings = { ...input };
    const errors = {};
    const textFields = [
        ['showroomName', 'Nama showroom', 120, true],
        ['subtitle', 'Subjudul', 180, false],
        ['showroomAddress', 'Alamat showroom', 500, false],
        ['showroomPhone', 'Nomor telepon showroom', 50, false],
        ['invoicePrefix', 'Prefix nomor nota', 12, true],
        ['footerNote', 'Catatan kaki', 1500, false],
        ['signatoryName', 'Nama penandatangan', 120, false],
        ['signatoryTitle', 'Jabatan penandatangan', 120, false],
    ];

    for (const [field, label, maxLength, required] of textFields) {
        if (typeof settings[field] !== 'string') {
            errors[field] = [`${label} harus berupa teks.`];
            continue;
        }

        settings[field] = settings[field].trim();
        if (settings[field].length > maxLength) {
            errors[field] = [`${label} maksimal ${maxLength} karakter.`];
        } else if (required && settings[field] === '') {
            errors[field] = [`${label} wajib diisi.`];
        }
    }

    if (typeof settings.invoicePrefix === 'string'
        && !/^[A-Za-z0-9_-]{1,12}$/.test(settings.invoicePrefix)) {
        errors.invoicePrefix = ['Prefix nota hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.'];
    }

    for (const field of [
        'showBuyerNik',
        'showVehicleIdentifiers',
        'showPaymentBreakdown',
        'showPaymentStatus',
        'showSignatures',
    ]) {
        if (typeof settings[field] !== 'boolean') {
            errors[field] = ['Pilihan visibilitas harus bernilai true atau false.'];
        }
    }

    if (!['emerald', 'classic', 'navy'].includes(settings.accentTheme)) {
        errors.accentTheme = ['Tema aksen nota tidak valid.'];
    }
    if (!['a4', 'a5-landscape'].includes(settings.paperSize)) {
        errors.paperSize = ['Ukuran kertas nota tidak valid.'];
    }

    const designValidation = validateNotaDesignConfig(settings.design_config);
    settings.design_config = designValidation.config;

    for (const [field, messages] of Object.entries(designValidation.errors)) {
        errors[`design_config.${field}`] = messages;
    }

    return { settings, errors };
}

async function getSalesReport(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat laporan penjualan.' }, 403);
    }

    const search = (context.req.query('search') ?? '').trim();
    const startDate = context.req.query('start_date') ?? '';
    const endDate = context.req.query('end_date') ?? '';
    const paymentStatus = context.req.query('payment_status') ?? 'all';
    const paymentMethod = context.req.query('payment_method') ?? 'all';
    const invalidStartDate = startDate !== '' && !validReportDate(startDate);
    const invalidEndDate = endDate !== '' && !validReportDate(endDate);
    const invalidDateRange = startDate !== '' && endDate !== '' && startDate > endDate;
    const invalidPaymentStatus = !['all', 'lunas', 'belum_lunas'].includes(paymentStatus);
    const invalidPaymentMethod = !['all', 'cash', 'leasing'].includes(paymentMethod);

    if (search.length > 100
        || invalidStartDate
        || invalidEndDate
        || invalidDateRange
        || invalidPaymentStatus
        || invalidPaymentMethod) {
        return context.json({
            message: 'Filter laporan penjualan tidak valid.',
            errors: {
                search: search.length > 100 ? ['Pencarian maksimal 100 karakter.'] : undefined,
                start_date: invalidStartDate ? ['Tanggal mulai tidak valid.'] : undefined,
                end_date: invalidEndDate ? ['Tanggal akhir tidak valid.'] : undefined,
                date_range: invalidDateRange ? ['Tanggal mulai harus sebelum atau sama dengan tanggal akhir.'] : undefined,
                payment_status: invalidPaymentStatus ? ['Status pembayaran tidak valid.'] : undefined,
                payment_method: invalidPaymentMethod ? ['Metode pembayaran tidak valid.'] : undefined,
            },
        }, 422);
    }

    const conditions = ['car_transactions.sell_date IS NOT NULL'];
    const parameters = [];

    if (user.role !== 'master') {
        conditions.push('sale_notes.created_by = ?');
        parameters.push(user.id);
    }
    if (search !== '') {
        const searchPattern = `%${search.toLowerCase()}%`;
        conditions.push(`(
            LOWER(sale_notes.nama_konsumen) LIKE ?
            OR LOWER(car_transactions.plate_number) LIKE ?
            OR LOWER(car_transactions.brand || ' ' || car_transactions.model) LIKE ?
        )`);
        parameters.push(searchPattern, searchPattern, searchPattern);
    }
    if (startDate !== '') {
        conditions.push('car_transactions.sell_date >= ?');
        parameters.push(startDate);
    }
    if (endDate !== '') {
        conditions.push('car_transactions.sell_date <= ?');
        parameters.push(endDate);
    }
    if (paymentStatus === 'lunas') {
        conditions.push('COALESCE(car_transactions.remaining_payment, 0) <= 0');
    } else if (paymentStatus === 'belum_lunas') {
        conditions.push('COALESCE(car_transactions.remaining_payment, 0) > 0');
    }
    if (paymentMethod === 'leasing') {
        conditions.push(`(${salePaymentMethodSql()}) = 'leasing'`);
    } else if (paymentMethod === 'cash') {
        conditions.push(`(${salePaymentMethodSql()}) = 'cash'`);
    }

    try {
        const result = await context.env.DB.prepare(
            salesRowsQuery(`WHERE ${conditions.join(' AND ')}`),
        ).bind(...parameters).all();
        const canViewSensitiveData = user.role === 'master';
        const data = (result.results ?? []).map((row) =>
            salesReportRow(row, canViewSensitiveData));
        const summary = data.reduce((totals, sale) => {
            totals.total_units += 1;
            totals.total_omzet += Number(sale.car_transaction.selling_price ?? 0);
            totals.total_paid += sale.total_paid;
            totals.total_receivable += Math.max(0, Number(sale.car_transaction.remaining_payment ?? 0));
            if (sale.payment_status === 'lunas') {
                totals.paid_units += 1;
            } else {
                totals.unpaid_units += 1;
            }
            if (canViewSensitiveData) {
                totals.total_net_profit += Number(sale.car_transaction.net_profit ?? 0);
            }

            return totals;
        }, {
            total_units: 0,
            total_omzet: 0,
            total_paid: 0,
            total_receivable: 0,
            paid_units: 0,
            unpaid_units: 0,
            total_net_profit: canViewSensitiveData ? 0 : null,
        });

        return context.json({
            summary,
            data,
            can_view_sensitive_data: canViewSensitiveData,
            filters: {
                search,
                start_date: startDate || null,
                end_date: endDate || null,
                payment_status: paymentStatus,
                payment_method: paymentMethod,
            },
        });
    } catch (error) {
        console.error('API sales report query failed.', error);

        return context.json({ message: 'Laporan penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

async function getSaleReceipts(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat nota penjualan.' }, 403);
    }

    const conditions = ['car_transactions.sell_date IS NOT NULL'];
    const parameters = [];

    if (user.role !== 'master') {
        conditions.push('sale_notes.created_by = ?');
        parameters.push(user.id);
    }

    try {
        const result = await context.env.DB.prepare(
            salesRowsQuery(`WHERE ${conditions.join(' AND ')}`),
        ).bind(...parameters).all();

        return context.json({
            data: (result.results ?? []).map(saleReceiptResponse),
        });
    } catch (error) {
        console.error('API sale receipt list query failed.', error);

        return context.json({ message: 'Daftar nota penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

async function getSaleReceipt(context) {
    const user = context.get('user');
    const id = Number(context.req.param('id'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat nota penjualan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Nota penjualan tidak ditemukan.' }, 404);
    }

    try {
        const sale = await findSale(context, id);

        if (!sale) {
            return context.json({ message: 'Nota penjualan tidak ditemukan.' }, 404);
        }

        return context.json({ data: saleReceiptResponse(sale) });
    } catch (error) {
        console.error('API sale receipt detail query failed.', error);

        return context.json({ message: 'Nota penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

async function getNotaSettings(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan mengatur template nota.' }, 403);
    }

    try {
        const row = await context.env.DB.prepare(
            `SELECT showroom_name, subtitle, showroom_address, showroom_phone, invoice_prefix,
                    footer_note, signatory_name, signatory_title, show_buyer_nik,
                    show_vehicle_identifiers, show_payment_breakdown, show_payment_status,
                    show_signatures, accent_theme, paper_size, design_config
             FROM nota_settings
             WHERE id = 1`,
        ).first();

        return context.json({ data: notaSettingsResponse(row) });
    } catch (error) {
        console.error('API nota settings query failed.', error);

        return context.json({ message: 'Pengaturan nota tidak dapat dimuat saat ini.' }, 500);
    }
}

async function updateNotaSettings(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan mengatur template nota.' }, 403);
    }

    let payload;

    try {
        payload = await context.req.json();
    } catch {
        return context.json({ message: 'Payload pengaturan nota harus berupa JSON yang valid.' }, 400);
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Payload pengaturan nota tidak valid.' }, 422);
    }

    const allowedFields = new Set(Object.keys(DEFAULT_NOTA_SETTINGS));
    const unknownFields = Object.keys(payload).filter((field) => !allowedFields.has(field));

    if (unknownFields.length > 0) {
        return context.json({
            message: 'Payload pengaturan nota berisi field yang tidak dikenal.',
            errors: { fields: unknownFields },
        }, 422);
    }

    try {
        const currentRow = await context.env.DB.prepare(
            `SELECT showroom_name, subtitle, showroom_address, showroom_phone, invoice_prefix,
                    footer_note, signatory_name, signatory_title, show_buyer_nik,
                    show_vehicle_identifiers, show_payment_breakdown, show_payment_status,
                    show_signatures, accent_theme, paper_size, design_config
             FROM nota_settings
             WHERE id = 1`,
        ).first();
        const currentSettings = notaSettingsResponse(currentRow);
        const { settings, errors } = validateNotaSettings({
            ...currentSettings,
            ...payload,
            design_config: mergeNotaDesignConfig(
                currentSettings.design_config,
                payload.design_config,
            ),
        });

        if (Object.keys(errors).length > 0) {
            return context.json({
                message: 'Pengaturan nota tidak valid.',
                errors,
            }, 422);
        }

        const updatedAt = new Date().toISOString();
        await context.env.DB.prepare(
            `INSERT INTO nota_settings (
                id, showroom_name, subtitle, showroom_address, showroom_phone, invoice_prefix,
                footer_note, signatory_name, signatory_title, show_buyer_nik,
                show_vehicle_identifiers, show_payment_breakdown, show_payment_status,
                show_signatures, accent_theme, paper_size, design_config, updated_by, updated_at
             ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(id) DO UPDATE SET
                showroom_name = excluded.showroom_name,
                subtitle = excluded.subtitle,
                showroom_address = excluded.showroom_address,
                showroom_phone = excluded.showroom_phone,
                invoice_prefix = excluded.invoice_prefix,
                footer_note = excluded.footer_note,
                signatory_name = excluded.signatory_name,
                signatory_title = excluded.signatory_title,
                show_buyer_nik = excluded.show_buyer_nik,
                show_vehicle_identifiers = excluded.show_vehicle_identifiers,
                show_payment_breakdown = excluded.show_payment_breakdown,
                show_payment_status = excluded.show_payment_status,
                show_signatures = excluded.show_signatures,
                accent_theme = excluded.accent_theme,
                paper_size = excluded.paper_size,
                design_config = excluded.design_config,
                updated_by = excluded.updated_by,
                updated_at = excluded.updated_at`,
        ).bind(
            settings.showroomName,
            settings.subtitle,
            settings.showroomAddress,
            settings.showroomPhone,
            settings.invoicePrefix,
            settings.footerNote,
            settings.signatoryName,
            settings.signatoryTitle,
            Number(settings.showBuyerNik),
            Number(settings.showVehicleIdentifiers),
            Number(settings.showPaymentBreakdown),
            Number(settings.showPaymentStatus),
            Number(settings.showSignatures),
            settings.accentTheme,
            settings.paperSize,
            JSON.stringify(settings.design_config),
            user.id,
            updatedAt,
        ).run();

        return context.json({
            message: 'Pengaturan nota berhasil disimpan.',
            data: settings,
        });
    } catch (error) {
        console.error('API nota settings update failed.', error);

        return context.json({ message: 'Pengaturan nota tidak dapat disimpan saat ini.' }, 500);
    }
}

async function getSales(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan melihat laporan penjualan.' }, 403);
    }

    const status = context.req.query('status') ?? 'all';
    const pageValue = context.req.query('page') ?? '1';
    const page = Number(pageValue);

    if (!['all', 'unpaid', 'leasing', 'paid'].includes(status)
        || !/^\d+$/.test(pageValue)
        || !Number.isSafeInteger(page)
        || page < 1) {
        return context.json({ message: 'Filter laporan penjualan tidak valid.' }, 422);
    }

    const canViewSensitiveData = user.role === 'master';
    const conditions = ['car_transactions.sell_date IS NOT NULL'];
    const parameters = [];

    if (!canViewSensitiveData) {
        conditions.push('sale_notes.created_by = ?');
        parameters.push(user.id);
    }
    if (status !== 'all') {
        conditions.push(`${saleStatusSql()} = ?`);
        parameters.push(status);
    }

    const whereClause = `WHERE ${conditions.join(' AND ')}`;
    const offset = (page - 1) * 10;

    try {
        const [rowsResult, countResult] = await Promise.all([
            context.env.DB.prepare(
                `SELECT sale_notes.id AS sale_note_id, sale_notes.nama_konsumen, sale_notes.buyer_nik,
                        sale_notes.buyer_phone, sale_notes.buyer_address, sale_notes.created_by,
                        car_transactions.*,
                        car_transactions.id AS car_transaction_id,
                        ${saleStatusSql()} AS payment_status,
                        car_transactions.down_payment + car_transactions.leasing_disbursement
                            + car_transactions.settlement_amount AS total_paid
                 FROM sale_notes
                 INNER JOIN car_transactions
                    ON car_transactions.id = sale_notes.car_transaction_id
                 ${whereClause}
                 ORDER BY car_transactions.sell_date DESC, sale_notes.id DESC
                 LIMIT ? OFFSET ?`,
            ).bind(...parameters, 10, offset).all(),
            context.env.DB.prepare(
                `SELECT COUNT(*) AS total
                 FROM sale_notes
                 INNER JOIN car_transactions
                    ON car_transactions.id = sale_notes.car_transaction_id
                 ${whereClause}`,
            ).bind(...parameters).first(),
        ]);
        const rows = rowsResult.results ?? [];
        const total = Number(countResult?.total ?? 0);

        return context.json({
            data: rows.map((row) => ({
                id: row.sale_note_id,
                nama_konsumen: row.nama_konsumen,
                buyer_nik: row.buyer_nik,
                buyer_phone: row.buyer_phone,
                buyer_address: row.buyer_address,
                created_by: row.created_by,
                payment_status: row.payment_status,
                total_paid: row.total_paid,
                can_view_sensitive_data: canViewSensitiveData,
                car_transaction: saleVehicle(row, canViewSensitiveData),
                payments: [],
            })),
            links: {},
            meta: {
                current_page: page,
                from: total === 0 ? null : offset + 1,
                last_page: Math.max(1, Math.ceil(total / 10)),
                per_page: 10,
                to: total === 0 ? null : Math.min(offset + rows.length, total),
                total,
            },
            can_view_sensitive_data: canViewSensitiveData,
            status,
        });
    } catch (error) {
        console.error('API sales query failed.', error);

        return context.json({ message: 'Laporan penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

async function getSaleForm(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan membuat transaksi penjualan.' }, 403);
    }

    try {
        const vehicles = await context.env.DB.prepare(
            `SELECT * FROM car_transactions
             WHERE sell_date IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM sale_notes
                    WHERE sale_notes.car_transaction_id = car_transactions.id
                )
             ORDER BY plate_number`,
        ).all();
        const vehicleRows = vehicles.results ?? [];
        const photosByVehicleId = new Map(
            vehicleRows.map((vehicle) => [vehicle.id, []]),
        );

        for (let offset = 0; offset < vehicleRows.length; offset += 90) {
            const vehicleIds = vehicleRows.slice(offset, offset + 90).map((vehicle) => vehicle.id);
            const photoPlaceholders = vehicleIds.map(() => '?').join(', ');
            const photos = await context.env.DB.prepare(
                `SELECT id, car_transaction_id, path, is_cover, position
                 FROM vehicle_photos
                 WHERE car_transaction_id IN (${photoPlaceholders})
                 ORDER BY car_transaction_id, position, id`,
            ).bind(...vehicleIds).all();

            for (const photo of photos.results ?? []) {
                photosByVehicleId.get(photo.car_transaction_id)?.push(photo);
            }
        }

        const availableVehicles = vehicleRows.map((transaction) => {
            const vehicle = transactionResponse(
                context,
                transaction,
                photosByVehicleId.get(transaction.id) ?? [],
            );

            return {
                ...vehicle,
                mileage: vehicle.mileage_km,
                stnk_name: vehicle.owner_name,
                handler_name: vehicle.manager_name,
                person_in_charge: vehicle.manager_name,
                purchase_date: vehicle.buy_date,
            };
        });
        const selectedVehicleId = Number(context.req.query('vehicle'));
        const selectedVehicle = Number.isSafeInteger(selectedVehicleId) && selectedVehicleId > 0
            ? availableVehicles.find((vehicle) => vehicle.id === selectedVehicleId) ?? null
            : null;

        return context.json({
            data: {
                vehicles: availableVehicles,
                selected_vehicle: selectedVehicle,
                has_available_vehicles: availableVehicles.length > 0,
                can_view_sensitive_data: user.role === 'master',
            },
        });
    } catch (error) {
        console.error('API sale form query failed.', error);

        return context.json({ message: 'Form penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

function marketingVehicle(context, transaction, photos = []) {
    const taxStatus = dashboardRow(
        context,
        transaction,
        false,
        new Date().toISOString().slice(0, 10),
    ).tax_status;
    const coverPhotoPath = transaction.vehicle_photo_path
        || photos.find((photo) => Boolean(photo.is_cover))?.path
        || photos[0]?.path
        || null;

    return {
        id: transaction.id,
        plate_number: transaction.plate_number,
        brand: transaction.brand,
        model: transaction.model,
        year: transaction.year,
        transmission: transaction.transmission,
        color: transaction.color,
        opening_price: transaction.opening_price,
        vehicle_photo_url: coverPhotoPath
            ? publicPhotoUrl(context, coverPhotoPath)
            : null,
        vehicle_photos: photos.map((photo) => ({
            id: photo.id,
            path: photo.path,
            url: publicPhotoUrl(context, photo.path),
            is_cover: Boolean(photo.is_cover),
            position: photo.position,
        })),
        tax_status: taxStatus,
        buy_date: transaction.buy_date,
        chassis_number: transaction.chassis_number,
        engine_number: transaction.engine_number,
        mileage_km: transaction.mileage_km,
        owner_name: transaction.owner_name,
        leasing_options: storedArray(transaction.leasing_options),
    };
}

function availableVehicleWhere() {
    return `sell_date IS NULL
        AND NOT EXISTS (
            SELECT 1 FROM sale_notes
            WHERE sale_notes.car_transaction_id = car_transactions.id
        )`;
}

async function getMarketingVehicles(context) {
    const user = context.get('user');

    if (user.role !== 'marketing') {
        return context.json({ message: 'Hanya akun Marketing yang dapat melihat katalog stok.' }, 403);
    }

    const search = (context.req.query('q') ?? '').trim();
    const transmission = context.req.query('transmission') ?? '';
    const yearFromValue = context.req.query('year_from') ?? '';
    const yearToValue = context.req.query('year_to') ?? '';
    const pageValue = context.req.query('page') ?? '1';
    const yearFrom = yearFromValue === '' ? null : Number(yearFromValue);
    const yearTo = yearToValue === '' ? null : Number(yearToValue);
    const page = Number(pageValue);
    const errors = {};

    if (search.length > 255) {
        errors.q = ['Pencarian maksimal 255 karakter.'];
    }
    if (transmission && !['manual', 'otomatis', 'automatic'].includes(transmission)) {
        errors.transmission = ['Filter transmisi tidak valid.'];
    }
    if (yearFrom !== null && (!Number.isSafeInteger(yearFrom) || yearFrom < 1900 || yearFrom > 2100)) {
        errors.year_from = ['Tahun mulai harus antara 1900 dan 2100.'];
    }
    if (yearTo !== null && (!Number.isSafeInteger(yearTo) || yearTo < 1900 || yearTo > 2100)) {
        errors.year_to = ['Tahun sampai harus antara 1900 dan 2100.'];
    } else if (yearFrom !== null && yearTo !== null && yearTo < yearFrom) {
        errors.year_to = ['Tahun sampai harus sama atau setelah tahun mulai.'];
    }
    if (!/^\d+$/.test(pageValue) || !Number.isSafeInteger(page) || page < 1) {
        errors.page = ['Halaman harus berupa bilangan bulat minimal 1.'];
    }
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Filter katalog tidak valid.', errors }, 422);
    }

    const conditions = [availableVehicleWhere()];
    const parameters = [];

    if (search) {
        conditions.push('(plate_number LIKE ? OR brand LIKE ? OR model LIKE ?)');
        const pattern = `%${search}%`;
        parameters.push(pattern, pattern, pattern);
    }
    if (transmission) {
        conditions.push('LOWER(transmission) = ?');
        parameters.push(transmission === 'automatic' ? 'otomatis' : transmission);
    }
    if (yearFrom !== null) {
        conditions.push('year >= ?');
        parameters.push(yearFrom);
    }
    if (yearTo !== null) {
        conditions.push('year <= ?');
        parameters.push(yearTo);
    }

    const where = `WHERE ${conditions.join(' AND ')}`;
    const offset = (page - 1) * 12;

    try {
        const [rowsResult, countResult] = await Promise.all([
            context.env.DB.prepare(
                `SELECT id, plate_number, brand, model, year, transmission, color,
                        opening_price, vehicle_photo_path, tax_items, buy_date,
                        chassis_number, engine_number, mileage_km, owner_name, leasing_options
                 FROM car_transactions ${where}
                 ORDER BY plate_number, id
                 LIMIT ? OFFSET ?`,
            ).bind(...parameters, 12, offset).all(),
            context.env.DB.prepare(
                `SELECT COUNT(*) AS total FROM car_transactions ${where}`,
            ).bind(...parameters).first(),
        ]);
        const rows = rowsResult.results ?? [];
        const total = Number(countResult?.total ?? 0);
        const photosResult = rows.length > 0
            ? await context.env.DB.prepare(
                `SELECT id, car_transaction_id, path, is_cover, position
                 FROM vehicle_photos
                 WHERE car_transaction_id IN (${rows.map(() => '?').join(', ')})
                 ORDER BY car_transaction_id, position, id`,
            ).bind(...rows.map((row) => row.id)).all()
            : { results: [] };
        const photosByTransactionId = new Map();

        for (const photo of photosResult.results ?? []) {
            const vehiclePhotos = photosByTransactionId.get(photo.car_transaction_id) ?? [];
            vehiclePhotos.push(photo);
            photosByTransactionId.set(photo.car_transaction_id, vehiclePhotos);
        }

        return context.json({
            data: rows.map((row) =>
                marketingVehicle(context, row, photosByTransactionId.get(row.id) ?? [])),
            links: {},
            meta: {
                current_page: page,
                from: total === 0 ? null : offset + 1,
                last_page: Math.max(1, Math.ceil(total / 12)),
                per_page: 12,
                to: total === 0 ? null : Math.min(offset + rows.length, total),
                total,
            },
            filters: {
                q: search || null,
                transmission: transmission || null,
                year_from: yearFrom,
                year_to: yearTo,
            },
            summary: { available_count: total },
        });
    } catch (error) {
        console.error('API marketing catalog query failed.', error);

        return context.json({ message: 'Katalog stok tidak dapat dimuat saat ini.' }, 500);
    }
}

async function getMarketingVehicle(context) {
    const user = context.get('user');
    const id = Number(context.req.param('id'));

    if (user.role !== 'marketing') {
        return context.json({ message: 'Hanya akun Marketing yang dapat melihat katalog stok.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Kendaraan tidak ditemukan.' }, 404);
    }

    try {
        const transaction = await context.env.DB.prepare(
            `SELECT id, plate_number, brand, model, year, transmission, color,
                    opening_price, vehicle_photo_path, tax_items, buy_date,
                    chassis_number, engine_number, mileage_km, owner_name, leasing_options
             FROM car_transactions
             WHERE id = ? AND ${availableVehicleWhere()}
             LIMIT 1`,
        ).bind(id).first();

        if (!transaction) {
            return context.json({ message: 'Kendaraan tidak ditemukan.' }, 404);
        }

        const photos = await context.env.DB.prepare(
            `SELECT id, path, is_cover, position
             FROM vehicle_photos
             WHERE car_transaction_id = ?
             ORDER BY position, id`,
        ).bind(id).all();

        return context.json({
            data: marketingVehicle(context, transaction, photos.results ?? []),
        });
    } catch (error) {
        console.error('API marketing vehicle detail query failed.', error);

        return context.json({ message: 'Detail kendaraan tidak dapat dimuat saat ini.' }, 500);
    }
}

function parseNumber(value) {
    if (value === null || value === undefined) {
        return 0;
    }

    if (typeof value === 'number') {
        return value;
    }

    if (typeof value !== 'string') {
        return Number.NaN;
    }

    const rawValue = value.trim().replace(/^Rp\s*/i, '').replace(/\s/g, '');

    if (rawValue === '') {
        return 0;
    }

    const match = rawValue.match(/^(-?)(\d[\d.,]*)$/);

    if (!match) {
        return Number.NaN;
    }

    const [, sign, numericValue] = match;
    let normalized = numericValue;

    if (numericValue.includes(',') && numericValue.includes('.')) {
        if (numericValue.lastIndexOf(',') > numericValue.lastIndexOf('.')) {
            if (!/^\d{1,3}(?:\.\d{3})*,\d+$/.test(numericValue)) {
                return Number.NaN;
            }

            normalized = numericValue.replace(/\./g, '').replace(',', '.');
        } else {
            if (!/^\d{1,3}(?:,\d{3})*\.\d+$/.test(numericValue)) {
                return Number.NaN;
            }

            normalized = numericValue.replace(/,/g, '');
        }
    } else if (numericValue.includes(',')) {
        if (/^\d{1,3}(?:,\d{3})+$/.test(numericValue)) {
            normalized = numericValue.replace(/,/g, '');
        } else if (/^\d+,\d+$/.test(numericValue)) {
            normalized = numericValue.replace(',', '.');
        } else {
            return Number.NaN;
        }
    } else if (numericValue.includes('.')) {
        if (/^\d{1,3}(?:\.\d{3})+$/.test(numericValue)) {
            normalized = numericValue.replace(/\./g, '');
        } else if (!/^\d+(?:\.\d+)?$/.test(numericValue)) {
            return Number.NaN;
        }
    }

    return Number(`${sign}${normalized}`);
}

function validateSalePayload(payload) {
    const errors = {};
    const text = (key, { required = false, maxLength = 255 } = {}) => {
        const value = payload[key];
        const normalized = typeof value === 'string' || typeof value === 'number'
            ? String(value).trim()
            : '';

        if ((required && !normalized) || normalized.length > maxLength) {
            errors[key] = [required ? 'Wajib diisi.' : `Maksimal ${maxLength} karakter.`];
        }

        return normalized || null;
    };
    const amount = (key) => {
        const normalized = parseNumber(payload[key]);

        if (!Number.isFinite(normalized) || normalized < 0 || normalized > 9999999999999.99) {
            errors[key] = ['Wajib berupa angka antara 0 hingga 9.999.999.999.999,99.'];
            return 0;
        }

        return normalized;
    };
    const parseDate = (key, { required = false } = {}) => {
        const value = typeof payload[key] === 'string' ? payload[key].trim() : '';

        if ((required && !value) || (value && !isValidDate(value))) {
            errors[key] = [required ? 'Tanggal wajib diisi dengan format yang valid.' : 'Tanggal tidak valid.'];
            return null;
        }

        return value || null;
    };
    const vehicleId = Number(payload.car_transaction_id);

    if (!Number.isSafeInteger(vehicleId) || vehicleId < 1) {
        errors.car_transaction_id = ['Pilih kendaraan yang akan dijual.'];
    }

    const values = {
        car_transaction_id: vehicleId,
        nama_konsumen: text('nama_konsumen', { required: true }),
        buyer_nik: text('buyer_nik', { maxLength: 16 }),
        buyer_phone: text('buyer_phone', { maxLength: 30 }),
        buyer_address: text('buyer_address', { maxLength: 2000 }),
        selling_price: amount('selling_price'),
        down_payment: amount('down_payment'),
        down_payment_date: parseDate('down_payment_date'),
        settlement_amount: amount('settlement_amount'),
        settlement_date: parseDate('settlement_date'),
        leasing_disbursement: amount('leasing_disbursement'),
        leasing_disbursement_date: parseDate('leasing_disbursement_date'),
        sell_date: parseDate('sell_date', { required: true }),
        planned_payment_date: parseDate('planned_payment_date'),
    };

    if (values.buyer_nik && !/^\d{16}$/.test(values.buyer_nik)) {
        errors.buyer_nik = ['NIK harus terdiri dari 16 digit.'];
    }

    if (Array.isArray(payload.intertine_expenses) && payload.intertine_expenses.length <= 50) {
        values.intertine_expenses = [];

        for (const [index, expense] of payload.intertine_expenses.entries()) {
            const name = typeof expense?.name === 'string' ? expense.name.trim() : '';
            const cost = parseNumber(expense?.cost);

            if (!expense || typeof expense !== 'object' || Array.isArray(expense)
                || !name || name.length > 100 || !Number.isFinite(cost) || cost < 0
                || cost > 9999999999999.99) {
                errors.intertine_expenses = [`Rincian biaya ke-${index + 1} tidak valid.`];
                break;
            }

            values.intertine_expenses.push({ name, cost });
        }
    } else if (payload.intertine_expenses !== undefined) {
        errors.intertine_expenses = ['Rincian biaya tidak valid atau melebihi 50 item.'];
    } else {
        values.intertine_expenses = [];
    }

    for (const [amountField, dateField] of [
        ['down_payment', 'down_payment_date'],
        ['settlement_amount', 'settlement_date'],
        ['leasing_disbursement', 'leasing_disbursement_date'],
    ]) {
        if (values[amountField] > 0 && !values[dateField]) {
            errors[dateField] = ['Tanggal realisasi pembayaran wajib diisi.'];
        }
    }

    const remainingPayment = Math.max(
        values.selling_price
            - values.down_payment
            - values.settlement_amount
            - values.leasing_disbursement,
        0,
    );

    if (remainingPayment > 0 && !values.planned_payment_date) {
        errors.planned_payment_date = [
            'Tanggal rencana pembayaran wajib diisi selama masih ada sisa pembayaran.',
        ];
    }

    return {
        errors,
        values: Object.keys(errors).length > 0
            ? null
            : { ...values, remaining_payment: remainingPayment },
    };
}

function storedIntertineExpenses(value) {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    const parsed = JSON.parse(value);

    if (!Array.isArray(parsed)) {
        throw new Error('Stored vehicle expenses must be a JSON array.');
    }

    return parsed;
}

async function createSale(context) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan mencatat penjualan.' }, 403);
    }

    if (!(context.req.header('Content-Type') ?? '').includes('application/json')) {
        return context.json({ message: 'Content-Type harus application/json.' }, 415);
    }

    let payload;

    try {
        payload = await context.req.json();
    } catch {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Data penjualan tidak valid.' }, 422);
    }

    const { errors, values } = validateSalePayload(payload);

    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data penjualan tidak valid.', errors }, 422);
    }

    try {
        const vehicle = await context.env.DB.prepare(
            `SELECT id, intertine_expenses FROM car_transactions
             WHERE id = ? AND sell_date IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM sale_notes
                    WHERE sale_notes.car_transaction_id = car_transactions.id
                )
             LIMIT 1`,
        ).bind(values.car_transaction_id).first();

        if (!vehicle) {
            return context.json({
                message: 'Kendaraan yang dipilih sudah tidak tersedia untuk dijual.',
                errors: { car_transaction_id: ['Kendaraan yang dipilih tidak tersedia.'] },
            }, 422);
        }

        const saleExpenseNames = new Set(['kr jual', 'op survei', 'fee cmo']);
        const retainedExpenses = storedIntertineExpenses(vehicle.intertine_expenses)
            .filter((expense) => expense && typeof expense === 'object')
            .filter((expense) => !saleExpenseNames.has(String(expense.name ?? '').trim().toLowerCase()));
        const intertineExpenses = [
            ...retainedExpenses,
            ...values.intertine_expenses,
        ];
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const results = await context.env.DB.batch([
            context.env.DB.prepare(
                `INSERT INTO sale_notes (
                    car_transaction_id, nama_konsumen, buyer_nik, buyer_phone,
                    buyer_address, created_by, created_at, updated_at
                )
                SELECT ?, ?, ?, ?, ?, ?, ?, ?
                WHERE EXISTS (
                    SELECT 1 FROM car_transactions
                    WHERE id = ? AND sell_date IS NULL
                )
                AND NOT EXISTS (
                    SELECT 1 FROM sale_notes WHERE car_transaction_id = ?
                )`,
            ).bind(
                values.car_transaction_id,
                values.nama_konsumen,
                values.buyer_nik,
                values.buyer_phone,
                values.buyer_address,
                user.id,
                now,
                now,
                values.car_transaction_id,
                values.car_transaction_id,
            ),
            context.env.DB.prepare(
                `UPDATE car_transactions
                 SET selling_price = ?, down_payment = ?, down_payment_date = ?,
                     settlement_amount = ?, settlement_date = ?, leasing_disbursement = ?,
                     leasing_disbursement_date = ?, remaining_payment = ?,
                     planned_payment_date = ?, sell_date = ?, intertine_expenses = ?,
                     updated_at = ?
                 WHERE id = ? AND sell_date IS NULL
                    AND EXISTS (
                        SELECT 1 FROM sale_notes
                        WHERE sale_notes.car_transaction_id = car_transactions.id
                    )`,
            ).bind(
                values.selling_price,
                values.down_payment,
                values.down_payment_date,
                values.settlement_amount,
                values.settlement_date,
                values.leasing_disbursement,
                values.leasing_disbursement_date,
                values.remaining_payment,
                values.remaining_payment > 0 ? values.planned_payment_date : null,
                values.sell_date,
                JSON.stringify(intertineExpenses),
                now,
                values.car_transaction_id,
            ),
        ]);

        if (Number(results[0]?.meta?.changes) !== 1 || Number(results[1]?.meta?.changes) !== 1) {
            return context.json({
                message: 'Kendaraan yang dipilih sudah tidak tersedia untuk dijual.',
                errors: { car_transaction_id: ['Kendaraan yang dipilih tidak tersedia.'] },
            }, 422);
        }

        const saleNoteId = Number(results[0]?.meta?.last_row_id);
        const data = {
            id: saleNoteId,
            car_transaction_id: values.car_transaction_id,
            nama_konsumen: values.nama_konsumen,
            buyer_nik: values.buyer_nik,
            buyer_phone: values.buyer_phone,
            buyer_address: values.buyer_address,
            created_by: user.id,
            payment_status: values.remaining_payment <= 0
                ? 'paid'
                : values.leasing_disbursement > 0 ? 'leasing' : 'unpaid',
            total_paid: values.down_payment + values.settlement_amount + values.leasing_disbursement,
            car_transaction: {
                id: values.car_transaction_id,
                selling_price: values.selling_price,
                down_payment: values.down_payment,
                down_payment_date: values.down_payment_date,
                settlement_amount: values.settlement_amount,
                settlement_date: values.settlement_date,
                leasing_disbursement: values.leasing_disbursement,
                leasing_disbursement_date: values.leasing_disbursement_date,
                remaining_payment: values.remaining_payment,
                planned_payment_date: values.remaining_payment > 0 ? values.planned_payment_date : null,
                sell_date: values.sell_date,
                intertine_expenses: intertineExpenses,
            },
            payments: [],
        };

        return context.json({ message: 'Penjualan berhasil disimpan', data }, 201);
    } catch (error) {
        if (String(error?.message ?? '').includes('UNIQUE constraint failed: sale_notes.car_transaction_id')) {
            return context.json({
                message: 'Kendaraan yang dipilih sudah memiliki data penjualan.',
                errors: { car_transaction_id: ['Kendaraan ini sudah memiliki data Jual.'] },
            }, 422);
        }

        console.error('Error D1 Jual:', error);

        return context.json({
            message: `Gagal menyimpan transaksi D1: ${
                error instanceof Error ? error.message : 'Kesalahan server tidak diketahui.'
            }`,
        }, 500);
    }
}

async function findSale(context, saleNoteId) {
    const user = context.get('user');

    if (!['master', 'admin'].includes(user.role)) {
        return null;
    }

    const conditions = ['sale_notes.id = ?'];
    const parameters = [saleNoteId];
    if (user.role !== 'master') {
        conditions.push('sale_notes.created_by = ?');
        parameters.push(user.id);
    }

    return context.env.DB.prepare(
        `SELECT sale_notes.id AS sale_note_id, sale_notes.nama_konsumen,
                sale_notes.buyer_nik, sale_notes.buyer_phone, sale_notes.buyer_address,
                sale_notes.created_by, car_transactions.*,
                car_transactions.id AS car_transaction_id,
                ${saleStatusSql()} AS payment_status,
                car_transactions.down_payment + car_transactions.leasing_disbursement
                    + car_transactions.settlement_amount AS total_paid
         FROM sale_notes
         INNER JOIN car_transactions
            ON car_transactions.id = sale_notes.car_transaction_id
         WHERE ${conditions.join(' AND ')}
         LIMIT 1`,
    ).bind(...parameters).first();
}

function saleNoteResponse(context, row, payments = [], photos = []) {
    const canViewSensitiveData = context.get('user').role === 'master';
    const coverPhotoPath = row.vehicle_photo_path
        || photos.find((photo) => Boolean(photo.is_cover))?.path
        || photos[0]?.path
        || null;
    const vehicle = saleVehicle(row, canViewSensitiveData);
    vehicle.vehicle_photos = photos.map((photo) => ({
        id: photo.id,
        path: photo.path,
        url: publicPhotoUrl(context, photo.path),
        is_cover: Boolean(photo.is_cover),
        is_primary: Boolean(photo.is_cover),
        position: photo.position,
    }));
    vehicle.vehicle_photo_url = coverPhotoPath
        ? publicPhotoUrl(context, coverPhotoPath)
        : null;

    return {
        id: row.sale_note_id,
        nama_konsumen: row.nama_konsumen,
        buyer_nik: row.buyer_nik,
        buyer_phone: row.buyer_phone,
        buyer_address: row.buyer_address,
        created_by: row.created_by,
        payment_status: row.payment_status,
        total_paid: Number(row.total_paid ?? 0),
        can_view_sensitive_data: canViewSensitiveData,
        car_transaction: vehicle,
        payments: payments.map((payment) => ({
            id: payment.id,
            amount: payment.amount,
            payment_method: payment.payment_method,
            payment_date: payment.payment_date,
            notes: payment.notes,
        })),
    };
}

async function getSale(context) {
    const id = Number(context.req.param('buyer'));

    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
    }

    try {
        const sale = await findSale(context, id);

        if (!sale) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }

        const [payments, photos] = await Promise.all([
            context.env.DB.prepare(
                `SELECT id, amount, payment_method, payment_date, notes
                 FROM sale_payments
                 WHERE sale_note_id = ?
                 ORDER BY payment_date, id`,
            ).bind(id).all(),
            context.env.DB.prepare(
                `SELECT id, path, is_cover, position
                 FROM vehicle_photos
                 WHERE car_transaction_id = ?
                 ORDER BY position, id`,
            ).bind(sale.car_transaction_id).all(),
        ]);

        return context.json({
            data: saleNoteResponse(context, sale, payments.results ?? [], photos.results ?? []),
        });
    } catch (error) {
        console.error('API sale detail query failed.', error);

        return context.json({ message: 'Detail penjualan tidak dapat dimuat saat ini.' }, 500);
    }
}

async function updateSale(context) {
    const user = context.get('user');
    const id = Number(context.req.param('buyer'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan memperbarui transaksi penjualan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
    }

    let payload;
    try {
        payload = await context.req.json();
    } catch {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Data penjualan tidak valid.', errors: {} }, 422);
    }
    if (Object.hasOwn(payload, 'car_transaction_id')) {
        return context.json({
            message: 'Data penjualan tidak valid.',
            errors: { car_transaction_id: ['Kendaraan pada transaksi Jual tidak dapat diganti.'] },
        }, 422);
    }

    try {
        const sale = await findSale(context, id);

        if (!sale) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }

        const { errors, values } = validateSalePayload({
            ...payload,
            car_transaction_id: sale.car_transaction_id,
        });
        if (Object.keys(errors).length > 0) {
            return context.json({ message: 'Data penjualan tidak valid.', errors }, 422);
        }

        const saleExpenseNames = new Set(['kr jual', 'op survei', 'fee cmo']);
        const retainedExpenses = storedIntertineExpenses(sale.intertine_expenses)
            .filter((expense) => expense && typeof expense === 'object')
            .filter((expense) => !saleExpenseNames.has(String(expense.name ?? '').trim().toLowerCase()));
        const intertineExpenses = [...retainedExpenses, ...values.intertine_expenses];
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const result = await context.env.DB.batch([
            context.env.DB.prepare(
                `UPDATE car_transactions
                 SET selling_price = ?, down_payment = ?, down_payment_date = ?,
                     settlement_amount = ?, settlement_date = ?, leasing_disbursement = ?,
                     leasing_disbursement_date = ?, remaining_payment = ?,
                     planned_payment_date = ?, sell_date = ?, intertine_expenses = ?,
                     updated_at = ?
                 WHERE id = ? AND EXISTS (
                    SELECT 1 FROM sale_notes WHERE id = ? AND car_transaction_id = ?
                 )`,
            ).bind(
                values.selling_price,
                values.down_payment,
                values.down_payment_date,
                values.settlement_amount,
                values.settlement_date,
                values.leasing_disbursement,
                values.leasing_disbursement_date,
                values.remaining_payment,
                values.remaining_payment > 0 ? values.planned_payment_date : null,
                values.sell_date,
                JSON.stringify(intertineExpenses),
                now,
                sale.car_transaction_id,
                id,
                sale.car_transaction_id,
            ),
            context.env.DB.prepare(
                `UPDATE sale_notes
                 SET nama_konsumen = ?, buyer_nik = ?, buyer_phone = ?,
                     buyer_address = ?, updated_at = ?
                 WHERE id = ? AND created_by = ?`,
            ).bind(
                values.nama_konsumen,
                values.buyer_nik,
                values.buyer_phone,
                values.buyer_address,
                now,
                id,
                sale.created_by,
            ),
        ]);

        if (Number(result[0]?.meta?.changes) !== 1 || Number(result[1]?.meta?.changes) !== 1) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }

        return context.json({
            message: 'Data Jual berhasil diperbarui.',
            data: { id },
        });
    } catch (error) {
        console.error('API sale update failed.', error);

        return context.json({ message: 'Transaksi penjualan tidak dapat diperbarui saat ini.' }, 500);
    }
}

async function deleteSale(context) {
    const user = context.get('user');
    const id = Number(context.req.param('buyer'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan menghapus transaksi penjualan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
    }

    try {
        const sale = await findSale(context, id);

        if (!sale) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }

        const remainingExpenses = storedIntertineExpenses(sale.intertine_expenses)
            .filter((expense) => expense && typeof expense === 'object')
            .filter((expense) => !['kr jual', 'op survei', 'fee cmo'].includes(
                String(expense.name ?? '').trim().toLowerCase(),
            ));
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const results = await context.env.DB.batch([
            context.env.DB.prepare(
                `UPDATE car_transactions
                 SET sell_date = NULL, selling_price = 0, down_payment = 0,
                     down_payment_date = NULL, settlement_amount = 0,
                     settlement_date = NULL, leasing_disbursement = 0,
                     leasing_disbursement_date = NULL, remaining_payment = 0,
                     planned_payment_date = NULL, intertine_expenses = ?, updated_at = ?
                 WHERE id = ? AND EXISTS (
                    SELECT 1 FROM sale_notes WHERE id = ? AND car_transaction_id = ?
                 )`,
            ).bind(
                JSON.stringify(remainingExpenses),
                now,
                sale.car_transaction_id,
                id,
                sale.car_transaction_id,
            ),
            context.env.DB.prepare(
                'DELETE FROM sale_notes WHERE id = ? AND created_by = ?',
            ).bind(id, sale.created_by),
        ]);

        if (Number(results[0]?.meta?.changes) !== 1 || Number(results[1]?.meta?.changes) !== 1) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }

        return context.json({ message: 'Data Jual berhasil dihapus.' });
    } catch (error) {
        console.error('API sale deletion failed.', error);

        return context.json({ message: 'Transaksi penjualan tidak dapat dihapus saat ini.' }, 500);
    }
}

async function createSalePayment(context) {
    const user = context.get('user');
    const id = Number(context.req.param('buyer'));

    if (!['master', 'admin'].includes(user.role)) {
        return context.json({ message: 'Anda tidak diizinkan mencatat pembayaran penjualan.' }, 403);
    }
    if (!Number.isSafeInteger(id) || id < 1) {
        return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
    }

    let payload;
    try {
        payload = await context.req.json();
    } catch {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return context.json({ message: 'Data pembayaran tidak valid.', errors: {} }, 422);
    }

    const amount = parseNumber(payload.amount);
    const paymentMethod = typeof payload.payment_method === 'string' ? payload.payment_method : '';
    const paymentDate = typeof payload.payment_date === 'string' ? payload.payment_date.trim() : '';
    const notes = typeof payload.notes === 'string' ? payload.notes.trim() : '';
    const errors = {};

    if (!Number.isFinite(amount) || amount <= 0 || amount > 9999999999999.99) {
        errors.amount = ['Nominal pelunasan harus lebih besar dari nol dan tidak melebihi batas maksimum.'];
    }
    if (!['cash', 'leasing', 'bank_transfer'].includes(paymentMethod)) {
        errors.payment_method = ['Metode pelunasan yang dipilih tidak valid.'];
    }
    if (!isValidDate(paymentDate)) {
        errors.payment_date = ['Tanggal pelunasan tidak valid.'];
    }
    if (notes.length > 1000) {
        errors.notes = ['Catatan pelunasan maksimal 1000 karakter.'];
    }
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data pembayaran tidak valid.', errors }, 422);
    }

    try {
        const sale = await findSale(context, id);

        if (!sale) {
            return context.json({ message: 'Data penjualan tidak ditemukan.' }, 404);
        }
        if (Number(sale.remaining_payment) <= 0) {
            return context.json({
                message: 'Transaksi ini sudah lunas dan tidak memiliki sisa pembayaran.',
                errors: { amount: ['Transaksi ini sudah lunas dan tidak memiliki sisa pembayaran.'] },
            }, 422);
        }

        const newSettlementAmount = Number(sale.settlement_amount ?? 0) + amount;
        if (newSettlementAmount > 9999999999999.99) {
            return context.json({
                message: 'Data pembayaran tidak valid.',
                errors: { amount: ['Akumulasi pelunasan melebihi batas maksimum yang dapat disimpan.'] },
            }, 422);
        }

        const remainingPayment = Math.max(
            Number(sale.selling_price) - Number(sale.down_payment)
                - newSettlementAmount - Number(sale.leasing_disbursement),
            0,
        );
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        const results = await context.env.DB.batch([
            context.env.DB.prepare(
                `UPDATE car_transactions
                 SET settlement_amount = ?, settlement_date = ?,
                     remaining_payment = ?, planned_payment_date = ?, updated_at = ?
                 WHERE id = ? AND settlement_amount = ? AND remaining_payment > 0
                    AND EXISTS (SELECT 1 FROM sale_notes WHERE id = ? AND car_transaction_id = ?)`,
            ).bind(
                newSettlementAmount,
                paymentDate,
                remainingPayment,
                remainingPayment > 0 ? sale.planned_payment_date : null,
                now,
                sale.car_transaction_id,
                Number(sale.settlement_amount ?? 0),
                id,
                sale.car_transaction_id,
            ),
            context.env.DB.prepare(
                `INSERT INTO sale_payments (
                    sale_note_id, amount, payment_method, payment_date, notes,
                    recorded_by, created_at, updated_at
                )
                SELECT ?, ?, ?, ?, ?, ?, ?, ?
                WHERE EXISTS (
                    SELECT 1 FROM car_transactions
                    WHERE id = ? AND settlement_amount = ? AND updated_at = ?
                )`,
            ).bind(
                id,
                amount,
                paymentMethod,
                paymentDate,
                notes || null,
                user.id,
                now,
                now,
                sale.car_transaction_id,
                newSettlementAmount,
                now,
            ),
        ]);

        if (Number(results[0]?.meta?.changes) !== 1 || Number(results[1]?.meta?.changes) !== 1) {
            return context.json({
                message: 'Pembayaran tidak dapat disimpan karena transaksi telah berubah.',
            }, 409);
        }

        return context.json({ message: 'Pelunasan berhasil dicatat.' }, 201);
    } catch (error) {
        console.error('API sale payment creation failed.', error);

        return context.json({ message: 'Pembayaran tidak dapat dicatat saat ini.' }, 500);
    }
}

function validateShowroomExpenseDate(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00.000Z`);

    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function parseShowroomExpenseAmount(value) {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim().replace(/[.,](?=\d{3}(?:\D|$))/g, '').replace(/\s/g, '');

    if (!/^\d+$/.test(normalized)) {
        return null;
    }

    const amount = Number(normalized);

    return Number.isSafeInteger(amount) && amount > 0 ? amount : null;
}

async function parseShowroomExpenseRequest(context) {
    const contentType = context.req.header('content-type') ?? '';

    if (contentType.includes('application/json')) {
        try {
            const payload = await context.req.json();

            return payload && typeof payload === 'object' && !Array.isArray(payload)
                ? { payload, receipt: null }
                : { error: 'Data pengeluaran harus berupa objek JSON.' };
        } catch {
            return { error: 'Format JSON pengeluaran tidak valid.' };
        }
    }

    if (contentType.includes('multipart/form-data')) {
        let formData;

        try {
            formData = await context.req.formData();
        } catch {
            return { error: 'Form pengeluaran tidak dapat dibaca.' };
        }

        const payload = {};
        for (const field of [
            'expense_date',
            'category',
            'title',
            'amount',
            'payment_method',
            'recipient_name',
            'notes',
            'remove_receipt',
        ]) {
            const value = formData.get(field);
            if (value !== null) {
                payload[field] = value;
            }
        }

        const receiptValue = formData.get('receipt');
        if (receiptValue !== null
            && (typeof File === 'undefined' || !(receiptValue instanceof File))) {
            return { error: 'Bukti pengeluaran harus berupa file.' };
        }

        return {
            payload,
            receipt: receiptValue && receiptValue.size > 0 ? receiptValue : null,
        };
    }

    return { error: 'Gunakan JSON atau multipart/form-data untuk data pengeluaran.' };
}

function validateShowroomExpense(payload, existing = null) {
    const errors = {};
    const values = {};
    const expenseDate = payload.expense_date ?? existing?.expense_date;
    const category = payload.category ?? existing?.category;
    const title = payload.title ?? existing?.title;
    const amountValue = payload.amount ?? existing?.amount;
    const paymentMethod = payload.payment_method ?? existing?.payment_method ?? 'Tunai';

    if (!validateShowroomExpenseDate(expenseDate)) {
        errors.expense_date = ['Tanggal pengeluaran harus berupa tanggal yang valid.'];
    } else {
        values.expense_date = expenseDate;
    }

    if (!SHOWROOM_EXPENSE_CATEGORIES.includes(category)) {
        errors.category = ['Kategori pengeluaran tidak valid.'];
    } else {
        values.category = category;
    }

    if (typeof title !== 'string' || title.trim().length === 0 || title.trim().length > 255) {
        errors.title = ['Judul pengeluaran wajib diisi dan maksimal 255 karakter.'];
    } else {
        values.title = title.trim();
    }

    const amount = parseShowroomExpenseAmount(amountValue);
    if (amount === null) {
        errors.amount = ['Nominal pengeluaran harus berupa bilangan Rupiah lebih dari nol.'];
    } else {
        values.amount = amount;
    }

    if (!SHOWROOM_EXPENSE_PAYMENT_METHODS.includes(paymentMethod)) {
        errors.payment_method = ['Metode pembayaran tidak valid.'];
    } else {
        values.payment_method = paymentMethod;
    }

    for (const [field, maxLength] of [['recipient_name', 255], ['notes', 4000]]) {
        const value = payload[field] ?? existing?.[field] ?? null;

        if (value !== null && (typeof value !== 'string' || value.length > maxLength)) {
            errors[field] = [`${field === 'notes' ? 'Keterangan' : 'Penerima'} maksimal ${maxLength} karakter.`];
        } else {
            values[field] = typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
        }
    }

    return { errors, values };
}

function showroomExpenseReceiptUrl(context, receiptPath) {
    if (!receiptPath) {
        return null;
    }

    const encodedPath = String(receiptPath).split('/').map(encodeURIComponent).join('/');

    return `${new URL(context.req.url).origin}/api/vehicle-photos/${encodedPath}`;
}

function serializeShowroomExpense(context, expense) {
    return {
        ...expense,
        receipt_url: showroomExpenseReceiptUrl(context, expense.receipt_path),
    };
}

function validateShowroomExpenseReceipt(context, receipt) {
    if (!receipt) {
        return null;
    }

    if (!SHOWROOM_EXPENSE_RECEIPT_TYPES.has(receipt.type)) {
        return context.json({ message: 'Bukti harus berupa gambar JPG, PNG, atau WebP.' }, 422);
    }

    if (receipt.size > SHOWROOM_EXPENSE_RECEIPT_MAX_BYTES) {
        return context.json({ message: 'Ukuran bukti maksimal 5 MB.' }, 422);
    }

    if (!context.env.VEHICLE_PHOTOS) {
        return context.json({ message: 'Penyimpanan bukti pengeluaran belum tersedia.' }, 503);
    }

    return null;
}

function canManageShowroomExpenses(context) {
    return ['master', 'admin'].includes(context.get('user')?.role);
}

async function getShowroomExpenses(context) {
    if (!canManageShowroomExpenses(context)) {
        return context.json({ message: 'Anda tidak memiliki akses ke pengeluaran showroom.' }, 403);
    }

    const query = context.req.query();
    const conditions = [];
    const parameters = [];
    const search = String(query.search ?? '').trim().toLowerCase();

    if (search) {
        conditions.push(`(
            LOWER(title) LIKE ? OR
            LOWER(COALESCE(recipient_name, '')) LIKE ? OR
            LOWER(COALESCE(notes, '')) LIKE ?
        )`);
        const searchTerm = `%${search}%`;
        parameters.push(searchTerm, searchTerm, searchTerm);
    }

    for (const [key, column] of [
        ['category', 'category'],
        ['payment_method', 'payment_method'],
        ['start_date', 'expense_date'],
    ]) {
        const value = String(query[key] ?? '').trim();
        if (value) {
            conditions.push(`${column} ${key === 'start_date' ? '>=' : '='} ?`);
            parameters.push(value);
        }
    }

    const endDate = String(query.end_date ?? '').trim();
    if (endDate) {
        conditions.push('expense_date <= ?');
        parameters.push(endDate);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    try {
        const result = await context.env.DB.prepare(
            `SELECT id, expense_date, category, title, amount, payment_method,
                    recipient_name, notes, receipt_path, created_by, created_at, updated_at
             FROM showroom_expenses ${whereClause}
             ORDER BY expense_date DESC, id DESC`,
        ).bind(...parameters).all();
        const expenses = result.results ?? [];
        const currentDateParts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Jakarta',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(new Date());
        const currentMonth = `${currentDateParts.find((part) => part.type === 'year')?.value}-${currentDateParts.find((part) => part.type === 'month')?.value}`;
        const currentDate = `${currentMonth}-${currentDateParts.find((part) => part.type === 'day')?.value}`;
        const monthlyTotal = await context.env.DB.prepare(
            `SELECT COALESCE(SUM(amount), 0) AS total_amount
             FROM showroom_expenses WHERE expense_date >= ? AND expense_date <= ?`,
        ).bind(`${currentMonth}-01`, currentDate).first();
        const categoryTotals = new Map();
        let totalAmount = 0;

        for (const expense of expenses) {
            const amount = Number(expense.amount) || 0;
            totalAmount += amount;
            categoryTotals.set(expense.category, (categoryTotals.get(expense.category) ?? 0) + amount);
        }

        const topCategory = [...categoryTotals.entries()]
            .sort((left, right) => right[1] - left[1])[0] ?? [null, 0];

        return context.json({
            data: expenses.map((expense) => serializeShowroomExpense(context, expense)),
            summary: {
                total_amount: totalAmount,
                this_month_amount: Number(monthlyTotal?.total_amount) || 0,
                transaction_count: expenses.length,
                top_category: topCategory[0],
                top_category_amount: topCategory[1],
            },
        });
    } catch (error) {
        console.error('Showroom expense listing failed.', error);

        return context.json({ message: 'Daftar pengeluaran showroom tidak dapat dimuat saat ini.' }, 500);
    }
}

async function createShowroomExpense(context) {
    if (!canManageShowroomExpenses(context)) {
        return context.json({ message: 'Anda tidak memiliki akses untuk mencatat pengeluaran showroom.' }, 403);
    }

    const parsedRequest = await parseShowroomExpenseRequest(context);
    if (parsedRequest.error) {
        return context.json({ message: parsedRequest.error }, 400);
    }

    const { errors, values } = validateShowroomExpense(parsedRequest.payload);
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data pengeluaran tidak valid.', errors }, 422);
    }

    const receiptError = validateShowroomExpenseReceipt(context, parsedRequest.receipt);
    if (receiptError) {
        return receiptError;
    }

    const user = context.get('user');
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    let expenseId = null;
    let uploadedReceiptPath = null;

    try {
        const inserted = await context.env.DB.prepare(
            `INSERT INTO showroom_expenses (
                expense_date, category, title, amount, payment_method,
                recipient_name, notes, receipt_path, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)`,
        ).bind(
            values.expense_date,
            values.category,
            values.title,
            values.amount,
            values.payment_method,
            values.recipient_name,
            values.notes,
            user.id,
            now,
            now,
        ).run();
        expenseId = Number(inserted.meta?.last_row_id);

        if (!Number.isSafeInteger(expenseId) || expenseId < 1) {
            throw new Error('D1 did not return a valid showroom expense ID.');
        }

        if (parsedRequest.receipt) {
            const extension = SHOWROOM_EXPENSE_RECEIPT_TYPES.get(parsedRequest.receipt.type);
            uploadedReceiptPath = `expenses/${expenseId}/${crypto.randomUUID()}.${extension}`;
            await context.env.VEHICLE_PHOTOS.put(uploadedReceiptPath, await parsedRequest.receipt.arrayBuffer(), {
                httpMetadata: { contentType: parsedRequest.receipt.type },
            });
            await context.env.DB.prepare(
                'UPDATE showroom_expenses SET receipt_path = ?, updated_at = ? WHERE id = ?',
            ).bind(uploadedReceiptPath, now, expenseId).run();
        }

        return context.json({
            message: 'Pengeluaran showroom berhasil dicatat.',
            data: serializeShowroomExpense(context, {
                id: expenseId,
                ...values,
                receipt_path: uploadedReceiptPath,
                created_by: user.id,
                created_at: now,
                updated_at: now,
            }),
        }, 201);
    } catch (error) {
        console.error('Showroom expense creation failed.', error);
        if (uploadedReceiptPath && context.env.VEHICLE_PHOTOS) {
            try {
                await context.env.VEHICLE_PHOTOS.delete(uploadedReceiptPath);
            } catch (cleanupError) {
                console.error('Showroom expense receipt cleanup failed.', cleanupError);
            }
        }
        if (expenseId) {
            try {
                await context.env.DB.prepare('DELETE FROM showroom_expenses WHERE id = ?').bind(expenseId).run();
            } catch (cleanupError) {
                console.error('Showroom expense rollback failed.', cleanupError);
            }
        }

        return context.json({ message: 'Pengeluaran showroom tidak dapat disimpan saat ini.' }, 500);
    }
}

async function updateShowroomExpense(context) {
    if (!canManageShowroomExpenses(context)) {
        return context.json({ message: 'Anda tidak memiliki akses untuk mengubah pengeluaran showroom.' }, 403);
    }

    const expenseId = Number(context.req.param('id'));
    if (!Number.isSafeInteger(expenseId) || expenseId < 1) {
        return context.json({ message: 'ID pengeluaran tidak valid.' }, 422);
    }

    const existing = await context.env.DB.prepare(
        'SELECT * FROM showroom_expenses WHERE id = ?',
    ).bind(expenseId).first();
    if (!existing) {
        return context.json({ message: 'Pengeluaran showroom tidak ditemukan.' }, 404);
    }

    const parsedRequest = await parseShowroomExpenseRequest(context);
    if (parsedRequest.error) {
        return context.json({ message: parsedRequest.error }, 400);
    }

    const { errors, values } = validateShowroomExpense(parsedRequest.payload, existing);
    if (Object.keys(errors).length > 0) {
        return context.json({ message: 'Data pengeluaran tidak valid.', errors }, 422);
    }

    const receiptError = validateShowroomExpenseReceipt(context, parsedRequest.receipt);
    if (receiptError) {
        return receiptError;
    }

    const removeReceipt = ['true', '1', 'on'].includes(
        String(parsedRequest.payload.remove_receipt ?? '').toLowerCase(),
    );
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const oldReceiptPath = existing.receipt_path ?? null;
    let receiptPath = oldReceiptPath;
    let uploadedReceiptPath = null;
    let didUpdate = false;

    if (oldReceiptPath && (parsedRequest.receipt || removeReceipt) && !context.env.VEHICLE_PHOTOS) {
        return context.json({ message: 'Penyimpanan bukti pengeluaran belum tersedia.' }, 503);
    }

    try {
        if (parsedRequest.receipt) {
            const extension = SHOWROOM_EXPENSE_RECEIPT_TYPES.get(parsedRequest.receipt.type);
            uploadedReceiptPath = `expenses/${expenseId}/${crypto.randomUUID()}.${extension}`;
            await context.env.VEHICLE_PHOTOS.put(uploadedReceiptPath, await parsedRequest.receipt.arrayBuffer(), {
                httpMetadata: { contentType: parsedRequest.receipt.type },
            });
            receiptPath = uploadedReceiptPath;
        } else if (removeReceipt) {
            receiptPath = null;
        }

        const updateResult = await context.env.DB.prepare(
            `UPDATE showroom_expenses
             SET expense_date = ?, category = ?, title = ?, amount = ?,
                 payment_method = ?, recipient_name = ?, notes = ?,
                 receipt_path = ?, updated_at = ?
             WHERE id = ?`,
        ).bind(
            values.expense_date,
            values.category,
            values.title,
            values.amount,
            values.payment_method,
            values.recipient_name,
            values.notes,
            receiptPath,
            now,
            expenseId,
        ).run();
        if (Number(updateResult.meta?.changes) !== 1) {
            if (uploadedReceiptPath) {
                await context.env.VEHICLE_PHOTOS.delete(uploadedReceiptPath);
                uploadedReceiptPath = null;
            }

            return context.json({ message: 'Pengeluaran berubah sebelum dapat diperbarui.' }, 409);
        }
        didUpdate = true;

        if (oldReceiptPath && oldReceiptPath !== receiptPath) {
            await context.env.VEHICLE_PHOTOS.delete(oldReceiptPath);
        }

        return context.json({
            message: 'Pengeluaran showroom berhasil diperbarui.',
            data: serializeShowroomExpense(context, {
                ...existing,
                ...values,
                id: expenseId,
                receipt_path: receiptPath,
                updated_at: now,
            }),
        });
    } catch (error) {
        console.error('Showroom expense update failed.', error);
        if (!didUpdate && uploadedReceiptPath && context.env.VEHICLE_PHOTOS) {
            try {
                await context.env.VEHICLE_PHOTOS.delete(uploadedReceiptPath);
            } catch (cleanupError) {
                console.error('Showroom expense receipt cleanup failed.', cleanupError);
            }
        }

        return context.json({ message: 'Pengeluaran showroom tidak dapat diperbarui saat ini.' }, 500);
    }
}

async function deleteShowroomExpense(context) {
    if (!canManageShowroomExpenses(context)) {
        return context.json({ message: 'Anda tidak memiliki akses untuk menghapus pengeluaran showroom.' }, 403);
    }

    const expenseId = Number(context.req.param('id'));
    if (!Number.isSafeInteger(expenseId) || expenseId < 1) {
        return context.json({ message: 'ID pengeluaran tidak valid.' }, 422);
    }

    let wasDeleted = false;
    try {
        const expense = await context.env.DB.prepare(
            'SELECT id, receipt_path FROM showroom_expenses WHERE id = ?',
        ).bind(expenseId).first();
        if (!expense) {
            return context.json({ message: 'Pengeluaran showroom tidak ditemukan.' }, 404);
        }

        if (expense.receipt_path && !context.env.VEHICLE_PHOTOS) {
            return context.json({ message: 'Penyimpanan bukti pengeluaran belum tersedia.' }, 503);
        }

        const deleteResult = await context.env.DB.prepare(
            'DELETE FROM showroom_expenses WHERE id = ?',
        ).bind(expenseId).run();
        if (Number(deleteResult.meta?.changes) !== 1) {
            return context.json({ message: 'Pengeluaran berubah sebelum dapat dihapus.' }, 409);
        }
        wasDeleted = true;

        if (expense.receipt_path) {
            await context.env.VEHICLE_PHOTOS.delete(expense.receipt_path);
        }

        return context.json({ message: 'Pengeluaran showroom berhasil dihapus.' });
    } catch (error) {
        console.error('Showroom expense deletion failed.', error);

        return context.json({
            message: wasDeleted
                ? 'Pengeluaran telah dihapus, tetapi file bukti gagal dibersihkan.'
                : 'Pengeluaran showroom tidak dapat dihapus saat ini.',
        }, 500);
    }
}

app.get('/api/health', async (context) => {
    const result = await context.env.DB.prepare('SELECT 1 AS connected').first();

    if (result?.connected !== 1) {
        return context.json({ ok: false, message: 'Database tidak tersedia.' }, 503);
    }

    return context.json({ ok: true, database: 'connected' });
});

app.get('/api/vehicle-photos/*', streamVehiclePhoto);

app.get('/api/spa/dashboard', requireSession, getDashboard);
app.get('/api/spa/transactions', requireSession, getDashboard);
app.post('/api/spa/transactions', requireSession, createTransaction);
app.get('/api/spa/transactions/:id/edit', requireSession, getTransaction);
app.get('/api/spa/transactions/:id', requireSession, getTransaction);
app.post('/api/spa/transactions/:id', requireSession, updateTransaction);
app.put('/api/spa/transactions/:id', requireSession, updateTransaction);
app.delete('/api/spa/transactions/:id', requireSession, deleteTransaction);
app.get('/api/spa/jual', requireSession, getSales);
app.get('/api/spa/jual/create', requireSession, getSaleForm);
app.post('/api/spa/jual', requireSession, createSale);
app.get('/api/spa/jual/:buyer', requireSession, getSale);
app.put('/api/spa/jual/:buyer', requireSession, updateSale);
app.delete('/api/spa/jual/:buyer', requireSession, deleteSale);
app.post('/api/spa/jual/:buyer/payments', requireSession, createSalePayment);
app.get('/api/spa/pengeluaran', requireSession, getShowroomExpenses);
app.post('/api/spa/pengeluaran', requireSession, createShowroomExpense);
app.put('/api/spa/pengeluaran/:id', requireSession, updateShowroomExpense);
app.post('/api/spa/pengeluaran/:id', requireSession, updateShowroomExpense);
app.delete('/api/spa/pengeluaran/:id', requireSession, deleteShowroomExpense);
app.get('/api/spa/laporan-penjualan', requireSession, getSalesReport);
app.get('/api/spa/nota-penjualan', requireSession, getSaleReceipts);
app.get('/api/spa/nota-penjualan/:id', requireSession, getSaleReceipt);
app.get('/api/spa/nota-settings', requireSession, getNotaSettings);
app.put('/api/spa/nota-settings', requireSession, updateNotaSettings);
app.get('/api/spa/marketing/vehicles', requireSession, getMarketingVehicles);
app.get('/api/spa/marketing/vehicles/:id', requireSession, getMarketingVehicle);
app.get('/api/spa/users', requireSession, getUsers);
app.post('/api/spa/users', requireSession, createUser);
app.put('/api/spa/users/:id', requireSession, updateUser);
app.delete('/api/spa/users/:id', requireSession, deleteUser);
app.get('/api/spa/profile', requireSession, getProfile);
app.put('/api/spa/profile', requireSession, updateProfile);

app.post('/api/spa/login', async (context) => {
    let credentials;

    try {
        credentials = await context.req.json();
    } catch {
        return context.json({ message: 'Request harus berisi JSON yang valid.' }, 400);
    }

    const username = typeof credentials?.username === 'string'
        ? credentials.username.trim()
        : '';
    const password = typeof credentials?.password === 'string'
        ? credentials.password
        : '';

    if (!username || !password || username.length > 255 || password.length > 1024) {
        const message = 'Username atau password salah';

        return context.json(validationError(message, ['username']), 401);
    }

    try {
        const user = await context.env.DB
            .prepare('SELECT * FROM users WHERE username = ? LIMIT 1')
            .bind(username)
            .first();

        const passwordHash = typeof user?.password === 'string'
            ? user.password
            : DUMMY_PASSWORD_HASH;
        const isPasswordValid = await compare(password, passwordHash);

        if (!user || !isPasswordValid) {
            const message = 'Username atau password salah';

            return context.json(validationError(message, ['username']), 401);
        }

        return context.json({
            message: 'Login berhasil',
            ...await createSession(context, user),
        });
    } catch (error) {
        console.error('API login failed.', error);

        return context.json({ message: 'Login tidak dapat diproses saat ini.' }, 500);
    }
});

app.get('/api/spa/me', requireSession, (context) => {
    return context.json({ data: context.get('user') });
});

app.post('/api/spa/logout', requireSession, async (context) => {
    const token = context.req.header('Authorization').slice('Bearer '.length).trim();

    await context.env.DB.prepare(
        'DELETE FROM api_sessions WHERE token_hash = ?',
    ).bind(await hashToken(token)).run();

    return context.body(null, 204);
});

app.notFound((context) => {
    if (context.req.path.startsWith('/api/')) {
        return context.json({ message: 'Endpoint API tidak ditemukan.' }, 404);
    }

    return context.text('Not Found', 404);
});

app.onError((error, context) => {
    console.error('Worker request failed.', error);

    return context.json({ message: 'Terjadi kesalahan pada server API.' }, 500);
});

export default app;
