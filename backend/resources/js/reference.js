import { api } from './api';

const cache = new Map();

function cached(key, fn) {
    if (cache.has(key)) {
        return Promise.resolve(cache.get(key));
    }
    return fn().then((data) => {
        cache.set(key, data);
        return data;
    });
}

export function loadRoles() {
    return cached('roles', () => api('/reference/roles').then((d) => d.items));
}

export function loadPermissions() {
    return cached('permissions', () => api('/reference/permissions').then((d) => d.items));
}

export function loadRanks() {
    return cached('ranks', () => api('/reference/ranks').then((d) => d.items));
}

export function loadProvinces() {
    return cached('provinces', () => api('/reference/provinces').then((d) => d.items));
}

export async function loadDistricts(provinceId) {
    if (!provinceId) {
        return [];
    }
    return cached(`districts:${provinceId}`, () =>
        api('/reference/districts', { params: { province_id: provinceId } }).then((d) => d.items),
    );
}

export async function loadLocalLevels(districtId) {
    if (!districtId) {
        return [];
    }
    return cached(`local-levels:${districtId}`, () =>
        api('/reference/local-levels', { params: { district_id: districtId } }).then((d) => d.items),
    );
}

export async function loadWards(localLevelId) {
    if (!localLevelId) {
        return [];
    }
    return cached(`wards:${localLevelId}`, () =>
        api('/reference/wards', { params: { local_level_id: localLevelId } }).then((d) => d.items),
    );
}
