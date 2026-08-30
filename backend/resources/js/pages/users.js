import { api, store } from '../api';
import { content, setActive } from '../layout';
import { badge, bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadDistricts, loadProvinces, loadRanks, loadRoles } from '../reference';

const INPUT_CLS =
    'w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all duration-200';
const LABEL_CLS = 'block text-sm font-medium text-slate-700 mb-1.5';
const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

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
        delete: store.hasPermission('users.delete'),
        view: store.hasPermission('users.view'),
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
                        ${can.view ? `<button data-view="${u.id}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</button>` : ''}
                        ${can.update ? `<span class="mx-1 text-slate-300">·</span><button data-edit="${u.id}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>` : ''}
                        ${can.update ? `<span class="mx-1 text-slate-300">·</span><button data-status="${u.id}" data-current="${u.status}" class="text-xs font-medium ${u.status === 'active' ? 'text-rose-600' : 'text-emerald-600'}">${u.status === 'active' ? 'Disable' : 'Activate'}</button>` : ''}
                        ${can.reset ? `<span class="mx-1 text-slate-300">·</span><button data-reset="${u.id}" class="text-amber-600 hover:text-amber-800 text-xs font-medium">Reset pwd</button>` : ''}
                        ${can.delete ? `<span class="mx-1 text-slate-300">·</span><button data-delete="${u.id}" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Delete</button>` : ''}
                      </td>
                    </tr>`,
                )
                .join('')}
            </tbody>
          </table>
        </div>`;

        table.querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => viewProfile(b.dataset.view)));
        table.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => renderForm(can, b.dataset.edit)));
        table.querySelectorAll('[data-status]').forEach((b) =>
            b.addEventListener('click', () => toggleStatus(b.dataset.status, b.dataset.current)),
        );
        table.querySelectorAll('[data-reset]').forEach((b) => b.addEventListener('click', () => resetPassword(b.dataset.reset)));
        table.querySelectorAll('[data-delete]').forEach((b) => b.addEventListener('click', () => deleteUser(b.dataset.delete)));
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

function viewProfile(uuid) {
    window.location.hash = `#/profile?id=${encodeURIComponent(uuid)}`;
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

async function deleteUser(uuid) {
    const confirmed = window.confirm('Delete this user? This action cannot be undone.');
    if (!confirmed) {
        return;
    }

    try {
        await api(`/users/${uuid}`, { method: 'DELETE' });
        toast('User deleted successfully.');
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
    const load = editing
        ? Promise.all([api(`/users/${uuid}`), loadRoles(), loadRanks()])
        : Promise.all([Promise.resolve(null), loadRoles(), loadRanks()]);

    load.then(async ([user, roles, ranks]) => {
        const root = content('');
        root.innerHTML = `
        <div class="max-w-4xl mx-auto pb-28">
          <button id="back" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-indigo-600 transition-colors mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Users
          </button>

          <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-800">${editing ? `Edit User — ${esc(user.name)}` : 'New User Creation'}</h2>
            <p class="mt-1 text-sm text-slate-500">${editing ? 'Update account, cadet and location details.' : 'Create a login account and, where applicable, link it to cadet and location information.'}</p>
          </div>

          <form id="user-form" class="bg-white rounded-2xl border border-slate-200">

            <section class="p-6 sm:p-8 border-b border-slate-100">
              <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide">Personal Information</h3>
              <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="${LABEL_CLS}">Full name *</label><input name="name" value="${esc(user?.name || '')}" class="${INPUT_CLS}" required></div>
                <div><label class="${LABEL_CLS}">Username *</label><input name="username" value="${esc(user?.username || '')}" class="${INPUT_CLS}" required></div>
                <div>
                  <label class="${LABEL_CLS}">Email *</label>
                  <input name="email" type="email" value="${esc(user?.email || '')}" class="${INPUT_CLS}" required>
                  <p id="email-error" class="mt-1.5 text-xs text-rose-600 hidden"></p>
                </div>
                <div><label class="${LABEL_CLS}">Contact number</label><input name="phone" value="${esc(user?.phone || '')}" class="${INPUT_CLS}"></div>
                ${editing
                ? ''
                : `
                <div>
                  <label class="${LABEL_CLS}">Password *</label>
                  <input name="password" type="password" id="new-password" class="${INPUT_CLS}" required>
                  <div id="strength-bar" class="mt-1.5 flex gap-1">
                    <span class="h-1 flex-1 rounded bg-slate-200"></span>
                    <span class="h-1 flex-1 rounded bg-slate-200"></span>
                    <span class="h-1 flex-1 rounded bg-slate-200"></span>
                    <span class="h-1 flex-1 rounded bg-slate-200"></span>
                  </div>
                  <p id="password-error" class="mt-1.5 text-xs text-rose-600 hidden"></p>
                </div>
                <div>
                  <label class="${LABEL_CLS}">Confirm password *</label>
                  <input name="password_confirmation" type="password" id="confirm-password" class="${INPUT_CLS}" required>
                </div>
                `
            }
              </div>
            </section>

            <section class="p-6 sm:p-8 border-b border-slate-100">
              <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide">Profile Picture</h3>
              <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                <div class="flex flex-col items-center gap-2">
                  <div id="photo-preview" class="h-28 w-28 rounded-full border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden text-slate-300">
                    ${user?.photo_url
                ? `<img src="${esc(user.photo_url)}" class="h-full w-full object-cover" alt="">`
                : svgAvatarPlaceholder()
            }
                  </div>
                  <span class="text-xs text-slate-400">Preview</span>
                </div>
                <div class="md:col-span-2">
                  <label id="photo-dropzone" for="photo-input" class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-8 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/40 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3-3m0 0l3 3m-3-3v9"/></svg>
                    <p class="text-sm text-slate-600"><span class="font-medium text-indigo-600">Drag profile picture here or click to browse.</span></p>
                    <p class="text-xs text-slate-400">PNG or JPG. Max size 2MB.</p>
                    <input type="file" id="photo-input" name="photo" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only">
                  </label>
                  <p id="photo-error" class="mt-1.5 text-xs text-rose-600 hidden"></p>
                </div>
              </div>
            </section>

            <section class="p-6 sm:p-8 border-b border-slate-100">
              <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide">Cadet Information</h3>
              <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="${LABEL_CLS}">Cadet number${editing ? '' : ' *'}</label>
                  <input name="cadet_number" value="${esc(user?.cadet_number || '')}" ${editing ? '' : 'required'}
                    pattern="[A-Za-z0-9\\-/]+" title="Letters, numbers, hyphens and slashes only"
                    placeholder="e.g. NCC-00123" class="${INPUT_CLS} uppercase placeholder:normal-case">
                  <p id="cadet-number-error" class="mt-1.5 text-xs text-rose-600 hidden"></p>
                </div>
                <div>
                  <label class="${LABEL_CLS}">Rank</label>
                  <div id="rank-select" class="relative"></div>
                </div>
              </div>
            </section>

            <section class="p-6 sm:p-8 border-b border-slate-100">
              <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide">Address Details</h3>
              <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="${LABEL_CLS}">Province</label>
                  <select id="geo-province" class="${INPUT_CLS}"><option value="">—</option></select>
                </div>
                <div>
                  <label class="${LABEL_CLS}">District</label>
                  <select id="geo-district" class="${INPUT_CLS}" disabled><option value="">—</option></select>
                </div>
                <div>
                  <label class="${LABEL_CLS}">Local level</label>
                  <input name="local_level" value="${esc(user?.local_level || '')}" placeholder="e.g. Kathmandu Metropolitan City" class="${INPUT_CLS}">
                </div>
                <div>
                  <label class="${LABEL_CLS}">Ward</label>
                  <input name="ward_number" value="${esc(user?.ward_number ?? '')}" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="e.g. 5" class="${INPUT_CLS}">
                </div>
              </div>
            </section>

            <section class="p-6 sm:p-8">
              <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wide">Access &amp; Permissions</h3>
              <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="${LABEL_CLS}">Roles *</label>
                  <div id="role-multiselect" class="relative"></div>
                  <p id="role-error" class="mt-1.5 text-xs text-rose-600 hidden"></p>
                </div>
                <div>
                  <label class="${LABEL_CLS}">Status *</label>
                  <select name="status" class="${INPUT_CLS}">
                    <option value="active" ${user?.status === 'active' ? 'selected' : ''}>Active</option>
                    <option value="disabled" ${user?.status === 'disabled' ? 'selected' : ''}>Disabled</option>
                  </select>
                </div>
              </div>
            </section>

          </form>

          <div id="form-actions" class="sticky bottom-0 z-10 mt-6 flex items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 backdrop-blur px-6 py-4 shadow-lg shadow-slate-900/5">
            <button type="button" id="cancel" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Cancel Action</button>
            <button type="submit" form="user-form" class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">${editing ? 'Save Changes' : 'Save and Create User Account'}</button>
          </div>
        </div>`;

        root.querySelector('#back').addEventListener('click', () => {
            state = { ...state, page: 1 };
            render();
        });
        root.querySelector('#cancel').addEventListener('click', () => render());

        const currentRoleIds = new Set((user?.roles || []).map((r) => r.id));
        const selectedRoles = new Set(currentRoleIds);
        initRoleMultiSelect(root, roles, selectedRoles);

        const rankSelect = initRankSelect(root, ranks, user?.rank_id ?? null);

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

        const geometry = { province_id: user?.province_id ?? null, district_id: user?.district_id ?? null };
        await initGeoScope(root, geometry);

        const photo = initPhotoUploader(root);

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
                const cadetNumberInput = form.querySelector('[name="cadet_number"]');
                const cadetNumberError = root.querySelector('#cadet-number-error');
                if (cadetNumberInput && !cadetNumberInput.value.trim()) {
                    if (cadetNumberError) {
                        cadetNumberError.textContent = 'Cadet number is required.';
                        cadetNumberError.classList.remove('hidden');
                    }
                    valid = false;
                }
            }
            if (photo.error) {
                valid = false;
            }
            if (!valid) {
                return;
            }

            const payload = {
                name: fd.get('name'),
                username: fd.get('username'),
                email,
                phone: fd.get('phone') || null,
                cadet_number: fd.get('cadet_number') ? String(fd.get('cadet_number')).trim().toUpperCase() : null,
                rank_id: rankSelect.get(),
                status: fd.get('status'),
                role_ids: Array.from(selectedRoles),
                province_id: geometry.province_id,
                district_id: geometry.district_id,
                local_level: fd.get('local_level') || null,
                ward_number: fd.get('ward_number') || null,
                ...(editing ? {} : { password, password_confirmation: confirmPassword }),
            };

            const body = photo.file ? toFormData(payload, photo.file) : payload;

            try {
                if (editing) {
                    if (photo.file) {
                        body.append('_method', 'PATCH');
                        await api(`/users/${uuid}`, { method: 'POST', body });
                    } else {
                        await api(`/users/${uuid}`, { method: 'PATCH', body });
                    }
                    if (store.hasPermission('users.assign-role')) {
                        if (payload.role_ids.length > 0) {
                            await api(`/users/${uuid}/roles`, { method: 'PUT', body: { role_ids: payload.role_ids } });
                        }
                        await api(`/users/${uuid}/geography`, {
                            method: 'PUT',
                            body: { province_id: payload.province_id, district_id: payload.district_id },
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

function svgAvatarPlaceholder() {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>';
}

function toFormData(payload, photoFile) {
    const fd = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
        if (value === null || value === undefined) {
            return;
        }
        if (key === 'role_ids') {
            value.forEach((id) => fd.append('role_ids[]', id));
            return;
        }
        fd.append(key, value);
    });
    fd.append('photo', photoFile);
    return fd;
}

async function initGeoScope(root, geometry) {
    const provinceSelect = root.querySelector('#geo-province');
    const districtSelect = root.querySelector('#geo-district');

    function fill(select, items, selectedId) {
        select.innerHTML = '<option value="">—</option>';
        items.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name_en;
            if (Number(selectedId) === item.id) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    const provinces = await loadProvinces();
    fill(provinceSelect, provinces, geometry.province_id);

    async function loadForProvince(provinceId, selectedDistrictId) {
        if (!provinceId) {
            districtSelect.innerHTML = '<option value="">—</option>';
            districtSelect.disabled = true;
            return;
        }
        districtSelect.disabled = false;
        const districts = await loadDistricts(provinceId);
        fill(districtSelect, districts, selectedDistrictId);
    }

    provinceSelect.addEventListener('change', async () => {
        geometry.province_id = provinceSelect.value ? Number(provinceSelect.value) : null;
        geometry.district_id = null;
        await loadForProvince(geometry.province_id, null);
    });

    districtSelect.addEventListener('change', () => {
        geometry.district_id = districtSelect.value ? Number(districtSelect.value) : null;
    });

    if (geometry.province_id) {
        await loadForProvince(geometry.province_id, geometry.district_id);
    }
}

function initRankSelect(root, ranks, selectedRankId) {
    const host = root.querySelector('#rank-select');
    let selected = selectedRankId;
    let open = false;

    function optionLabel(rank) {
        return rank.short_code ? `${rank.name_en} (${rank.short_code})` : rank.name_en;
    }

    function render() {
        const chosen = ranks.find((r) => r.id === selected);
        host.innerHTML = `
          <button type="button" id="rank-select-btn" class="${INPUT_CLS} flex items-center justify-between text-left">
            <span class="${chosen ? 'text-slate-800' : 'text-slate-400'}">${chosen ? esc(optionLabel(chosen)) : 'Select a rank…'}</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div id="rank-dropdown" class="hidden absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg max-h-56 overflow-y-auto py-1">
            <button type="button" data-rank-id="" class="w-full text-left px-3 py-2 text-sm text-slate-400 hover:bg-slate-50">—</button>
            ${ranks
                .map(
                    (r) =>
                        `<button type="button" data-rank-id="${r.id}" class="w-full text-left px-3 py-2 text-sm ${r.id === selected ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50'}">${esc(optionLabel(r))}</button>`,
                )
                .join('')}
          </div>`;

        const btn = host.querySelector('#rank-select-btn');
        const dropdown = host.querySelector('#rank-dropdown');

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            open = !open;
            dropdown.classList.toggle('hidden', !open);
        });

        dropdown.querySelectorAll('[data-rank-id]').forEach((opt) => {
            opt.addEventListener('click', () => {
                const id = opt.dataset.rankId;
                selected = id ? Number(id) : null;
                open = false;
                render();
            });
        });
    }

    document.addEventListener('click', (e) => {
        if (!host.contains(e.target)) {
            open = false;
            host.querySelector('#rank-dropdown')?.classList.add('hidden');
        }
    });

    render();

    return { get: () => selected };
}

function initPhotoUploader(root) {
    const dropzone = root.querySelector('#photo-dropzone');
    const input = root.querySelector('#photo-input');
    const preview = root.querySelector('#photo-preview');
    const error = root.querySelector('#photo-error');
    const state = { file: null, error: false };

    function showError(message) {
        state.error = true;
        error.textContent = message;
        error.classList.remove('hidden');
    }

    function clearError() {
        state.error = false;
        error.classList.add('hidden');
    }

    function accept(file) {
        if (!file) {
            return;
        }
        if (!file.type.startsWith('image/')) {
            showError('Please choose an image file.');
            return;
        }
        if (file.size > MAX_PHOTO_BYTES) {
            showError('Image must be 2MB or smaller.');
            return;
        }
        clearError();
        state.file = file;
        preview.innerHTML = `<img src="${URL.createObjectURL(file)}" class="h-full w-full object-cover" alt="">`;
    }

    input.addEventListener('change', () => accept(input.files?.[0]));

    ['dragenter', 'dragover'].forEach((evt) => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.add('border-indigo-400', 'bg-indigo-50/40');
        });
    });
    ['dragleave', 'drop'].forEach((evt) => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-indigo-400', 'bg-indigo-50/40');
        });
    });
    dropzone.addEventListener('drop', (e) => {
        const file = e.dataTransfer?.files?.[0];
        accept(file);
    });

    return state;
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
