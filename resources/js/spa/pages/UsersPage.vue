<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const users = computed(() => payload.value?.data ?? []);
const pagination = computed(() => payload.value?.meta ?? null);
const masterCount = computed(() => payload.value?.summary?.master_count ?? 0);
const search = ref('');
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const formVisible = ref(false);
const editingUserId = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return { name: '', username: '', password: '', role: 'admin' };
}

async function loadUsers(signal) {
    isLoading.value = true;
    errorMessage.value = '';
    search.value = route.query.q ?? '';
    const query = new URLSearchParams();

    if (route.query.q) query.set('q', route.query.q);
    if (route.query.page) query.set('page', route.query.page);

    try {
        payload.value = await apiRequest(`/api/spa/users?${query}`, { signal });
    } catch (error) {
        if (error.name !== 'AbortError') {
            errorMessage.value = error.message;
        }
    } finally {
        if (!signal.aborted) isLoading.value = false;
    }
}

watch(
    () => route.fullPath,
    (_path, _previousPath, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        loadUsers(controller.signal);
    },
    { immediate: true },
);

function searchUsers() {
    router.push({ query: search.value.trim() ? { q: search.value.trim() } : {} });
}

function startCreate() {
    editingUserId.value = null;
    form.value = emptyForm();
    fieldErrors.value = {};
    formVisible.value = true;
}

function startEdit(user) {
    editingUserId.value = user.id;
    form.value = { name: user.name, username: user.username, role: user.role, password: '' };
    fieldErrors.value = {};
    formVisible.value = true;
}

async function saveUser() {
    isSaving.value = true;
    fieldErrors.value = {};
    errorMessage.value = '';
    const isEditing = editingUserId.value !== null;
    const body = { ...form.value };

    if (isEditing && !body.password) {
        delete body.password;
    }

    try {
        await apiRequest(
            isEditing ? `/api/spa/users/${editingUserId.value}` : '/api/spa/users',
            { method: isEditing ? 'PUT' : 'POST', body },
        );
        formVisible.value = false;
        await loadUsers(new AbortController().signal);
    } catch (error) {
        fieldErrors.value = error.errors ?? {};
        errorMessage.value = error.message;
    } finally {
        isSaving.value = false;
    }
}

async function deleteUser(user) {
    if (!window.confirm(`Hapus akun ${user.name}?`)) return;

    isSaving.value = true;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/spa/users/${user.id}`, { method: 'DELETE' });
        await loadUsers(new AbortController().signal);
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isSaving.value = false;
    }
}

function goToPage(page) {
    router.push({ query: { ...route.query, page: String(page) } });
}

const roleLabels = { master: 'Master', admin: 'Admin', marketing: 'Marketing' };
</script>

<template>
    <section class="spa-dashboard" aria-labelledby="users-title">
        <div class="spa-heading">
            <div>
                <p class="spa-eyebrow">Administrasi</p>
                <h1 id="users-title">Manajemen akun</h1>
                <p>Kelola akses dan akun pengguna sistem.</p>
            </div>
            <button type="button" class="spa-primary-action" :disabled="isSaving" @click="startCreate">
                Tambah akun
            </button>
        </div>

        <div class="spa-stats">
            <article class="spa-stat"><span>Total akun Master</span><strong>{{ masterCount }}</strong></article>
        </div>

        <form class="spa-filters" @submit.prevent="searchUsers">
            <label class="spa-search">
                <span>Cari nama, username, atau peran</span>
                <input v-model="search" type="search" :disabled="isLoading">
            </label>
            <button type="submit" :disabled="isLoading">Cari</button>
        </form>

        <div v-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</div>
        <div v-if="isLoading" class="spa-loading" role="status">
            <span class="spa-spinner" aria-hidden="true"></span> Memuat akun...
        </div>

        <section v-else class="spa-table-panel" aria-label="Daftar pengguna">
            <div class="spa-table-scroll">
                <table>
                    <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Tanggal dibuat</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>{{ user.name }}</td>
                            <td>{{ user.username }}</td>
                            <td>{{ roleLabels[user.role] ?? user.role }}</td>
                            <td>{{ user.created_at ? new Intl.DateTimeFormat('id-ID').format(new Date(user.created_at)) : '—' }}</td>
                            <td class="spa-row-actions">
                                <button type="button" :disabled="isSaving" @click="startEdit(user)">Edit</button>
                                <button type="button" :disabled="isSaving" @click="deleteUser(user)">Hapus</button>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0"><td colspan="5" class="spa-empty">Tidak ada akun yang cocok.</td></tr>
                    </tbody>
                </table>
            </div>
            <footer v-if="pagination && pagination.last_page > 1" class="spa-pagination">
                <button type="button" :disabled="isSaving || pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">Sebelumnya</button>
                <span>Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                <button type="button" :disabled="isSaving || pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)">Berikutnya</button>
            </footer>
        </section>

        <section v-if="formVisible" class="spa-form-panel" aria-labelledby="user-form-title">
            <h2 id="user-form-title">{{ editingUserId ? 'Edit akun' : 'Tambah akun' }}</h2>
            <form class="spa-form-grid" @submit.prevent="saveUser">
                <label><span>Nama</span><input v-model="form.name" required maxlength="255" :disabled="isSaving"><small v-if="fieldErrors.name">{{ fieldErrors.name[0] }}</small></label>
                <label><span>Username</span><input v-model="form.username" required maxlength="255" :disabled="isSaving"><small v-if="fieldErrors.username">{{ fieldErrors.username[0] }}</small></label>
                <label><span>{{ editingUserId ? 'Password baru (opsional)' : 'Password' }}</span><input v-model="form.password" type="password" :required="!editingUserId" minlength="8" :disabled="isSaving"><small v-if="fieldErrors.password">{{ fieldErrors.password[0] }}</small></label>
                <label><span>Peran</span><select v-model="form.role" required :disabled="isSaving"><option value="admin">Admin</option><option value="marketing">Marketing</option><option value="master">Master</option></select><small v-if="fieldErrors.role">{{ fieldErrors.role[0] }}</small></label>
                <div class="spa-form-actions">
                    <button type="button" :disabled="isSaving" @click="formVisible = false">Batal</button>
                    <button type="submit" class="spa-primary-action" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan akun' }}</button>
                </div>
            </form>
        </section>
    </section>
</template>
