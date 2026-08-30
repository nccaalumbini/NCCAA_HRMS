import { api, store } from '../api';
import { content, setActive } from '../layout';
import { badge, bindPagination, esc, fieldError, pagination, spinner, toast } from '../ui';
import { loadRoles } from '../reference';
import { cascadeSelect } from '../cascade';

let state = {
    search: '',
    page: 1,
    status: '',
    role: '',
};

export async function render() {
    setActive('users');
    content(spinner());
    const can = {
        create: store.hasPermission('users.create'),
        update: store.hasPermission('users.update'),
        reset: store.hasPermission('users.reset-password'),
        assignRole: store.hasPermission('users.assign-role'),
    };
    const data = await api('/users', {
        params: { search: state.search, status: state.status, role: state.role, page: state.page, per_page: 10 },
    });

    const root = content('');
    root.innerHTML = `
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-3 items-center">
          <input id="user-search" type="text" value="${esc(state.search)}" placeholder="Search name, email, username, phone…"
            class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <select id="user-status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            <option value="active" ${state.status === 'active' ? 'selected' : ''}>Active</option>
            <option value="disabled" ${state.status === 'disabled' ? 'selected' : ''}>Disabled</option>
          </select>
          ${can.create ? '<button id="new-user" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New User</button>' : ''}
        </div>
      </div>
      <div id="user-table"></div>
      <div id="user-pagination"></div>`;

    root.querySelector('#user-search').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.search = e.target.value.trim();
            state.page = 1;
            render();
        }
    });
    root.querySelector('#user-status').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        render();
    });
    root.querySelector('#new-user')?.addEventListener('click', () => renderForm(can));

    const table = root.querySelector('#user-table');
    if (!data.items.length) {
        table.innerHTML = '<div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-400">No users found.</div>';
    } else {
        table.innerHTML = `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
              <tr><th class="px-4 py-3 font-medium">Name</th><th class="px-4 py-3 font-medium">Email</th><th class="px-4 py-3 font-medium">Roles</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${data.items
                  .map(
                      (u) => `
                    <tr class="hover:bg-slate-50">
                      <td class="px-4 py-3">
                        <div class="font-medium text-slate-800">${esc(u.name)}</div>
                        <div class="text-xs text-slate-400">@${esc(u.username)}</div>
                      </td>
                      <td class="px-4 py-3 text-slate-600">${esc(u.email)}</td>
                      <td class="px-4 py-3 text-slate-600">${u.roles.map((r) => `<span class="inline-block mr-1 mb-1 rounded bg-indigo-50 text-indigo-700 px-2 py-0.5 text-xs">${esc(r.name)}</span>`).join('')}</td>
                      <td class="px-4 py-3">${badge(u.status)}</td>
                      <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button data-edit="${u.id}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>
                        ${can.update ? `<span class="mx-1 text-slate-300">·</span><button data-status="${u.id}" data-current="${u.status}" class="text-xs font-medium ${u.status === 'active' ? 'text-rose-600' : 'text-emerald-600'}">${u.status === 'active' ? 'Disable' : 'Activate'}</button>` : ''}
                        ${can.reset ? `<span class="mx-1 text-slate-300">·</span><button data-reset="${u.id}" class="text-amber-600 hover:text-amber-800 text-xs font-medium">Reset pwd</button>` : ''}
                      </td>
                    </tr>`,
                  )
                  .join('')}
            </tbody>
          </table>
        </div>`;

        table.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => renderForm(can, b.dataset.edit)));
        table.querySelectorAll('[data-status]').forEach((b) =>
            b.addEventListener('click', () => toggleStatus(b.dataset.status, b.dataset.current)),
        );
        table.querySelectorAll('[data-reset]').forEach((b) => b.addEventListener('click', () => resetPassword(b.dataset.reset)));
    }

    const pag = root.querySelector('#user-pagination');
    pag.innerHTML = pagination(data.meta, (p) => {
        state.page = p;
        render();
    });
    bindPagination(pag, (p) => {
        state.page = p;
        render();
    });
}

async function toggleStatus(uuid, current) {
    const action = current === 'active' ? 'disable' : 'activate';
    try {
        const data = await api(`/users/${uuid}/${action}`, { method: 'POST' });
        toast(`User ${action === 'disable' ? 'disabled' : 'activated'}.`);
        render();
    } catch (err) {
        toast(err.message, 'error');
    }
}

async function resetPassword(uuid) {
    const password = prompt('Enter a new password (min 12 characters):');
    if (!password) {
        return;
    }
    if (password.length < 12) {
        toast('Password must be at least 12 characters.', 'error');
        return;
    }
    const confirm = prompt('Confirm the new password:');
    if (password !== confirm) {
        toast('Passwords do not match.', 'error');
        return;
    }
    try {
        await api(`/users/${uuid}/reset-password`, { method: 'POST', body: { password, password_confirmation: confirm } });
        toast('Password reset successfully.');
    } catch (err) {
        toast(err.message, 'error');
    }
}

function renderForm(can, uuid = null) {
    content(spinner());
    const editing = !!uuid;
    const load = editing ? Promise.all([api(`/users/${uuid}`), loadRoles()]) : Promise.all([Promise.resolve(null), loadRoles()]);

    load.then(async ([user, roles]) => {
        const root = content('');
        root.innerHTML = `
        <div class="max-w-2xl">
          <button id="back" class="text-sm text-slate-500 hover:text-slate-700 mb-4">← Back to users</button>
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">${editing ? `Edit User — ${esc(user.name)}` : 'New User'}</h2>
            <form id="user-form" class="space-y-4">
              <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Full name</label><input name="name" value="${esc(user?.name || '')}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Username</label><input name="username" value="${esc(user?.username || '')}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label><input name="email" type="email" value="${esc(user?.email || '')}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required><p id="email-error" class="mt-1 text-xs text-rose-600 hidden"></p></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Phone</label><input name="phone" value="${esc(user?.phone || '')}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"></div>
                ${editing ? '' : `
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Password</label><input name="password" type="password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label><input name="password_confirmation" type="password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required></div>
                `}
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                  <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="active" ${user?.status === 'active' ? 'selected' : ''}>Active</option>
                    <option value="disabled" ${user?.status === 'disabled' ? 'selected' : ''}>Disabled</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Roles</label>
                <div id="role-multiselect" class="relative"></div>
                <p id="role-error" class="mt-1 text-xs text-rose-600 hidden"></p>
              </div>
              ${editing ? '' : `
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input name="password" type="password" id="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                <div id="strength-bar" class="mt-1 flex gap-1">
                  <span class="h-1 flex-1 rounded bg-slate-200"></span>
                  <span class="h-1 flex-1 rounded bg-slate-200"></span>
                  <span class="h-1 flex-1 rounded bg-slate-200"></span>
                  <span class="h-1 flex-1 rounded bg-slate-200"></span>
                </div>
                <p id="password-error" class="mt-1 text-xs text-rose-600 hidden"></p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                <input name="password_confirmation" type="password" id="confirm-password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              </div>
              `}
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1 mt-2">Geographic scope</label>
                <div id="geo-container"></div>
              </div>
              <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">${editing ? 'Save changes' : 'Create user'}</button>
                <button type="button" id="cancel" class="rounded-lg border border-slate-300 bg-slate-100 px-6 py-2.5 text-sm text-slate-600 hover:bg-slate-200">Cancel</button>
              </div>
            </form>
          </div>
        </div>`;

        root.querySelector('#back').addEventListener('click', () => {
            state = { ...state, page: 1 };
            render();
        });
        root.querySelector('#cancel').addEventListener('click', () => render());

        const currentRoleIds = new Set((user?.roles || []).map((r) => r.id));
        const selectedRoles = new Set(currentRoleIds);
        const roleMultiselect = initRoleMultiSelect(root, roles, selectedRoles);

        const passwordInput = root.querySelector('#new-password');
        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                const msg = root.querySelector('#password-error');
                if (msg) {
                    msg.classList.add('hidden');
                }
                updateStrength(root, passwordInput.value);
            });
        }
        const confirmInput = root.querySelector('#confirm-password');
        if (confirmInput) {
            confirmInput.addEventListener('input', () => {
                const msg = root.querySelector('#password-error');
                if (msg) {
                    msg.classList.add('hidden');
                }
            });
        }

        const geoContainer = root.querySelector('#geo-container');
        let geometry = { province_id: null, district_id: null };
        const geo = cascadeSelect({
            container: geoContainer,
            onData: (d) => {
                geometry.province_id = d.province_id;
                geometry.district_id = d.district_id;
            },
        });
        if (user) {
            geo.populate({ province_id: user.province_id, district_id: user.district_id });
        }

        const form = root.querySelector('#user-form');
        const errorsHost = createErrorHost(root);
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors(errorsHost);
            const fd = new FormData(form);
            const email = fd.get('email');
            const password = fd.get('password');
            const confirmPassword = fd.get('password_confirmation');

            let valid = true;
            const emailError = root.querySelector('#email-error');
            if (emailError && email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailError.textContent = 'Please enter a valid email address.';
                emailError.classList.remove('hidden');
                valid = false;
            }
            if (!editing) {
                const passwordError = root.querySelector('#password-error');
                if (!password || password.length < 12) {
                    if (passwordError) {
                        passwordError.textContent = 'Password must be at least 12 characters.';
                        passwordError.classList.remove('hidden');
                    }
                    valid = false;
                } else if (password !== confirmPassword) {
                    if (passwordError) {
                        passwordError.textContent = 'Passwords do not match.';
                        passwordError.classList.remove('hidden');
                    }
                    valid = false;
                }
                const roleError = root.querySelector('#role-error');
                if (selectedRoles.size === 0) {
                    if (roleError) {
                        roleError.textContent = 'Please select at least one role.';
                        roleError.classList.remove('hidden');
                    }
                    valid = false;
                }
            }
            if (!valid) {
                return;
            }

            const body = {
                name: fd.get('name'),
                username: fd.get('username'),
                email,
                phone: fd.get('phone') || null,
                status: fd.get('status'),
                role_ids: Array.from(selectedRoles),
                province_id: geometry.province_id,
                district_id: geometry.district_id,
                ...(editing ? {} : { password, password_confirmation: confirmPassword }),
            };
            try {
                if (editing) {
                    await api(`/users/${uuid}`, { method: 'PATCH', body });
                    if (store.hasPermission('users.assign-role')) {
                        if (body.role_ids.length > 0) {
                            await api(`/users/${uuid}/roles`, { method: 'PUT', body: { role_ids: body.role_ids } });
                        }
                        await api(`/users/${uuid}/geography`, {
                            method: 'PUT',
                            body: { province_id: body.province_id, district_id: body.district_id },
                        });
                    }
                    toast('User updated.');
                    render();
                } else {
                    await api('/users', { method: 'POST', body });
                    toast('User created.');
                    render();
                }
            } catch (err) {
                showErrors(errorsHost, err.errors);
                toast(err.message, 'error');
            }
        });
    });
}

function createErrorHost(root) {
    const host = document.createElement('div');
    host.className = 'space-y-1 mb-2';
    root.querySelector('form').prepend(host);
    return host;
}

function clearErrors(host) {
    host.innerHTML = '';
}

function showErrors(host, errors) {
    host.innerHTML = '';
    Object.values(errors || {}).flat().forEach((msg) => {
        const p = document.createElement('p');
        p.className = 'text-xs text-rose-600';
        p.textContent = msg;
        host.appendChild(p);
    });
}

function initRoleMultiSelect(root, roles, selectedRoles) {
    const host = root.querySelector('#role-multiselect');
    const roleError = root.querySelector('#role-error');

    const optionMarkup = (role) => `
      <label class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer">
        <input type="checkbox" data-role-id="${role.id}" ${selectedRoles.has(role.id) ? 'checked' : ''} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        ${esc(role.name)}
      </label>`;

    let open = false;

    host.innerHTML = `
      <div class="relative">
        <button type="button" id="role-select-btn" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-left flex items-center justify-between focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <span id="role-select-label" class="text-slate-600">Select roles…</span>
          <span class="ml-2 text-xs text-slate-400">${selectedRoles.size} selected</span>
        </button>
        <div id="role-dropdown" class="hidden absolute z-20 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg max-h-56 overflow-y-auto py-1"></div>
      </div>`;

    const btn = host.querySelector('#role-select-btn');
    const dropdown = host.querySelector('#role-dropdown');
    const label = host.querySelector('#role-select-label');

    function renderDropdown() {
        dropdown.innerHTML = roles.map(optionMarkup).join('');
        dropdown.querySelectorAll('input[data-role-id]').forEach((box) => {
            box.addEventListener('change', () => {
                const id = Number(box.dataset.roleId);
                if (box.checked) {
                    selectedRoles.add(id);
                } else {
                    selectedRoles.delete(id);
                }
                updateLabel();
                if (roleError) {
                    roleError.classList.add('hidden');
                }
            });
        });
    }

    function updateLabel() {
        const chosen = roles.filter((r) => selectedRoles.has(r.id));
        label.textContent = chosen.length ? chosen.map((r) => r.name).join(', ') : 'Select roles…';
        const countSpan = host.querySelector('#role-select-btn span:last-child');
        if (countSpan) {
            countSpan.textContent = `${selectedRoles.size} selected`;
        }
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        open = !open;
        dropdown.classList.toggle('hidden', !open);
        renderDropdown();
    });
    document.addEventListener('click', (e) => {
        if (!host.contains(e.target)) {
            open = false;
            dropdown.classList.add('hidden');
        }
    });

    updateLabel();

    return { get: () => Array.from(selectedRoles) };
}

function updateStrength(root, value) {
    const bar = root.querySelector('#strength-bar');
    if (!bar) {
        return;
    }
    const spans = bar.querySelectorAll('span');
    let score = 0;
    if (value.length >= 8) {
        score++;
    }
    if (value.length >= 12) {
        score++;
    }
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) {
        score++;
    }
    if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) {
        score++;
    }
    const colors = ['#f87171', '#fbbf24', '#a3e635', '#34d399'];
    spans.forEach((span, i) => {
        span.style.backgroundColor = i < score ? colors[score - 1] : '#e2e8f0';
    });
}
