<script setup>
import { onMounted, ref } from 'vue';
import { apiRequest } from '../services/api.js';

const form = ref({
    name: window.spaUser?.name ?? '',
    username: '',
    current_password: '',
    password: '',
    password_confirmation: '',
});
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const successMessage = ref('');

onMounted(async () => {
    try {
        const response = await apiRequest('/api/spa/profile');
        form.value.name = response.data.name;
        form.value.username = response.data.username;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
});

async function saveProfile() {
    isSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    fieldErrors.value = {};

    try {
        const response = await apiRequest('/api/spa/profile', {
            method: 'PUT',
            body: form.value,
        });
        form.value.name = response.data.name;
        form.value.username = response.data.username;
        form.value.current_password = '';
        form.value.password = '';
        form.value.password_confirmation = '';
        successMessage.value = 'Pengaturan akun berhasil diperbarui.';
    } catch (error) {
        errorMessage.value = error.message;
        fieldErrors.value = error.errors ?? {};
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <section class="spa-dashboard" aria-labelledby="profile-title">
        <div class="spa-heading">
            <div><p class="spa-eyebrow">Keamanan</p><h1 id="profile-title">Pengaturan akun</h1><p>Perbarui nama, username, dan kata sandi Anda.</p></div>
        </div>
        <div v-if="isLoading" class="spa-loading" role="status"><span class="spa-spinner" aria-hidden="true"></span> Memuat profil...</div>
        <form v-else class="spa-form-panel spa-profile-form" @submit.prevent="saveProfile">
            <p v-if="successMessage" class="spa-success" role="status">{{ successMessage }}</p>
            <p v-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</p>
            <div class="spa-form-grid">
                <label><span>Nama lengkap</span><input v-model="form.name" required maxlength="255" :disabled="isSaving"><small v-if="fieldErrors.name">{{ fieldErrors.name[0] }}</small></label>
                <label><span>Username</span><input v-model="form.username" required maxlength="255" :disabled="isSaving"><small v-if="fieldErrors.username">{{ fieldErrors.username[0] }}</small></label>
            </div>
            <h2>Ubah kata sandi</h2>
            <p class="spa-muted">Kosongkan password baru jika tidak ingin menggantinya.</p>
            <div class="spa-form-grid">
                <label><span>Password saat ini</span><input v-model="form.current_password" type="password" autocomplete="current-password" :disabled="isSaving"><small v-if="fieldErrors.current_password">{{ fieldErrors.current_password[0] }}</small></label>
                <label><span>Password baru</span><input v-model="form.password" type="password" minlength="8" autocomplete="new-password" :disabled="isSaving"><small v-if="fieldErrors.password">{{ fieldErrors.password[0] }}</small></label>
                <label><span>Konfirmasi password baru</span><input v-model="form.password_confirmation" type="password" autocomplete="new-password" :disabled="isSaving"></label>
            </div>
            <div class="spa-form-actions"><button type="submit" class="spa-primary-action" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan perubahan' }}</button></div>
        </form>
    </section>
</template>
