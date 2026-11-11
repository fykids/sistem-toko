export async function apiRequest(path, { method = 'GET', body, signal } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
        signal,
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json();

    if (!response.ok) {
        const error = new Error(
            Object.values(payload.errors ?? {}).flat()[0]
                ?? payload.message
                ?? 'Permintaan gagal diproses.',
        );
        error.status = response.status;
        error.errors = payload.errors ?? {};
        error.redirect = payload.redirect;
        throw error;
    }

    return payload;
}

export async function apiFormRequest(path, { method = 'POST', body, signal } = {}) {
    const response = await fetch(path, {
        method,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        credentials: 'same-origin',
        body,
        signal,
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json();

    if (!response.ok) {
        const error = new Error(
            Object.values(payload.errors ?? {}).flat()[0]
                ?? payload.message
                ?? 'Permintaan gagal diproses.',
        );
        error.status = response.status;
        error.errors = payload.errors ?? {};
        error.redirect = payload.redirect;
        throw error;
    }

    return payload;
}
