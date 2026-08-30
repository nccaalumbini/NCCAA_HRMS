const BASE = '/api/v1';

const store = {
    getToken() {
        return localStorage.getItem('nccaa_token');
    },
    setToken(token) {
        localStorage.setItem('nccaa_token', token);
    },
    setUser(user) {
        localStorage.setItem('nccaa_user', JSON.stringify(user));
    },
    getUser() {
        try {
            return JSON.parse(localStorage.getItem('nccaa_user') || 'null');
        } catch {
            return null;
        }
    },
    clear() {
        localStorage.removeItem('nccaa_token');
        localStorage.removeItem('nccaa_user');
    },
    hasPermission(slug) {
        const user = this.getUser();
        return user && Array.isArray(user.permissions) && (user.permissions.includes('*') || user.permissions.includes(slug));
    },
};

export class ApiError extends Error {
    constructor(message, status, errors) {
        super(message);
        this.status = status;
        this.errors = errors || {};
    }
}

export async function api(path, { method = 'GET', body, params } = {}) {
    const token = store.getToken();
    const url = new URL(BASE + path, window.location.origin);

    if (params) {
        Object.entries(params).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) {
                url.searchParams.set(key, value);
            }
        });
    }

    const headers = { Accept: 'application/json' };
    let payload;

    if (body instanceof FormData) {
        payload = body;
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(url, { method, headers, body: payload });

    if (response.status === 401) {
        store.clear();
        window.location.hash = '#/login';
        throw new ApiError('Session expired. Please log in again.', 401);
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(data.message || 'Request failed', response.status, data.errors || {});
    }

    return data.data ?? data;
}

export function qs(params) {
    const out = [];
    Object.entries(params || {}).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            out.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
    });
    return out.length ? `?${out.join('&')}` : '';
}

export { store };
