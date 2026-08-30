import { api, store } from '../api';
import { content, setActive } from '../layout';
import { badge, bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadRanks } from '../reference';
import { cascadeSelect } from '../cascade';

let state = { search: '', status: '', rank_id: '', page: 1 };

export async function render() {
    setActive('cadets');
    content(spinner());
    const can = { create: store.hasPermission('cadets.create'), update: store.hasPermission('cadets.update') };
    const data = await api('/cadets', {
        params: { search: state.search, status: state.status, rank_id: state.rank_id, page: state.page, per_page: 10 },
    });
    const ranks = await loadRanks().catch(() => []);

    const root = content('');
    root.innerHTML = `
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-3 items-center">
          <input id="cadet-search" type="text" value="${esc(state.search)}" placeholder="Search name, cadet number, email…"
            class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <select id="cadet-status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            ${['active', 'inactive', 'suspended', 'graduated'].map((s) => `<option value="${s}" ${state.status === s ? 'selected' : ''}>${s}</option>`).join('')}
          </select>
          <select id="cadet-rank" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">All ranks</option>
            ${ranks.map((r) => `<option value="${r.id}" ${String(state.rank_id) === String(r.id) ? 'selected' : ''}>${esc(r.name_en)}</option>`).join('')}
          </select>
          ${can.create ? '<button id="new-cadet" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">New Cadet</button>' : ''}
        </div>
      </div>
      <div id="cadet-table"></div>
      <div id="cadet-pagination"></div>`;

    root.querySelector('#cadet-search').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.search = e.target.value.trim();
            state.page = 1;
            render();
        }
    });
    root.querySelector('#cadet-status').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        render();
    });
    root.querySelector('#cadet-rank').addEventListener('change', (e) => {
        state.rank_id = e.target.value;
        state.page = 1;
        render();
    });
    root.querySelector('#new-cadet')?.addEventListener('click', () => renderForm(can));

    const table = root.querySelector('#cadet-table');
    if (!data.items.length) {
        table.innerHTML = '<div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-400">No cadets found.</div>';
    } else {
        table.innerHTML = `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
              <tr><th class="px-4 py-3 font-medium">Name</th><th class="px-4 py-3 font-medium">Cadet No.</th><th class="px-4 py-3 font-medium">Rank</th><th class="px-4 py-3 font-medium">District</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3 font-medium text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${data.items
                  .map(
                      (c) => `
                    <tr class="hover:bg-slate-50">
                      <td class="px-4 py-3 font-medium text-slate-800">${esc(c.name)}</td>
                      <td class="px-4 py-3 text-slate-600">${esc(c.cadet_number)}</td>
                      <td class="px-4 py-3 text-slate-600">${c.rank ? esc(c.rank.short_code || c.rank.name_en) : '—'}</td>
                      <td class="px-4 py-3 text-slate-600">${c.district ? esc(c.district.name_en) : '—'}</td>
                      <td class="px-4 py-3">${badge(c.status)}</td>
                      <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button data-view="${c.id}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View</button>
                        ${can.update ? `<span class="mx-1 text-slate-300">·</span><button data-edit="${c.id}" class="text-amber-600 hover:text-amber-800 text-xs font-medium">Edit</button>` : ''}
                      </td>
                    </tr>`,
                  )
                  .join('')}
            </tbody>
          </table>
        </div>`;

        table.querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => renderView(b.dataset.view)));
        table.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => renderForm(can, b.dataset.edit)));
    }

    const pag = root.querySelector('#cadet-pagination');
    pag.innerHTML = pagination(data.meta, (p) => {
        state.page = p;
        render();
    });
    bindPagination(pag, (p) => {
        state.page = p;
        render();
    });
}

async function renderView(uuid) {
    content(spinner());
    const c = await api(`/cadets/${uuid}`);
    const p = c.profile || {};
    const root = content('');
    root.innerHTML = `
      <button id="back" class="text-sm text-slate-500 hover:text-slate-700 mb-4">← Back to cadets</button>
      <div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-xl font-semibold text-slate-800">${esc(c.name)}</h2>
            <p class="text-sm text-slate-500">${esc(c.cadet_number)}${c.rank ? ` · ${esc(c.rank.name_en)}` : ''}</p>
          </div>
          ${badge(c.status)}
        </div>
        <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
          ${field('Email', c.email)}
          ${field('Phone', c.phone)}
          ${field('Province', c.province?.name_en)}
          ${field('District', c.district?.name_en)}
          ${field('Date of birth', p.date_of_birth)}
          ${field('Gender', p.gender)}
          ${field('Blood group', p.blood_group)}
          ${field('Father', p.father_name)}
          ${field('Mother', p.mother_name)}
          ${field('Guardian', p.guardian_name)}
          ${field('Guardian phone', p.guardian_phone)}
          ${field('Citizenship no.', p.citizenship_number)}
          ${field('Local address', p.local_address)}
          ${field('Enrollment date', p.enrollment_date)}
        </dl>
      </div>`;
    root.querySelector('#back').addEventListener('click', () => render());
}

function field(label, value) {
    return `<div><dt class="text-slate-400">${enc(label)}</dt><dd class="font-medium text-slate-800">${enc(value) || '—'}</dd></div>`;
}
function enc(v) {
    return esc(v ?? '');
}

function renderForm(can, uuid = null) {
    content(spinner());
    const editing = !!uuid;
    const load = editing ? Promise.all([api(`/cadets/${uuid}`), loadRanks()]) : Promise.all([Promise.resolve(null), loadRanks()]);

    load.then(async ([cadet, ranks]) => {
        const p = cadet?.profile || {};
        const root = content('');
        root.innerHTML = `
        <div class="max-w-3xl">
          <button id="back" class="text-sm text-slate-500 hover:text-slate-700 mb-4">← Back to cadets</button>
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">${editing ? `Edit Cadet — ${esc(cadet.name)}` : 'New Cadet'}</h2>
            <form id="cadet-form" class="space-y-4">
              <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Cadet number *</label><input name="cadet_number" value="${enc(cadet?.cadet_number)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Full name *</label><input name="name" value="${enc(cadet?.name)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Rank</label>
                  <select name="rank_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    ${ranks.map((r) => `<option value="${r.id}" ${cadet?.rank?.id === r.id ? 'selected' : ''}>${esc(r.name_en)}</option>`).join('')}
                  </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                  <select name="status" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    ${['active', 'inactive', 'suspended', 'graduated'].map((s) => `<option value="${s}" ${cadet?.status === s ? 'selected' : ''}>${s}</option>`).join('')}
                  </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label><input name="email" type="email" value="${enc(cadet?.email)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Phone</label><input name="phone" value="${enc(cadet?.phone)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Address (province → ward)</label>
                <div id="geo-container"></div>
              </div>

              <div class="border-t border-slate-100 pt-4">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Cadet Profile</h3>
                <div class="grid sm:grid-cols-2 gap-4">
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Date of birth</label><input name="profile[date_of_birth]" type="date" value="${enc(p.date_of_birth)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                    <select name="profile[gender]" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                      <option value="">—</option>
                      ${['male', 'female', 'other'].map((g) => `<option value="${g}" ${p.gender === g ? 'selected' : ''}>${g}</option>`).join('')}
                    </select>
                  </div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Blood group</label>
                    <select name="profile[blood_group]" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                      <option value="">—</option>
                      ${['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map((b) => `<option value="${b}" ${p.blood_group === b ? 'selected' : ''}>${b}</option>`).join('')}
                    </select>
                  </div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Enrollment date</label><input name="profile[enrollment_date]" type="date" value="${enc(p.enrollment_date)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Father's name</label><input name="profile[father_name]" value="${enc(p.father_name)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Mother's name</label><input name="profile[mother_name]" value="${enc(p.mother_name)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Guardian name</label><input name="profile[guardian_name]" value="${enc(p.guardian_name)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Guardian phone</label><input name="profile[guardian_phone]" value="${enc(p.guardian_phone)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Citizenship number</label><input name="profile[citizenship_number]" value="${enc(p.citizenship_number)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                  <div><label class="block text-sm font-medium text-slate-700 mb-1">Local address</label><input name="profile[local_address]" value="${enc(p.local_address)}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                </div>
              </div>

              <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">${editing ? 'Save changes' : 'Create cadet'}</button>
                <button type="button" id="cancel" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600">Cancel</button>
              </div>
            </form>
          </div>
        </div>`;

        root.querySelector('#back').addEventListener('click', () => render());
        root.querySelector('#cancel').addEventListener('click', () => render());

        let geometry = { province_id: null, district_id: null, local_level_id: null, ward_id: null };
        const geo = cascadeSelect({
            container: root.querySelector('#geo-container'),
            onData: (d) => {
                geometry = d;
            },
        });
        if (cadet) {
            geo.populate({
                province_id: cadet.province_id,
                district_id: cadet.district_id,
                local_level_id: cadet.local_level_id,
                ward_id: cadet.ward_id,
            });
        }

        const form = root.querySelector('#cadet-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const toNull = (v) => (v === '' ? null : v);
            const body = {
                cadet_number: fd.get('cadet_number'),
                name: fd.get('name'),
                rank_id: toNull(fd.get('rank_id')),
                status: fd.get('status'),
                email: toNull(fd.get('email')),
                phone: toNull(fd.get('phone')),
                province_id: geometry.province_id,
                district_id: geometry.district_id,
                local_level_id: geometry.local_level_id,
                ward_id: geometry.ward_id,
                profile: {
                    date_of_birth: toNull(fd.get('profile[date_of_birth]')),
                    gender: toNull(fd.get('profile[gender]')),
                    blood_group: toNull(fd.get('profile[blood_group]')),
                    enrollment_date: toNull(fd.get('profile[enrollment_date]')),
                    father_name: toNull(fd.get('profile[father_name]')),
                    mother_name: toNull(fd.get('profile[mother_name]')),
                    guardian_name: toNull(fd.get('profile[guardian_name]')),
                    guardian_phone: toNull(fd.get('profile[guardian_phone]')),
                    citizenship_number: toNull(fd.get('profile[citizenship_number]')),
                    local_address: toNull(fd.get('profile[local_address]')),
                },
            };
            try {
                if (editing) {
                    await api(`/cadets/${uuid}`, { method: 'PATCH', body });
                    toast('Cadet updated.');
                } else {
                    await api('/cadets', { method: 'POST', body });
                    toast('Cadet created.');
                }
                render();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    });
}
