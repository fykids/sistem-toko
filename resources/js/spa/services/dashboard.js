import { apiRequest } from './api.js';

export async function fetchDashboard(filters, signal) {
    const query = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, String(value));
        }
    });

    return apiRequest(`/api/spa/dashboard?${query}`, { signal });
}
