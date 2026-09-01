import { api, store } from '../api';
import { content, setActive } from '../layout';
import { bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadPermissions } from '../reference';

let state = { search: '', page: 1 };

const ACTION_COLUMNS = ['view', 'create', 'update', 'delete', 'export', 'import'];
const COLUMNS = [
    { key: 'view', label: 'View' },
    { key: 'create', label: 'Create' },
    { key: 'update', label: 'Update' },
    { key: 'delete', label: 'Delete' },
    { key: 'export', label: 'Export' },
    { key: 'import', label: 'Import' },
    { key: 'special', label: 'Special Actions' },
];

function humanize(str) {
    return str
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function actionOf(perm) {
    const parts = perm.slug.split('.');
    const action = parts[parts.length - 1];
    return ACTION_COLUMNS.includes(action) ? action : 'special';
}

const MAX_MODULES = 5;
const PASTELS = [
    'bg-indigo-50 text-indigo-700 ring-indigo-100',
    'bg-emerald-50 text-emerald-700 ring-emerald-100',
    'bg-amber-50 text-amber-700 ring-amber-100',
    'bg-sky-50 text-sky-700 ring-sky-100',
    'bg-rose-50 text-rose-700 ring-rose-100',
    'bg-violet-50 text-violet-700 ring-violet-100',
    'bg-teal-50 text-teal-700 ring-teal-100',
    'bg-orange-50 text-orange-700 ring-orange-100',
];
const ACTION_ORDER = ['view', 'create', 'update', 'delete', 'import', 'export', 'assign-role', 'reset-password', 'manage', 'publish', 'review', 'shortlist', 'select', 'send'];
const ACTION_LABELS = {
    view: 'View', create: 'Create', update: 'Update', delete: 'Delete',
    import: 'Import', export: 'Export', 'assign-role': 'Assign Roles',
    'reset-password': 'Reset Password', manage: 'Manage', publish: 'Publish',
    review: 'Review', shortlist: 'Shortlist', select: 'Select', send: 'Send',
};

function avatarClasses(id) {
    return PASTELS[Number(id) % PASTELS.length];
}

function summaryFor(rolePerms, permissionGroups) {
    const refByModule = new Map(permissionGroups.map((g) => [g.group, g.items]));
    const order = new Map(permissionGroups.map((g, i) => [g.group, i]));
    const byModule = new Map();
    rolePerms.forEach((p) => {
        if (!byModule.has(p.group)) {
            byModule.set(p.group, []);
        }
        byModule.get(p.group).push(p);
    });
    const out = [];
    byModule.forEach((perms, mod) => {
        const ref = refByModule.get(mod) || [];
        const assigned = new Set(perms.map((p) => p.slug));
        const actions = perms.map((p) => p.slug.split('.').pop());
        const allAssigned = ref.length > 0 && ref.every((p) => assigned.has(p.slug));
        let label;
        if (allAssigned && ref.length > 1) {
            label = 'Full Access';
        } else if (actions.length === 1 && actions[0] === 'view') {
            label = 'Read-Only';
        } else {
            label = actions
                .sort((a, b) => ACTION_ORDER.indexOf(a) - ACTION_ORDER.indexOf(b))
                .map((a) => ACTION_LABELS[a] || humanize(a))
                .join(', ');
        }
        out.push({ mod, label });
    });
    out.sort((a, b) => (order.get(a.mod) ?? 99) - (order.get(b.mod) ?? 99));
    return out;
}

function moduleTag(summary) {
    return `<span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-500">
        <span class="font-medium text-slate-700">${esc(humanize(summary.mod))}</span>
        <span class="text-slate-300">·</span>${esc(summary.label)}
    </span>`;
}

function modulesMarkup(summaries, showAll) {
    if (!summaries.length) {
        return '<span class="text-xs text-slate-400">No access</span>';
    }
    const visible = showAll ? summaries : summaries.slice(0, MAX_MODULES);
    const hidden = summaries.length - visible.length;
    const trigger = hidden > 0
        ? `<button type="button" data-expand="${showAll ? 0 : 1}" class="whitespace-nowrap text-[11px] font-medium text-primary-600 transition hover:text-primary-700">${showAll ? 'Show less' : `+${hidden} more`}</button>`
        : '';
    return `<div class="flex flex-wrap items-center gap-1">${visible.map(moduleTag).join('')}${trigger}</div>`;
}

function usersBadge(count) {
    return `<span class="inline-flex min-w-[2rem] items-center justify-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">${count}</span>`;
}

export async function render() {
    setActive('roles');
    content(spinner());
    const can = {
        create: store.hasPermission('roles.create'),
        update: store.hasPermission('roles.update'),
        delete: store.hasPermission('roles.delete'),
    };
    const [data, permissionGroups] = await Promise.all([
        api('/roles', { params: { search: state.search, page: state.page, per_page: 10 } }),
        loadPermissions(),
    ]);
    const summariesById = new Map(data.items.map((role) => [role.id, summaryFor(role.permissions, permissionGroups)]));
    const total = data.meta.total;

    const root = content('');
    root.innerHTML = `
      <div class="mx-auto max-w-7xl">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">Roles &amp; Permissions</h1>
            <p class="mt-0.5 text-sm text-slate-500">${total} active system role${total === 1 ? '' : 's'} with granular permission controls.</p>
          </div>
          ${can.create ? '<button id="new-role" class="inline-flex w-fit items-center gap-1.5 rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:bg-primary-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>New Role</button>' : ''}
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3">
            <div class="relative min-w-[240px] flex-1 sm:max-w-sm">
              <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
              <input id="role-search" type="text" value="${esc(state.search)}" placeholder="Search role name or slug…"
                class="w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 py-2 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <span class="ml-auto text-xs text-slate-500" id="role-count"></span>
          </div>

          <div id="role-list" class="overflow-x-auto"></div>
          <div id="role-pagination" class="border-t border-slate-200 px-4 py-3"></div>
        </div>
      </div>`;

    root.querySelector('#role-search').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.search = e.target.value.trim();
            state.page = 1;
            render();
        }
    });
    root.querySelector('#new-role')?.addEventListener('click', () => renderForm(can));

    const list = root.querySelector('#role-list');
    const countEl = root.querySelector('#role-count');
    if (!data.items.length) {
        countEl.textContent = '0 roles';
        list.innerHTML = '<div class="px-4 py-14 text-center text-sm text-slate-400">No roles found.</div>';
    } else {
        countEl.textContent = `${data.meta.total} role${data.meta.total === 1 ? '' : 's'}`;
        list.innerHTML = `
<table class="ui-sticky-col min-w-[640px] divide-y divide-slate-200 text-sm">
          <thead>
            <tr class="bg-slate-50/70 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <th class="px-4 py-3 font-semibold">Role</th>
              <th class="px-4 py-3 font-semibold">Description</th>
              <th class="px-4 py-3 font-semibold">Module Access</th>
              <th class="px-4 py-3 text-center font-semibold">Assigned Users</th>
              <th class="px-4 py-3 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            ${data.items.map((role) => {
                const summaries = summariesById.get(role.id) || [];
                return `
              <tr class="transition hover:bg-slate-50/60">
                <td class="px-4 py-3 align-top">
                  <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[13px] font-semibold ring-1 ring-inset ${avatarClasses(role.id)}">${esc(role.name.charAt(0).toUpperCase())}</div>
                    <div>
                      <div class="font-medium text-slate-900">${esc(role.name)}</div>
                      <div class="text-xs text-slate-400">${esc(role.slug)}</div>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 align-top">
                  ${role.description
                    ? `<span class="line-clamp-2 max-w-[22rem] text-sm leading-5 text-slate-500">${esc(role.description)}</span>`
                    : '<span class="text-sm text-slate-300">—</span>'}
                </td>
                <td class="px-4 py-3 align-top" data-modules="${role.id}">${modulesMarkup(summaries, false)}</td>
                <td class="px-4 py-3 text-center align-top">${usersBadge(role.users_count)}</td>
                <td class="px-4 py-3 text-right align-top">
                  ${can.update || can.delete ? `
                  <div class="relative inline-block text-left" data-actions>
                    <button type="button" data-toggle aria-label="Role actions" aria-haspopup="true"
                      class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
                    </button>
                    <div data-menu class="absolute right-0 top-full z-20 mt-1 hidden w-48 origin-top-right rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                      ${can.update ? `<button type="button" data-edit="${role.id}" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Edit Details</button>` : ''}
                      ${can.delete ? `<button type="button" data-delete="${role.id}" data-name="${esc(role.name)}" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50">Delete role</button>` : ''}
                    </div>
                  </div>` : '<span class="text-xs text-slate-300">—</span>'}
                </td>
              </tr>`;}).join('')}
          </tbody>
        </table>`;

        list.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => renderForm(can, b.dataset.edit)));
        list.querySelectorAll('[data-delete]').forEach((b) => b.addEventListener('click', () => openDeleteModal(b.dataset.delete, b.dataset.name)));
        list.querySelectorAll('[data-expand]').forEach((b) => {
            b.addEventListener('click', () => {
                const role = data.items.find((r) => String(r.id) === String(b.closest('[data-modules]').dataset.modules));
                if (role) {
                    b.closest('[data-modules]').innerHTML = modulesMarkup(summariesById.get(role.id) || [], b.dataset.expand === '1');
                }
            });
        });
        list.querySelectorAll('[data-actions]').forEach((wrap) => {
            wrap.querySelector('[data-toggle]').addEventListener('click', (e) => {
                e.stopPropagation();
                list.querySelectorAll('[data-menu]').forEach((m) => {
                    if (m !== wrap.querySelector('[data-menu]')) {
                        m.classList.add('hidden');
                    }
                });
                wrap.querySelector('[data-menu]').classList.toggle('hidden');
            });
        });
        document.addEventListener('click', () => {
            list.querySelectorAll('[data-menu]').forEach((m) => m.classList.add('hidden'));
        });
    }

    const pag = root.querySelector('#role-pagination');
    pag.innerHTML = pagination(data.meta, (p) => {
        state.page = p;
        render();
    });
    bindPagination(pag, (p) => {
        state.page = p;
        render();
    });
}

function openDeleteModal(id, name) {
    const old = document.getElementById('role-delete-modal');
    if (old) {
        old.remove();
    }
    const modal = document.createElement('div');
    modal.id = 'role-delete-modal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-h-[calc(100dvh-2rem)] overflow-y-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-xl">
        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-100">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Delete role</h3>
        <p class="mt-1 text-sm text-slate-500">Are you sure you want to delete <span class="font-medium text-slate-700">"${esc(name)}"</span>? This action cannot be undone. Users assigned this role will lose its permissions.</p>
        <div class="mt-5 flex justify-end gap-2">
          <button id="rm-cancel" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button>
          <button id="rm-confirm" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">Delete role</button>
        </div>
      </div>`;
    document.body.appendChild(modal);

    function close() {
        modal.remove();
    }
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            close();
        }
    });
    modal.querySelector('#rm-cancel').addEventListener('click', close);
    modal.querySelector('#rm-confirm').addEventListener('click', async () => {
        modal.querySelector('#rm-confirm').disabled = true;
        try {
            await api(`/roles/${id}`, { method: 'DELETE' });
            toast('Role deleted.');
            close();
            render();
        } catch (err) {
            toast(err.message, 'error');
            close();
        }
    });
}

function checkboxMarkup(perm, checked) {
    return `<label class="group inline-flex cursor-pointer items-center" title="${esc(perm.name)}">
        <input type="checkbox" name="permission_ids" value="${perm.id}" ${checked ? 'checked' : ''} class="h-4 w-4 rounded border-slate-300 accent-primary-600 transition focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
      </label>`;
}

function renderMatrix(groupsHost, permissionGroups, currentPermIds) {
    const grid = document.createElement('div');
    grid.className = 'rounded-lg border border-slate-200';

    const headerCells = `<th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500">Capability</th>` +
        COLUMNS.map(
            (c) => `<th class="border-l border-slate-200 bg-slate-50/70 px-3 py-2.5 text-center text-xs font-semibold text-slate-500">${c.label}</th>`,
        ).join('');

    const bodyRows = permissionGroups
        .map((group) => {
            const cells = COLUMNS.map((col) => {
                const perms = group.items.filter((p) => actionOf(p) === col.key);
                if (!perms.length) {
                    return `<td class="border-l border-slate-200 px-3 py-3 text-center"><span class="text-slate-200">—</span></td>`;
                }
                if (col.key === 'special') {
                    const inner = perms
                        .map((p) => `
                          <label class="group flex cursor-pointer items-center justify-between gap-2 rounded px-1.5 py-1 transition hover:bg-slate-50" title="${esc(p.name)}">
                            <span class="text-xs text-slate-600">${humanize(p.slug.split('.').pop())}</span>
                            <input type="checkbox" name="permission_ids" value="${p.id}" ${currentPermIds.has(p.id) ? 'checked' : ''} class="h-3.5 w-3.5 rounded border-slate-300 accent-primary-600 transition focus:ring-2 focus:ring-primary-500">
                          </label>`)
                        .join('');
                    return `<td class="border-l border-slate-200 px-2 py-2 text-center">${perms.length === 1 ? checkboxMarkup(perms[0], currentPermIds.has(perms[0].id)) : `<div class="text-left">${inner}</div>`}</td>`;
                }
                return `<td class="border-l border-slate-200 px-3 py-3 text-center">${checkboxMarkup(perms[0], currentPermIds.has(perms[0].id))}</td>`;
            }).join('');

            return `
              <tr class="${group.group === 'roles' || group.group === 'permissions' ? 'bg-primary-50/30' : ''}">
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-slate-700">${humanize(group.group)}</div>
                  <div class="text-xs text-slate-400">${group.items.length} permission${group.items.length === 1 ? '' : 's'}</div>
                </td>
                ${cells}
              </tr>`;
        })
        .join('');

    grid.innerHTML = `
      <table class="ui-sticky-col min-w-[720px] divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50/70 text-left text-xs uppercase tracking-wide text-slate-500">${headerCells}</tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">${bodyRows}
        </tbody>
      </table>`;
    groupsHost.appendChild(grid);
}

async function renderForm(can, id = null) {
    content(spinner());
    const editing = !!id;
    const [role, permissionGroups] = await Promise.all([
        editing ? api(`/roles/${id}`) : Promise.resolve(null),
        loadPermissions(),
    ]);

    const root = content('');
    root.innerHTML = `
    <div class="mx-auto max-w-5xl">
      <button id="back" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-500 transition hover:text-slate-700">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>Roles
      </button>

      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 class="text-base font-semibold text-slate-900">${editing ? `Edit Role` : 'New Role'}</h2>
            <p class="mt-0.5 text-sm text-slate-500">${editing ? `Editing “${esc(role.name)}”` : 'Define a new access role and its permissions.'}</p>
          </div>
          ${editing ? `<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">${esc(role.slug)}</span>` : ''}
        </div>

        <form id="role-form">
          <div class="grid grid-cols-1 gap-4 border-b border-slate-200 px-5 py-5 sm:grid-cols-2">
            <div>
              <label for="role-name" class="mb-1.5 block text-sm font-medium text-slate-700">Role name <span class="text-rose-500" aria-hidden="true">*</span></label>
              <input name="name" id="role-name" value="${esc(role?.name || '')}" placeholder="e.g. Field Officer"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500" required>
            </div>
            <div>
              <label for="role-slug" class="mb-1.5 block text-sm font-medium text-slate-700">Slug <span class="text-xs font-normal text-slate-400">(auto-generated if blank)</span></label>
              <input name="slug" id="role-slug" value="${esc(role?.slug || '')}" placeholder="field-officer"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="sm:col-span-2">
              <label for="role-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
              <textarea name="description" id="role-description" rows="2" placeholder="Briefly describe the scope of this role…"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500">${esc(role?.description || '')}</textarea>
            </div>
          </div>

          <div class="px-5 py-5">
            <div class="mb-3 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">Capabilities</h3>
                <p class="mt-0.5 text-xs text-slate-500">Check the operations each module should expose to this role.</p>
              </div>
              <button type="button" id="toggle-all" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">Select all / none</button>
            </div>
            <div id="permission-groups" class="overflow-x-auto"></div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-4">
            <button type="button" id="cancel" class="rounded-md border border-slate-300 bg-white px-5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-md bg-primary-600 px-6 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 active:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60">${editing ? 'Save changes' : 'Create role'}</button>
          </div>
        </form>
      </div>
    </div>`;

    root.querySelector('#back').addEventListener('click', () => render());
    root.querySelector('#cancel').addEventListener('click', () => render());

    const nameInput = root.querySelector('#role-name');
    const slugInput = root.querySelector('#role-slug');
    nameInput.addEventListener('input', () => {
        if (!editing && !slugInput.value) {
            slugInput.value = nameInput.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        }
    });

    const currentPermIds = new Set((role?.permissions || []).map((p) => p.id));
    renderMatrix(root.querySelector('#permission-groups'), permissionGroups, currentPermIds);

    const toggleAllBtn = root.querySelector('#toggle-all');
    const toggleAll = (checked) => {
        root.querySelectorAll('input[name="permission_ids"]').forEach((b) => {
            b.checked = checked;
        });
    };
    toggleAllBtn.addEventListener('click', () => {
        const boxes = root.querySelectorAll('input[name="permission_ids"]');
        const allChecked = Array.from(boxes).every((b) => b.checked);
        toggleAll(!allChecked);
    });

    const form = root.querySelector('#role-form');
    const errorsHost = createErrorHost(form);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorsHost.innerHTML = '';
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        const fd = new FormData(form);
        const body = {
            name: fd.get('name'),
            slug: fd.get('slug') || null,
            description: fd.get('description') || null,
            permission_ids: fd.getAll('permission_ids').map(Number),
        };
        try {
            if (editing) {
                await api(`/roles/${id}`, { method: 'PATCH', body });
                toast('Role updated.');
            } else {
                await api('/roles', { method: 'POST', body });
                toast('Role created.');
            }
            render();
        } catch (err) {
            showErrors(errorsHost, err.errors);
            toast(err.message, 'error');
            submitBtn.disabled = false;
        }
    });
}

function createErrorHost(form) {
    const host = document.createElement('div');
    host.className = 'mb-4 space-y-1 rounded-md border border-rose-200 bg-rose-50 px-3 py-2';
    form.prepend(host);
    return host;
}

function showErrors(host, errors) {
    host.innerHTML = '';
    Object.values(errors || {}).flat().forEach((msg) => {
        const p = document.createElement('p');
        p.className = 'text-xs text-rose-700';
        p.textContent = msg;
        host.appendChild(p);
    });
}
