import { api, store } from '../api';
import { content, setActive } from '../layout';
import { badge, esc, spinner, toast } from '../ui';

const MODULES = ['Users', 'Cadets', 'Skills', 'Recruitments', 'Applications', 'Notifications', 'Reports', 'Geography', 'Roles'];

export async function render() {
    setActive('profile');
    content(spinner());

    const user = store.getUser();
    const hash = window.location.hash || '';
    const params = new URLSearchParams(hash.split('?')[1] || '');
    const targetId = params.get('id');

    let profile;
    if (targetId) {
        profile = await api(`/users/${targetId}`);
    } else {
        profile = await api('/profile');
    }
    const data = profile || {};

    const page = content('');
    const roles = (data.roles || []).map((role) => role.name || role.slug || role).join(', ');
    const permissionGroups = groupPermissions(data.permissions || []);
    page.innerHTML = `
      <div class="mx-auto max-w-6xl space-y-5">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
          <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Identity &amp; access</p><h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">${esc(data.name || 'User')} <span class="font-normal text-slate-400">/ Profile</span></h2></div>
          <div class="flex flex-wrap gap-2">
            <button id="edit-profile" type="button" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Edit Profile Data</button>
            <button id="change-password" type="button" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Change Password</button>
            <button id="view-activity" type="button" class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View Activity Logs</button>
          </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User overview</div>
          <div class="flex flex-col gap-5 px-5 py-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
              <div class="group relative flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-slate-100 shadow ring-1 ring-slate-200">
                ${data.photo_url ? `<img src="${esc(data.photo_url)}" alt="${esc(data.name || 'Profile')}" class="h-full w-full object-cover">` : `<span class="text-3xl font-semibold text-slate-500">${esc((data.name || 'U').charAt(0).toUpperCase())}</span>`}
                <button id="change-picture" type="button" class="absolute inset-x-1 bottom-1 rounded-full bg-slate-900/85 px-1 py-1 text-[10px] font-semibold text-white opacity-0 transition-opacity group-hover:opacity-100">Change Picture</button>
              </div>
              <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-xl font-semibold text-slate-900">${esc(data.name || 'User')}</h3>${badge(data.status || 'active')}</div><p class="mt-1 text-sm font-medium text-slate-500">@${esc(data.username || 'unassigned')}</p><p class="mt-1 truncate text-sm text-slate-600">${esc(data.email || 'Not assigned')}</p></div>
            </div>
            <div class="flex flex-col gap-2 border-l-0 border-slate-200 sm:border-l sm:pl-6"><span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Primary role</span><span class="text-sm font-semibold text-slate-900">${esc(roles || 'No role assigned')}</span><span class="text-sm text-slate-600">Rank: ${esc(formatRank(data.rank))}</span></div>
          </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="grid grid-cols-1 gap-x-10 gap-y-8 md:grid-cols-2"><div><h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Account details</h3><dl class="mt-4 divide-y divide-slate-100">${field('User ID', data.id)}${field('Username', data.username)}${field('Email', data.email)}${field('Phone', data.phone)}${field('Cadet number', data.cadet_number)}${field('Rank', formatRank(data.rank), true)}</dl></div><div><h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Address details</h3><dl class="mt-4 divide-y divide-slate-100">${field('Province', data.province?.name || data.province_name)}${field('District', data.district?.name || data.district_name)}${field('Local level', data.local_level)}${field('Ward', data.ward_number)}</dl></div></div></section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Access matrix</h3><p class="mt-1 text-sm text-slate-500">Capabilities grouped by operational module.</p></div><span class="text-xs font-medium text-slate-400">${(data.permissions || []).length} capabilities</span></div><div class="divide-y divide-slate-100">${MODULES.map((module) => permissionModule(module, permissionGroups[module.toLowerCase()] || [])).join('')}</div></section>
      </div>
    `;

    page.querySelector('#view-activity').addEventListener('click', () => toast('Activity logs are not available yet.', 'error'));
    page.querySelector('#change-password').addEventListener('click', () => toast('Use Reset Password from the Users module.', 'error'));
    page.querySelector('#change-picture').addEventListener('click', () => toast('Use Edit Profile Data to upload a picture.', 'error'));
    page.querySelector('#edit-profile').addEventListener('click', () => { window.location.hash = '#/users'; });

    if (user && data.id && String(user.id) !== String(data.id) && window.location.hash.includes('profile')) {
        const title = page.querySelector('h2');
        if (title) {
            title.textContent = `${esc(data.name || 'User')} • Profile`;
        }
    }
}

function field(label, value, alreadyFormatted = false) {
    const display = value ? `<span class="font-semibold text-slate-900">${alreadyFormatted ? value : esc(value)}</span>` : '<span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Not assigned</span>';
    return `<div class="flex items-center justify-between gap-4 py-3"><dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">${label}</dt><dd class="text-right text-sm">${display}</dd></div>`;
}

function formatRank(rank) {
    if (!rank) {
        return '';
    }
    return rank.short_code ? `${rank.name_en} (${rank.short_code})` : rank.name_en;
}

function groupPermissions(permissions) {
    return permissions.reduce((groups, permission) => {
        const [module, action = 'access'] = String(permission).split('.');
        const key = module.toLowerCase();
        groups[key] = groups[key] || [];
        groups[key].push(action.replaceAll('-', ' '));
        return groups;
    }, {});
}

function permissionModule(module, permissions) {
    return `<div class="grid grid-cols-[9rem_1fr] items-center gap-4 px-5 py-3"><span class="text-sm font-semibold text-slate-800">${module}</span><div class="flex flex-wrap gap-1.5">${permissions.length ? permissions.map((permission) => `<span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium capitalize text-slate-700">${esc(permission)}</span>`).join('') : '<span class="text-xs text-slate-400">No access</span>'}</div></div>`;
}
