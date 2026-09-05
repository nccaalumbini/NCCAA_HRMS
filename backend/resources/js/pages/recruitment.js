import { api, store } from '../api';
import { content, setActive } from '../layout';
import { bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadDistricts, loadProvinces, loadRanks } from '../reference';

let state = { search: '', status: '', page: 1 };
const STATUSES = ['imported', 'outreach_sent', 'cv_requested', 'cv_submitted', 'rejected', 'converted_to_cadet'];

export async function render() {
    setActive('recruitment');
    content(spinner());
    const can = { import: store.hasPermission('recruitment.import'), action: store.hasPermission('recruitment.action'), promote: store.hasPermission('recruitment.promote-to-cadet'), communicate: store.hasPermission('recruitment.communication.send'), create: store.hasPermission('recruitment.create'), view: store.hasPermission('recruitment.view'), update: store.hasPermission('recruitment.update') || store.hasPermission('recruitment.action'), delete: store.hasPermission('recruitment.delete') };
    const data = await api('/recruitment-candidates', { params: state });
    const root = content('');
    root.innerHTML = `<div class="mx-auto max-w-7xl"><div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-medium text-indigo-600">Talent pipeline</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Recruitment &amp; Ingestion</h2><p class="mt-1 text-sm text-slate-500">Track prospective cadets from import through verified enrollment.</p></div><div class="flex gap-2">${can.create ? '<button id="add-candidate" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">Add candidate</button>' : ''}${can.import ? '<button id="import-candidates" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Import CSV ledger</button>' : ''}</div></div><div class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm"><div class="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50/60 p-4"><input id="candidate-search" value="${esc(state.search)}" placeholder="Search candidate, email, or contact…" class="w-full sm:min-w-[220px] sm:flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"><select id="candidate-status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600"><option value="">All pipeline statuses</option>${STATUSES.map((status) => `<option value="${status}" ${state.status === status ? 'selected' : ''}>${label(status)}</option>`).join('')}</select></div><div id="candidate-list" class="overflow-x-auto"></div><div id="candidate-pagination" class="border-t border-slate-100 px-4 py-3"></div></div></div>`;
    root.querySelector('#candidate-search').addEventListener('keydown', (event) => { if (event.key === 'Enter') { state.search = event.target.value.trim(); state.page = 1; render(); } });
    root.querySelector('#candidate-status').addEventListener('change', (event) => { state.status = event.target.value; state.page = 1; render(); });
    root.querySelector('#add-candidate')?.addEventListener('click', () => candidateFormModal());
    root.querySelector('#import-candidates')?.addEventListener('click', () => importModal());
    renderTable(root, data, can);
}

function renderTable(root, data, can) {
    const list = root.querySelector('#candidate-list');
    if (!data.items.length) { list.innerHTML = '<div class="p-12 text-center text-sm text-slate-400">No recruitment candidates found.</div>'; return; }
    list.innerHTML = `<table class="ui-sticky-col min-w-[780px] text-sm"><thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Full name</th><th class="px-4 py-3">Location context</th><th class="px-4 py-3">Contact details</th><th class="px-4 py-3">Skills</th><th class="px-4 py-3">Pipeline status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">${data.items.map((candidate) => `<tr class="hover:bg-slate-50/60"><td class="px-4 py-3 align-top"><div class="font-semibold text-slate-800">${esc(candidate.full_name)}</div><div class="mt-1 text-xs text-slate-400">${esc(candidate.gender || 'Gender not recorded')}</div></td><td class="px-4 py-3 align-top text-xs text-slate-600">${esc([candidate.province?.name_en, candidate.district?.name_en, candidate.local_level, candidate.ward_number ? `Ward ${candidate.ward_number}` : ''].filter(Boolean).join(' · ') || 'Not mapped')}</td><td class="px-4 py-3 align-top"><div class="text-xs font-medium text-slate-700">${candidate.contact_number ? `<a href="${phoneHref(candidate.contact_number)}" class="hover:text-indigo-700">${esc(candidate.contact_number)}</a>` : 'No phone'}</div><div class="mt-1 text-xs text-slate-400">${esc(candidate.email || 'No email')}</div></td><td class="px-4 py-3 align-top"><div class="flex max-w-48 flex-wrap gap-1">${(candidate.skills || []).slice(0, 4).map((skill) => `<span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">${esc(skill)}</span>`).join('') || '<span class="text-xs text-slate-400">—</span>'}</div></td><td class="px-4 py-3 align-top">${statusBadge(candidate.recruitment_status)}</td><td class="px-4 py-3 text-right align-top">${actionMenu(candidate, can)}</td></tr>`).join('')}</tbody></table>`;
    list.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => runAction(button.dataset.action, data.items.find((item) => String(item.id) === button.dataset.id))));
    list.querySelectorAll('[data-promote]').forEach((button) => button.addEventListener('click', () => promoteModal(data.items.find((item) => String(item.id) === button.dataset.promote))));
    list.querySelectorAll('[data-communication]').forEach((button) => button.addEventListener('click', () => communicationModal(data.items.find((item) => String(item.id) === button.dataset.id), button.dataset.communication)));
    list.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => viewCandidateModal(data.items.find((item) => String(item.id) === button.dataset.view))));
    list.querySelectorAll('[data-edit]').forEach((button) => button.addEventListener('click', () => candidateFormModal(data.items.find((item) => String(item.id) === button.dataset.edit))));
    list.querySelectorAll('[data-delete]').forEach((button) => button.addEventListener('click', () => deleteCandidateModal(data.items.find((item) => String(item.id) === button.dataset.delete))));
    const page = root.querySelector('#candidate-pagination'); page.innerHTML = pagination(data.meta); bindPagination(page, (next) => { state.page = next; render(); });
}

function hasContact(candidate, channel) {
    if (channel === 'email') return Boolean(candidate.email && String(candidate.email).trim());
    return String(candidate.contact_number || '').replace(/[^\d+]/g, '') !== '';
}

function channelAnchor({ href, title, label, tone, external = false }) {
    return `<a href="${href}"${external ? ' target="_blank" rel="noreferrer"' : ''} title="${title}" aria-label="${title}" class="inline-flex min-h-10 items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 ${tone}">${label}</a>`;
}

function channelDisabled(title, label) {
    return `<span title="${title}" aria-label="${title}" class="inline-flex min-h-10 cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-300">${label}</span>`;
}

function actionMenu(candidate, can) {
    if (!can.action && !can.promote && !can.view && !can.update && !can.delete && !candidate.contact_number && !candidate.email && !can.communicate) return '<span class="text-xs text-slate-300">—</span>';
    const name = esc(candidate.full_name);
    const hasPhone = hasContact(candidate, 'phone');
    const hasEmail = hasContact(candidate, 'email');
    return `<div class="flex justify-end gap-2">${hasPhone ? channelAnchor({ href: phoneHref(candidate.contact_number), title: `Call ${name}`, label: '<span aria-hidden="true">☎</span> Call', tone: 'hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700' }) : channelDisabled('No phone on file', '<span aria-hidden="true">☎</span> Call')}${hasPhone ? channelAnchor({ href: smsHref(candidate.contact_number), title: `Message ${name}`, label: '<span aria-hidden="true">✉</span> SMS', tone: 'hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700' }) : channelDisabled('No phone on file', '<span aria-hidden="true">✉</span> SMS')}${hasPhone ? channelAnchor({ href: whatsappHref(candidate.contact_number), title: `Open WhatsApp for ${name}`, label: '<span aria-hidden="true">◉</span> WhatsApp', tone: 'hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700', external: true }) : channelDisabled('No phone on file', '<span aria-hidden="true">◉</span> WhatsApp')}${hasEmail ? `<button data-communication="email" data-id="${candidate.id}" title="Email ${name}" aria-label="Email ${name}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><span aria-hidden="true">✉</span> Email</button>` : channelDisabled('No email on file', '<span aria-hidden="true">✉</span> Email')}${can.action || can.update || can.view || can.delete ? `<details class="relative text-left"><summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-2 min-h-10 text-xs font-medium text-slate-600 hover:bg-slate-50">Manage</summary><div class="absolute right-0 z-20 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">${can.view ? `<button data-view="${candidate.id}" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50">View details</button>` : ''}${can.update ? `<button data-edit="${candidate.id}" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50">Edit details</button>` : ''}${can.action || can.update ? `<button data-action="notes" data-id="${candidate.id}" class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50">Log phone notes</button>` : ''}${can.action ? `<button data-action="reject" data-id="${candidate.id}" class="block w-full px-3 py-2 text-left text-xs text-rose-600 hover:bg-rose-50">Mark as rejected</button>` : ''}${can.delete ? `<button data-delete="${candidate.id}" class="block w-full px-3 py-2 text-left text-xs text-rose-600 hover:bg-rose-50">Delete candidate</button>` : ''}</div></details>` : ''}${can.promote && candidate.recruitment_status !== 'converted_to_cadet' ? `<button data-promote="${candidate.id}" class="rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Promote</button>` : ''}</div>`;
}
async function runAction(action, candidate) { if (action === 'notes') { const notes = window.prompt('Add recruitment phone notes', candidate.notes || ''); if (notes === null) return; await api(`/recruitment-candidates/${candidate.id}`, { method: 'PATCH', body: { notes } }); } else { await api(`/recruitment-candidates/${candidate.id}`, { method: 'PATCH', body: { recruitment_status: 'rejected' } }); } toast('Candidate pipeline updated.', 'success'); render(); }

function viewCandidateModal(candidate) {
    const rows = [
        ['Full name', esc(candidate.full_name)],
        ['Gender', esc(candidate.gender || '—')],
        ['Pipeline status', statusBadge(candidate.recruitment_status)],
        ['Contact number', candidate.contact_number ? `<a href="${phoneHref(candidate.contact_number)}" class="text-indigo-700 hover:underline">${esc(candidate.contact_number)}</a>` : '—'],
        ['Email', esc(candidate.email || '—')],
        ['Location', esc([candidate.province?.name_en, candidate.district?.name_en, candidate.local_level, candidate.ward_number ? `Ward ${candidate.ward_number}` : ''].filter(Boolean).join(' · ') || 'Not mapped')],
        ['Skills', (candidate.skills || []).map((skill) => esc(skill)).join(', ') || '—'],
        ['Priority score', candidate.priority_score ?? '—'],
        ['Source', esc(candidate.source || '—')],
        ['Notes', esc(candidate.notes || '—')],
        ['Created', candidate.created_at ? new Date(candidate.created_at).toLocaleDateString() : '—'],
    ];
    modal(`Candidate · ${esc(candidate.full_name)}`, `<dl class="mt-5 divide-y divide-slate-100">${rows.map(([key, value]) => `<div class="flex justify-between gap-6 py-2.5"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">${key}</dt><dd class="text-right text-sm text-slate-700">${value}</dd></div>`).join('')}</dl><div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button></div>`, true);
}

const FIELD_BASE = ['border-slate-200', 'focus:border-indigo-500', 'focus:ring-indigo-500/20'];
const FIELD_ERROR = ['border-rose-400', 'bg-rose-50/30', 'text-rose-900', 'focus:border-rose-500', 'focus:ring-rose-500/20'];

function setFieldError(field, message) {
    const wrapper = document.querySelector(`#candidate-form [data-field="${field}"]`);
    if (!wrapper) return;
    wrapper.querySelector('.candidate-field-error')?.remove();
    const input = wrapper.querySelector('input, select, textarea');
    if (!input) return;
    if (message) {
        input.classList.remove(...FIELD_BASE);
        input.classList.add(...FIELD_ERROR);
        wrapper.insertAdjacentHTML('beforeend', `<p class="candidate-field-error mt-1.5 text-xs font-medium text-rose-600">${esc(message)}</p>`);
    } else {
        input.classList.remove(...FIELD_ERROR);
        input.classList.add(...FIELD_BASE);
    }
}

function showFormAlert(message) {
    const alert = document.querySelector('#candidate-alert');
    if (!alert) return;
    alert.innerHTML = message ? `<div class="mb-4 rounded-r-lg border-l-4 border-rose-500 bg-rose-50 p-3 text-sm font-medium text-rose-700">${esc(message)}</div>` : '';
}

function setSubmitting(submitting, editing) {
    const form = document.querySelector('#candidate-form');
    if (!form) return;
    form.querySelectorAll('input, select, textarea').forEach((element) => { element.disabled = submitting; });
    const submitButton = form.querySelector('#candidate-submit');
    submitButton.disabled = submitting;
    submitButton.innerHTML = submitting ? '<span class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 16 0z"></path></svg>Processing Registration...</span>' : (editing ? 'Save changes' : 'Create candidate');
}

async function candidateFormModal(candidate = null) {
    const editing = candidate !== null;
    const provinces = await loadProvinces();
    const geo = { province_id: candidate?.province_id ?? null, district_id: candidate?.district_id ?? null };
    const input = (extra = '') => `w-full rounded-lg border px-3 py-2 text-sm outline-none border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 ${extra}`;
    const required = '<span class="text-rose-500">*</span>';
    modal(editing ? `Edit ${esc(candidate.full_name)}` : 'Add recruitment candidate', `<p class="text-sm text-slate-500">${editing ? 'Update the candidate record. Contact numbers are checked against existing candidates and users.' : 'Manually add a prospective cadet to the talent pipeline.'}</p><div id="candidate-alert"></div><form id="candidate-form" class="mt-5 space-y-4" novalidate><div class="grid gap-4 sm:grid-cols-2"><div data-field="full_name"><label class="block text-sm font-medium text-slate-700">Full name ${required}<input name="full_name" value="${esc(candidate?.full_name || '')}" required autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="contact_number"><label class="block text-sm font-medium text-slate-700">Contact number ${required}</label><div class="relative mt-1"><input name="contact_number" value="${esc(candidate?.contact_number || '')}" required inputmode="tel" autocomplete="off" class="${input('pr-9')}"><span id="contact-checking" class="pointer-events-none absolute inset-y-0 right-3 hidden items-center"><svg class="h-4 w-4 animate-spin text-indigo-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 16 0z"></path></svg></span></div></div><div data-field="email"><label class="block text-sm font-medium text-slate-700">Email<input name="email" type="email" placeholder="name@example.com" value="${esc(candidate?.email || '')}" autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="gender"><label class="block text-sm font-medium text-slate-700">Gender<select name="gender" class="${input('mt-1')}"><option value="">Not recorded</option>${['male', 'female', 'other'].map((gender) => `<option value="${gender}" ${candidate?.gender === gender ? 'selected' : ''}>${gender}</option>`).join('')}</select></label></div><div data-field="province_id"><label class="block text-sm font-medium text-slate-700">Province<select id="cf-province" class="${input('mt-1')}"><option value="">—</option></select></label></div><div data-field="district_id"><label class="block text-sm font-medium text-slate-700">District<select id="cf-district" disabled class="${input('mt-1')}"><option value="">—</option></select></label></div><div data-field="local_level"><label class="block text-sm font-medium text-slate-700">Local level<input name="local_level" placeholder="Enter local municipality name" value="${esc(candidate?.local_level || '')}" autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="ward_number"><label class="block text-sm font-medium text-slate-700">Ward number<input name="ward_number" inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="e.g., 5" value="${esc(candidate?.ward_number ?? '')}" autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="skills"><label class="block text-sm font-medium text-slate-700">Skills (comma separated)<input name="skills" placeholder="e.g., First Aid, Leadership" value="${esc((candidate?.skills || []).join(', '))}" autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="priority_score"><label class="block text-sm font-medium text-slate-700">Priority score (0-100)<input name="priority_score" type="number" min="0" max="100" placeholder="e.g., 85" value="${esc(candidate?.priority_score ?? '')}" autocomplete="off" class="${input('mt-1')}"></label></div><div data-field="recruitment_status"><label class="block text-sm font-medium text-slate-700">Pipeline status<select name="recruitment_status" class="${input('mt-1')}">${STATUSES.map((status) => `<option value="${status}" ${candidate?.recruitment_status === status ? 'selected' : ''}>${label(status)}</option>`).join('')}</select></label></div><div data-field="notes"><label class="block text-sm font-medium text-slate-700 sm:col-span-2">Notes<textarea name="notes" rows="3" class="${input('mt-1')}">${esc(candidate?.notes || '')}</textarea></label></div></div><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button id="candidate-submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">${editing ? 'Save changes' : 'Create candidate'}</button></div></form>`, true);

    const provinceSelect = document.querySelector('#cf-province');
    const districtSelect = document.querySelector('#cf-district');
    provinceSelect.innerHTML = `<option value="">—</option>${provinces.map((province) => `<option value="${province.id}" ${String(geo.province_id) === String(province.id) ? 'selected' : ''}>${esc(province.name_en)}</option>`).join('')}`;

    async function loadDistrictsInto() {
        const provinceId = provinceSelect.value ? Number(provinceSelect.value) : null;
        geo.province_id = provinceId;
        districtSelect.disabled = !provinceId;
        if (!provinceId) { geo.district_id = null; districtSelect.innerHTML = '<option value="">—</option>'; return; }
        const districts = await loadDistricts(provinceId);
        districtSelect.innerHTML = `<option value="">—</option>${districts.map((district) => `<option value="${district.id}">${esc(district.name_en)}</option>`).join('')}`;
        districtSelect.value = geo.district_id !== null && districts.some((district) => Number(district.id) === Number(geo.district_id)) ? String(geo.district_id) : '';
        if (!districtSelect.value) geo.district_id = null;
    }

    provinceSelect.addEventListener('change', () => { geo.district_id = null; loadDistrictsInto(); });
    districtSelect.addEventListener('change', () => { geo.district_id = districtSelect.value ? Number(districtSelect.value) : null; });
    await loadDistrictsInto();

    const contactInput = document.querySelector('#candidate-form [name="contact_number"]');
    const checking = document.querySelector('#contact-checking');
    let contactDebounce = null;
    let contactRequestId = 0;

    function setContactChecking(on) {
        checking.classList.toggle('hidden', !on);
        checking.classList.toggle('flex', on);
    }

    contactInput.addEventListener('input', () => {
        setFieldError('contact_number', null);
        clearTimeout(contactDebounce);
        contactRequestId++;
        const raw = contactInput.value.trim();
        if (!raw) { setContactChecking(false); return; }
        setContactChecking(true);
        contactDebounce = setTimeout(async () => {
            const requestId = ++contactRequestId;
            try {
                const result = await api('/recruitment-candidates/check-contact', { params: { contact_number: raw } });
                if (requestId !== contactRequestId) return;
                const normalized = raw.replace(/\s+|-/g, '');
                if (!result.taken || (editing && normalized === candidate.contact_number)) {
                    setFieldError('contact_number', null);
                } else {
                    setFieldError('contact_number', '⚠️ This number is already assigned to a candidate profile');
                }
            } catch {
                // network hiccups must not block the form; the backend re-checks on submit
            } finally {
                if (requestId === contactRequestId) setContactChecking(false);
            }
        }, 400);
    });

    document.querySelector('#candidate-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        const payload = {
            full_name: form.get('full_name').trim(),
            contact_number: form.get('contact_number').trim(),
            email: form.get('email').trim() || null,
            gender: form.get('gender') || null,
            province_id: geo.province_id,
            district_id: geo.district_id,
            local_level: form.get('local_level').trim() || null,
            ward_number: form.get('ward_number').trim() || null,
            skills: (form.get('skills') || '').split(/[,;|\n]+/).map((skill) => skill.trim()).filter(Boolean),
            recruitment_status: form.get('recruitment_status'),
            notes: form.get('notes').trim() || null,
            priority_score: form.get('priority_score').trim() || null,
        };
        showFormAlert(null);
        setSubmitting(true, editing);
        try {
            if (editing) {
                await api(`/recruitment-candidates/${candidate.id}`, { method: 'PATCH', body: payload });
                toast('Candidate updated.', 'success');
            } else {
                await api('/recruitment-candidates', { method: 'POST', body: payload });
                toast('Candidate created.', 'success');
            }
            closeModal();
            render();
        } catch (serverError) {
            setSubmitting(false, editing);
            if (serverError.errors && Object.keys(serverError.errors).length) {
                showFormAlert(serverError.message || 'Please correct the highlighted fields and try again.');
                Object.entries(serverError.errors).forEach(([key, messages]) => {
                    const firstMessage = Array.isArray(messages) ? messages[0] : String(messages);
                    setFieldError(key, firstMessage);
                    setFieldError(key.replace(/\.\d+$/, ''), firstMessage);
                });
            } else {
                showFormAlert(serverError.message || 'Request failed.');
            }
        }
    });
}

function deleteCandidateModal(candidate) {
    modal(`Delete ${esc(candidate.full_name)}`, `<p class="text-sm text-slate-500">This marks the candidate as deleted and removes them from the recruitment pipeline. The record can no longer be promoted or contacted.</p><div class="mt-5 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">${esc(candidate.contact_number)} · ${esc(candidate.email || 'No email')}</div><div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button id="confirm-delete" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Delete candidate</button></div>`);
    document.querySelector('#confirm-delete').addEventListener('click', async () => {
        try {
            await api(`/recruitment-candidates/${candidate.id}`, { method: 'DELETE' });
            closeModal();
            toast('Candidate deleted.', 'success');
            render();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}

function phoneHref(phone) { return `tel:${normalizePhone(phone)}`; }

function smsHref(phone) { return `sms:${normalizePhone(phone)}`; }

function whatsappHref(phone, message = '') { return `https://wa.me/${normalizePhone(phone).replace(/^\+/, '')}${message ? `?text=${encodeURIComponent(message)}` : ''}`; }

function normalizePhone(phone) { const value = String(phone || '').replace(/[^\d+]/g, ''); return value.startsWith('+') ? value : `+977${value.replace(/^0+/, '')}`; }

export function communicationModal(candidate, channel, onDone = render) {
    const isEmail = channel === 'email';
    const title = isEmail ? `Email ${esc(candidate.full_name)}` : `WhatsApp ${esc(candidate.full_name)}`;
    modal(title, `<p class="text-sm text-slate-500">${isEmail ? 'Send through the configured SMTP mailer.' : 'Send through Meta WhatsApp Cloud API, if configured. Otherwise use the manual WhatsApp button.'}</p><form id="communication-form" class="mt-5 space-y-4">${isEmail ? '<label class="block text-sm font-medium text-slate-700">To<input name="to" value="' + esc(candidate.email || '') + '" readonly class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"></label><label class="block text-sm font-medium text-slate-700">Subject<input name="subject" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label>' : ''}<label class="block text-sm font-medium text-slate-700">Message<textarea name="message" rows="6" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea></label><div class="flex justify-between gap-2"><a href="${isEmail ? `mailto:${esc(candidate.email)}` : whatsappHref(candidate.contact_number)}" target="_blank" rel="noreferrer" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700">Open ${isEmail ? 'email app' : 'WhatsApp'}</a><div class="flex gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Send via ${isEmail ? 'SMTP' : 'API'}</button></div></div></form>`);
    document.querySelector('#communication-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.target);
        try {
            await api(`/recruitment-candidates/${candidate.id}/communication`, { method: 'POST', body: { channel, subject: form.get('subject'), message: form.get('message') } });
            closeModal();
            toast(`${isEmail ? 'Email' : 'WhatsApp message'} sent successfully.`, 'success');
            onDone();
        } catch (error) {
            toast(error.message, 'error');
        }
    });
}
function importModal() { modal('Import recruitment CSV', `<p class="text-sm text-slate-500">Upload a CSV exported from Excel or Google Sheets. Duplicate contact numbers are skipped and returned in the import warning log.</p><form id="import-form" class="mt-5"><input name="file" type="file" accept=".csv,text/csv" required class="block w-full rounded-lg border border-slate-200 p-2 text-sm"><div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Start import</button></div></form>`); document.querySelector('#import-form').addEventListener('submit', async (event) => { event.preventDefault(); const result = await api('/recruitment-candidates/import', { method: 'POST', body: new FormData(event.target) }); closeModal(); toast(`${result.imported} imported · ${result.duplicates} duplicates · ${result.skipped} skipped`, 'success'); if (result.warnings?.length) window.alert(`Import warnings:\n\n${result.warnings.join('\n')}`); render(); }); }
export async function promoteModal(candidate, onDone = render) { const ranks = await loadRanks(); const username = candidate.full_name.toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.|\.$/g, '').slice(0, 42); modal(`Promote ${esc(candidate.full_name)} to Cadet`, `<p class="text-sm text-slate-500">Creates a linked active user and cadet record using the pre-filled recruitment profile.</p><form id="promote-form" class="mt-5 space-y-4"><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-slate-700">Cadet number<input name="cadet_number" placeholder="NCC-XXXXX" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-sm font-medium text-slate-700">Verified rank<select name="rank_id" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Select rank</option>${ranks.map((rank) => `<option value="${rank.id}">${esc(rank.name_en)}</option>`).join('')}</select></label><label class="text-sm font-medium text-slate-700">Username<input name="username" value="${esc(username)}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-sm font-medium text-slate-700">Temporary password<input name="password" type="password" required minlength="12" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="text-sm font-medium text-slate-700 sm:col-span-2">Confirm temporary password<input name="password_confirmation" type="password" required minlength="12" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label></div><div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">${esc(candidate.full_name)} · ${esc(candidate.email || 'No email')} · ${esc(candidate.contact_number)}</div><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Confirm promotion</button></div></form>`); document.querySelector('#promote-form').addEventListener('submit', async (event) => { event.preventDefault(); const form = new FormData(event.target); await api(`/recruitment-candidates/${candidate.id}/promote`, { method: 'POST', body: Object.fromEntries(form) }); closeModal(); toast('Candidate promoted to active cadet.', 'success'); onDone(); }); }
function modal(title, body, wide = false) { document.body.insertAdjacentHTML('beforeend', `<div id="recruitment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"><div class="w-full max-h-[calc(100dvh-2rem)] overflow-y-auto ${wide ? 'max-w-2xl' : 'max-w-lg'} rounded-xl bg-white p-6 shadow-xl"><div class="flex items-start justify-between gap-4"><h3 class="text-lg font-bold text-slate-900">${title}</h3><button data-close aria-label="Close dialog" class="text-slate-400">✕</button></div>${body}</div></div>`); document.querySelectorAll('#recruitment-modal [data-close]').forEach((button) => button.addEventListener('click', closeModal)); }
function closeModal() { document.getElementById('recruitment-modal')?.remove(); }
function label(status) { return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function statusBadge(status) { const tones = { imported: 'bg-slate-100 text-slate-600', outreach_sent: 'bg-sky-50 text-sky-700', cv_requested: 'bg-amber-50 text-amber-700', cv_submitted: 'bg-violet-50 text-violet-700', rejected: 'bg-rose-50 text-rose-700', converted_to_cadet: 'bg-emerald-50 text-emerald-700' }; return `<span class="inline-flex rounded-full px-2 py-1 text-xs font-medium ${tones[status] || tones.imported}">${label(status)}</span>`; }
