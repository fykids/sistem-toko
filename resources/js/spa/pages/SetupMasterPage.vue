<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const router = useRouter();
const form = ref({ name: '', username: '', password: '', password_confirmation: '' });
const isSubmitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

async function createMaster() {
    isSubmitting.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    try {
        const response = await apiRequest('/api/spa/setup-master', {
            method: 'POST',
            body: form.value,
        });
        window.location.assign(response.redirect);
    } catch (error) {
        if (error.status === 409) {
            await router.push('/login');
        } else {
            errorMessage.value = error.message;
            fieldErrors.value = error.errors ?? {};
        }
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <main class="spa-auth-page">
        <section class="spa-auth-card" aria-labelledby="setup-title">
            <p class="spa-eyebrow">Sistem Toko Mobil</p>
            <h1 id="setup-title">Buat akun Master</h1>
            <p>Buat akun Master pertama untuk memulai aplikasi.</p>
            <p v-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</p>
            <form class="spa-auth-form" @submit.prevent="createMaster">
                <label><span>Nama lengkap</span><input v-model="form.name" maxlength="255" autocomplete="name" required :disabled="isSubmitting"><small v-if="fieldErrors.name">{{ fieldErrors.name[0] }}</small></label>
                <label><span>Username</span><input v-model="form.username" maxlength="255" autocomplete="username" required :disabled="isSubmitting"><small v-if="fieldErrors.username">{{ fieldErrors.username[0] }}</small></label>
                <label><span>Password baru (minimal 12 karakter)</span><input v-model="form.password" type="password" minlength="12" autocomplete="new-password" required :disabled="isSubmitting"><small v-if="fieldErrors.password">{{ fieldErrors.password[0] }}</small></label>
                <label><span>Konfirmasi password baru</span><input v-model="form.password_confirmation" type="password" autocomplete="new-password" required :disabled="isSubmitting"></label>
                <button class="spa-primary-action" type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Membuat akun...' : 'Buat akun Master & mulai aplikasi' }}</button>
            </form>
        </section>
    </main>
</template>
