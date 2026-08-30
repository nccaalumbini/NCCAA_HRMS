import { api, store } from '../api';
import { content, setActive } from '../layout';
import { badge, bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadRanks } from '../reference';
import { cascadeSelect } from '../cascade';

let state = { search: '', status: '', rank_id: '', page: 1 };

export async function render() {
    setActive('cadets');
    content(spinner());
    const can = {
        create: store.hasPermission('cadets.create'),
        update: store.hasPermission('cadets.update'),
        import: store.hasPermission('cadets.import'),
    };
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
          ${can.import ? '<button id="import-cadets-btn" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Import Excel</button>' : ''}
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
    root.querySelector('#import-cadets-btn')?.addEventListener('click', () => renderImportModal());

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

function renderImportModal() {
    const modalHtml = `
      <div id="cadet-import-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-4xl rounded-xl bg-white p-6 shadow-2xl space-y-5 max-h-[90vh] flex flex-col">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 class="text-lg font-bold text-slate-900">Cadet Excel / Spreadsheet Import</h3>
              <p class="text-xs text-slate-500">Upload .xlsx, .xls, or .csv files, review column mappings, preview validation, and import safely.</p>
            </div>
            <button id="btn-close-import-modal" class="text-slate-400 hover:text-slate-600">✕</button>
          </div>

          <div id="import-step-container" class="flex-1 overflow-y-auto space-y-4">
            <!-- Step 1: Upload -->
            <div id="import-upload-step" class="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center hover:border-indigo-400 transition cursor-pointer">
              <input type="file" id="import-file-input" accept=".xlsx,.xls,.csv" class="hidden">
              <div class="text-indigo-600 mb-2">
                <svg class="mx-auto h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              </div>
              <p class="text-sm font-semibold text-slate-700">Click to upload or drag and drop spreadsheet</p>
              <p class="text-xs text-slate-400 mt-1">Supports XLSX, XLS, and CSV (max 10MB, up to 2,500 rows)</p>
            </div>

            <!-- Step 2: Mapping & Preview container (injected dynamically) -->
            <div id="import-dynamic-content" class="hidden space-y-4"></div>
          </div>

          <div id="import-modal-actions" class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <button id="btn-cancel-import" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</button>
          </div>
        </div>
      </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = document.getElementById('cadet-import-modal');
    const uploadStep = modal.querySelector('#import-upload-step');
    const fileInput = modal.querySelector('#import-file-input');
    const dynamicContent = modal.querySelector('#import-dynamic-content');
    const actions = modal.querySelector('#import-modal-actions');

    const closeModal = () => modal.remove();
    modal.querySelector('#btn-close-import-modal').onclick = closeModal;
    modal.querySelector('#btn-cancel-import').onclick = closeModal;

    uploadStep.onclick = () => fileInput.click();
    uploadStep.ondragover = (e) => {
        e.preventDefault();
        uploadStep.classList.add('border-indigo-500', 'bg-indigo-50/20');
    };
    uploadStep.ondragleave = () => {
        uploadStep.classList.remove('border-indigo-500', 'bg-indigo-50/20');
    };
    uploadStep.ondrop = (e) => {
        e.preventDefault();
        uploadStep.classList.remove('border-indigo-500', 'bg-indigo-50/20');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(fileInput.files[0]);
        }
    };

    fileInput.onchange = () => {
        if (fileInput.files.length) {
            handleFileSelect(fileInput.files[0]);
        }
    };

    let currentFile = null;
    let inspectedData = null;

    async function handleFileSelect(file) {
        currentFile = file;
        uploadStep.classList.add('hidden');
        dynamicContent.classList.remove('hidden');
        dynamicContent.innerHTML = spinner();

        const formData = new FormData();
        formData.append('file', file);

        try {
            inspectedData = await api('/cadets/import/inspect', { method: 'POST', body: formData });
            renderMappingStep(inspectedData);
        } catch (err) {
            dynamicContent.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 text-xs rounded-lg">${esc(err.message)}</div>`;
            actions.innerHTML = `<button id="btn-reupload" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Upload another file</button>`;
            modal.querySelector('#btn-reupload').onclick = () => {
                uploadStep.classList.remove('hidden');
                dynamicContent.classList.add('hidden');
                actions.innerHTML = `<button id="btn-cancel-import" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</button>`;
                modal.querySelector('#btn-cancel-import').onclick = closeModal;
            };
        }
    }

    function renderMappingStep(data) {
        const fields = [
            { key: 'cadet_number', label: 'Cadet Number *', required: true },
            { key: 'name', label: 'Full Name *', required: true },
            { key: 'email', label: 'Email Address' },
            { key: 'phone', label: 'Phone Number' },
            { key: 'rank', label: 'Rank' },
            { key: 'province', label: 'Province' },
            { key: 'district', label: 'District' },
            { key: 'local_level', label: 'Local Level' },
            { key: 'ward_number', label: 'Ward Number' },
            { key: 'gender', label: 'Gender' },
            { key: 'blood_group', label: 'Blood Group' },
        ];

        dynamicContent.innerHTML = `
          <div class="space-y-4">
            <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 border border-slate-200">
              File: <strong class="text-slate-800">${esc(currentFile.name)}</strong> (${data.headers.length} columns detected)
            </div>

            <div>
              <h4 class="text-sm font-semibold text-slate-800 mb-2">Column Mapping</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                ${fields.map((f) => `
                  <div class="border border-slate-200 rounded-lg p-2.5 bg-white">
                    <label class="block text-xs font-medium text-slate-700 mb-1">${f.label}</label>
                    <select id="map-${f.key}" class="w-full text-xs rounded border border-slate-200 p-1.5 focus:border-indigo-500 focus:outline-none">
                      <option value="">— Skip / None —</option>
                      ${data.headers.map((h) => `<option value="${esc(h)}" ${data.detected_mapping[f.key] === h ? 'selected' : ''}>${esc(h)}</option>`).join('')}
                    </select>
                  </div>`).join('')}
              </div>
            </div>
          </div>`;

        actions.innerHTML = `
          <button id="btn-back-upload" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</button>
          <button id="btn-preview-import" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Preview &amp; Validate</button>`;

        modal.querySelector('#btn-back-upload').onclick = () => {
            uploadStep.classList.remove('hidden');
            dynamicContent.classList.add('hidden');
            actions.innerHTML = `<button id="btn-cancel-import" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</button>`;
            modal.querySelector('#btn-cancel-import').onclick = closeModal;
        };

        modal.querySelector('#btn-preview-import').onclick = () => runPreview(fields);
    }

    async function runPreview(fields) {
        const mapping = {};
        fields.forEach((f) => {
            const val = dynamicContent.querySelector(`#map-${f.key}`)?.value;
            if (val) mapping[f.key] = val;
        });

        dynamicContent.innerHTML = spinner();
        const formData = new FormData();
        formData.append('file', currentFile);
        formData.append('mapping', JSON.stringify(mapping));

        try {
            const previewData = await api('/cadets/import/preview', { method: 'POST', body: formData });
            renderPreviewTable(previewData, fields);
        } catch (err) {
            toast(err.message, 'error');
            renderMappingStep(inspectedData);
        }
    }

    function renderPreviewTable(previewData, fields) {
        const s = previewData.summary;
        const validRows = previewData.rows.filter((r) => r.is_valid);

        dynamicContent.innerHTML = `
          <div class="space-y-4">
            <!-- Summary Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
              <div class="rounded-lg bg-slate-50 p-2 border border-slate-200">
                <div class="font-bold text-slate-800 text-sm">${s.total_rows}</div>
                <div class="text-slate-500">Total Rows</div>
              </div>
              <div class="rounded-lg bg-emerald-50 p-2 border border-emerald-200 text-emerald-800">
                <div class="font-bold text-sm">${s.valid_rows}</div>
                <div>Valid Records</div>
              </div>
              <div class="rounded-lg bg-rose-50 p-2 border border-rose-200 text-rose-800">
                <div class="font-bold text-sm">${s.invalid_rows}</div>
                <div>Invalid / Errors</div>
              </div>
              <div class="rounded-lg bg-amber-50 p-2 border border-amber-200 text-amber-800">
                <div class="font-bold text-sm">${s.duplicates}</div>
                <div>Duplicates</div>
              </div>
            </div>

            <div class="border border-slate-200 rounded-lg overflow-hidden max-h-72 overflow-y-auto">
              <table class="min-w-full text-xs">
                <thead class="bg-slate-50 sticky top-0 text-left text-slate-500">
                  <tr>
                    <th class="px-3 py-2">Row</th>
                    <th class="px-3 py-2">Cadet No</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Rank</th>
                    <th class="px-3 py-2">Location</th>
                    <th class="px-3 py-2">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  ${previewData.rows.map((r) => `
                    <tr class="${r.is_valid ? 'hover:bg-slate-50' : 'bg-rose-50/40'}">
                      <td class="px-3 py-2 text-slate-400">${r.row_index}</td>
                      <td class="px-3 py-2 font-mono font-semibold ${r.cadet_number ? 'text-slate-800' : 'text-rose-500'}">${esc(r.cadet_number || 'Missing')}</td>
                      <td class="px-3 py-2 font-medium text-slate-800">${esc(r.name)}</td>
                      <td class="px-3 py-2 text-slate-500">${esc(r.email || '—')}</td>
                      <td class="px-3 py-2">${esc(r.rank_name || '—')}</td>
                      <td class="px-3 py-2 text-slate-500">${esc([r.province_name, r.district_name].filter(Boolean).join(' · ') || '—')}</td>
                      <td class="px-3 py-2">
                        ${r.is_valid ? '<span class="text-emerald-600 font-semibold">✓ Valid</span>' : `<span class="text-rose-600 font-semibold" title="${esc(r.errors.join(', '))}">✕ ${esc(r.errors[0])}</span>`}
                        ${r.warnings?.length ? `<div class="text-[10px] text-amber-600">${esc(r.warnings[0])}</div>` : ''}
                      </td>
                    </tr>`).join('')}
                </tbody>
              </table>
            </div>
          </div>`;

        actions.innerHTML = `
          <button id="btn-back-mapping" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to Mapping</button>
          ${validRows.length ? `<button id="btn-commit-import" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Import ${validRows.length} Valid Records</button>` : `<button disabled class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed">No Valid Records</button>`}`;

        modal.querySelector('#btn-back-mapping').onclick = () => renderMappingStep(inspectedData);
        modal.querySelector('#btn-commit-import')?.addEventListener('click', async () => {
            actions.innerHTML = spinner();
            try {
                const result = await api('/cadets/import/commit', {
                    method: 'POST',
                    body: { rows: validRows },
                });
                closeModal();
                toast(`Import complete: ${result.imported} imported, ${result.skipped} skipped.`, 'success');
                render();
            } catch (err) {
                toast(err.message || 'Import failed.', 'error');
                renderPreviewTable(previewData, fields);
            }
        });
    }
}
