export async function fetchDashboard(filters, signal) {
    const query = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, String(value));
        }
    });

    const response = await fetch(`/api/spa/dashboard?${query}`, {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        signal,
    });
    const payload = await response.json();

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
        throw new Error(validationMessage ?? payload.message ?? 'Data dashboard gagal dimuat.');
    }

    return payload;
}
