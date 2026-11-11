<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api.js';

const router = useRouter();
const credentials = ref({ username: '', password: '' });
const isSubmitting = ref(false);
const errorMessage = ref('');
const usernameError = ref('');

async function submitLogin() {
    isSubmitting.value = true;
    errorMessage.value = '';
    usernameError.value = '';

    try {
        const response = await apiRequest('/api/spa/login', {
            method: 'POST',
            body: credentials.value,
        });
        window.location.assign(response.redirect);
    } catch (error) {
        if (error.status === 409 && error.redirect) {
            await router.push(error.redirect);
        } else {
            errorMessage.value = error.message;
            usernameError.value = error.errors?.username?.[0] ?? '';
        }
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <main class="spa-auth-page">
        <section class="spa-auth-card" aria-labelledby="login-title">
            <p class="spa-eyebrow">Sistem Toko Mobil</p>
            <h1 id="login-title">Masuk ke akun</h1>
            <p>Gunakan akun Master, Admin, atau Marketing untuk melanjutkan.</p>
            <p v-if="errorMessage" class="spa-error" role="alert">{{ errorMessage }}</p>
            <form class="spa-auth-form" @submit.prevent="submitLogin">
                <label><span>Username</span><input v-model="credentials.username" autocomplete="username" required autofocus :disabled="isSubmitting"><small v-if="usernameError">{{ usernameError }}</small></label>
                <label><span>Password</span><input v-model="credentials.password" type="password" autocomplete="current-password" required :disabled="isSubmitting"></label>
                <button class="spa-primary-action" type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Memeriksa akun...' : 'Login' }}</button>
            </form>
        </section>
    </main>
</template>
