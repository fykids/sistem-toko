const configuredApiBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim();
const apiBaseUrl = (configuredApiBaseUrl || (
    import.meta.env.DEV
        ? 'http://localhost:8787'
        : 'https://sistem-toko-api.fyosuaphabeahan.workers.dev'
)).replace(/\/+$/, '');

function resolveApiUrl(path) {
    return new URL(path.replace(/^\/+/, ''), `${apiBaseUrl}/`).toString();
}

export function resolvePhotoUrl(url) {
    if (typeof url !== 'string' || url.trim() === '') {
        return null;
    }

    const photoUrl = url.trim();

    if (/^(?:https?:\/\/|blob:|data:image\/)/i.test(photoUrl)) {
        return photoUrl;
    }
    if (photoUrl.startsWith('//')) {
        return new URL(photoUrl, `${apiBaseUrl}/`).toString();
    }
    if (/^[a-z][a-z\d+.-]*:/i.test(photoUrl)) {
        return null;
    }

    try {
        return new URL(photoUrl, `${apiBaseUrl}/`).toString();
    } catch {
        return null;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function accessToken() {
    return sessionStorage.getItem('apiToken');
}

function responseErrorMessage(response, payload) {
    const validationErrors = payload?.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors).flat().find((message) => typeof message === 'string')
        : null;

    if (validationErrors) {
        return validationErrors;
    }

    if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
        return payload.message;
    }

    if (response.status === 404 || response.status >= 500) {
        return `Endpoint API tidak ditemukan atau server offline (HTTP ${response.status}).`;
    }

    return `Permintaan gagal diproses (HTTP ${response.status}).`;
}

function clearLocalSession() {
    for (const storage of [sessionStorage, localStorage]) {
        storage.removeItem('apiToken');
        storage.removeItem('spaUser');
    }

    window.spaUser = null;
}

async function handleResponse(response, path) {
    if (response.status === 204) {
        return null;
    }

    const contentType = response.headers.get('content-type') ?? '';
    const isJson = /\bapplication\/(?:[\w.-]+\+)?json\b/i.test(contentType);
    let payload = null;

    if (isJson) {
        const responseBody = await response.text();

        if (responseBody.trim() !== '') {
            try {
                payload = JSON.parse(responseBody);
            } catch {
                if (response.ok) {
                    throw new Error('Server mengembalikan respons JSON yang tidak valid.');
                }
            }
        }
    }

    if (!response.ok) {
        if (response.status === 401 && !/^\/?api\/spa\/login(?:\?|$)/.test(path)) {
            clearLocalSession();

            if (window.location.pathname !== '/login') {
                window.location.replace('/login');
            }
        }

        const error = new Error(responseErrorMessage(response, payload));
        error.status = response.status;
        error.errors = payload?.errors && typeof payload.errors === 'object'
            ? payload.errors
            : {};
        error.details = typeof payload?.details === 'string' ? payload.details : null;
        error.redirect = payload?.redirect;
        throw error;
    }

    if (!isJson) {
        throw new Error('Endpoint API tidak ditemukan. Periksa URL backend API.');
    }

    if (payload === null) {
        throw new Error('Server mengembalikan respons kosong atau bukan JSON.');
    }

    return payload;
}

async function sendRequest(path, options) {
    const url = resolveApiUrl(path);
    const token = accessToken();
    const headers = new Headers(options.headers);

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    try {
        const response = await fetch(url, {
            ...options,
            headers,
            credentials: 'include',
        });

        return await handleResponse(response, path);
    } catch (error) {
        if (error.name === 'AbortError' || error.status !== undefined) {
            throw error;
        }

        if (error instanceof TypeError) {
            throw new Error('Tidak dapat terhubung ke server API. Periksa URL backend atau koneksi server.');
        }

        throw error;
    }
}

export async function apiRequest(path, { method = 'GET', body, signal } = {}) {
    const normalizedMethod = method.toUpperCase();
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    if (['POST', 'PUT', 'PATCH'].includes(normalizedMethod)) {
        headers['Content-Type'] = 'application/json';
    }

    return sendRequest(path, {
        method: normalizedMethod,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        signal,
    });
}

export async function apiFormRequest(path, { method = 'POST', body, signal } = {}) {
    return sendRequest(path, {
        method: method.toUpperCase(),
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
        signal,
    });
}
