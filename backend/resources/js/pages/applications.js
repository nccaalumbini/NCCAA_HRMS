import { api, store } from '../api';
import { content, setActive } from '../layout';
import { bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadDistricts, loadProvinces } from '../reference';
import { communicationModal, promoteModal } from './recruitment';

const PIPELINE = ['applied', 'under_review', 'shortlisted', 'interview_scheduled', 'interviewed', 'offer_extended', 'converted_to_cadet', 'rejected', 'withdrawn'];
const TERMINAL = ['converted_to_cadet', 'rejected', 'withdrawn'];
const REJECTION_REASONS = ['Not enough experience', 'Position filled', 'Incomplete documents', 'Other'];
const DIVISIONS = ['junior', 'senior'];
const DEFAULT_SUBJECT = 'NCCAA Recruitment – Next Steps';

const TONE = {
    applied: 'bg-rose-400',
    under_review: 'bg-amber-400',
    shortlisted: 'bg-sky-400',
    interview_scheduled: 'bg-violet-400',
    interviewed: 'bg-fuchsia-400',
    offer_extended: 'bg-indigo-400',
    converted_to_cadet: 'bg-emerald-500',
    rejected: 'bg-slate-400',
    withdrawn: 'bg-slate-300',
};

let state = {
    view: 'board',
    search: '',
    stage: '',
    skill_category_id: '',
    division: '',
    province_id: '',
    district_id: '',
    date_from: '',
    date_to: '',
    sort: 'desc',
    page: 1,
};

const selected = new Set();
let categoriesCache = [];
let provincesCache = [];
let currentItems = [];
let drawerIndex = -1;
let drawerList = [];

function label(value) {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function stageBadge(stage) {
    const badgeTone = {
        applied: 'bg-slate-100 text-slate-600',
        under_review: 'bg-amber-50 text-amber-700 border border-amber-200',
        shortlisted: 'bg-sky-50 text-sky-700 border border-sky-200',
        interview_scheduled: 'bg-violet-50 text-violet-700 border border-violet-200',
        interviewed: 'bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200',
        offer_extended: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        converted_to_cadet: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        rejected: 'bg-rose-50 text-rose-700 border border-rose-200',
        withdrawn: 'bg-slate-100 text-slate-500',
    };
    return `<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeTone[stage] || badgeTone.applied}">${label(stage)}</span>`;
}

function locationText(app) {
    return [app.province?.name_en, app.district?.name_en].filter(Boolean).join(' · ') || 'Not mapped';
}

function fmtDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString();
}

function fmtDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return `${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
}

function normalizePhone(phone) {
    const value = String(phone || '').replace(/[^\d+]/g, '');
    return value.startsWith('+') ? value : `+977${value.replace(/^0+/, '')}`;
}

function phoneHref(phone) { return `tel:${normalizePhone(phone)}`; }

function smsHref(phone) { return `sms:${normalizePhone(phone)}`; }

function whatsappHref(phone, message = '') {
    const base = `https://wa.me/${normalizePhone(phone).replace(/^\+/, '')}`;
    return message ? `${base}?text=${encodeURIComponent(message)}` : base;
}

function hasContact(app, channel) {
    if (channel === 'email') return Boolean(app.email && String(app.email).trim());
    return String(app.contact_number || '').replace(/[^\d+]/g, '') !== '';
}

function listParams() {
    return {
        search: state.search,
        stage: state.stage,
        skill_category_id: state.skill_category_id,
        division: state.division,
        province_id: state.province_id,
        district_id: state.district_id,
        date_from: state.date_from,
        date_to: state.date_to,
        sort: state.sort,
        per_page: state.view === 'board' ? 100 : 20,
        page: state.page,
    };
}

export async function render() {
    setActive('applications');
    content(spinner());

    const can = {
        manage: store.hasPermission('applications.manage'),
        note: store.hasPermission('applications.notes.add'),
        promote: store.hasPermission('recruitment.promote-to-cadet'),
        communicate: store.hasPermission('recruitment.communication.send'),
    };

    const [provinces, categories, stats, data] = await Promise.all([
        loadProvinces().catch(() => []),
        api('/job-categories').catch(() => ({ items: [] })),
        api('/applications/stats').catch(() => ({ total: 0, month_total: 0, by_stage: [], by_category: [], avg_time_to_decision_days: null })),
        api('/applications', { params: listParams() }),
    ]);
    provincesCache = provinces;
    categoriesCache = categories.items || [];
    currentItems = data.items || [];
    const districts = state.province_id ? await loadDistricts(state.province_id).catch(() => []) : [];

    const root = content('');
    root.innerHTML = `
    <div class="mx-auto max-w-[1600px]">
      <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm font-medium text-indigo-600">Online applications</p>
          <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Applications Pipeline</h2>
          <p class="mt-1 text-sm text-slate-500">Review public job-portal submissions and move candidates through the review flow.</p>
        </div>
        <div class="flex gap-2">
          <button id="app-export" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Export CSV</button>
          <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 text-sm">
            <button id="view-board" class="rounded-md px-3 py-2 font-semibold transition ${state.view === 'board' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}">Board</button>
            <button id="view-list" class="rounded-md px-3 py-2 font-semibold transition ${state.view === 'list' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'}">List</button>
          </div>
        </div>
      </div>

      <div id="apps-stats" class="mb-5"></div>

      <div id="apps-filters" class="mb-4"></div>

      <div id="apps-view"></div>
      <div id="apps-pagination"></div>
    </div>`;

    root.querySelector('#view-board').addEventListener('click', () => { state.view = 'board'; state.page = 1; selected.clear(); render(); });
    root.querySelector('#view-list').addEventListener('click', () => { state.view = 'list'; state.page = 1; selected.clear(); render(); });
    root.querySelector('#app-export').addEventListener('click', exportCsv);

    renderStats(root.querySelector('#apps-stats'), stats);
    renderFilters(root.querySelector('#apps-filters'), districts, can);
    if (state.view === 'board') {
        renderBoard(root.querySelector('#apps-view'), currentItems, can);
    } else {
        renderList(root.querySelector('#apps-view'), currentItems, can);
    }
    renderPagination(root.querySelector('#apps-pagination'), data.meta);
}

function renderStats(container, stats) {
    const byStage = (stats.by_stage || []).slice(0, 6);
    container.innerHTML = `
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Total applications</div>
        <div class="mt-1 text-2xl font-bold text-slate-900">${stats.total ?? 0}</div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">This month</div>
        <div class="mt-1 text-2xl font-bold text-indigo-600">${stats.month_total ?? 0}</div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Avg. days to decision</div>
        <div class="mt-1 text-2xl font-bold text-slate-900">${stats.avg_time_to_decision_days ?? '—'}</div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Top categories</div>
        <div class="mt-1 space-y-1">
          ${(stats.by_category || []).slice(0, 3).map((c) => `<div class="flex items-center justify-between gap-2 text-xs"><span class="truncate text-slate-600">${esc(c.name)}</span><span class="font-semibold text-slate-800">${c.count}</span></div>`).join('') || '<div class="text-sm text-slate-400">—</div>'}
        </div>
      </div>
    </div>
    ${byStage.length ? `<div class="mt-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex flex-wrap gap-2">${byStage.map((item) => `<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200 px-2.5 py-1 text-xs text-slate-600"><span class="h-2 w-2 rounded-full ${TONE[item.stage] || TONE.applied}"></span>${esc(label(item.stage))} · <strong>${item.count}</strong></span>`).join('')}</div></div>` : ''}`;
}

function renderFilters(container, districts, can) {
    const inputCls = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100';
    container.innerHTML = `
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50/60 p-3">
        <input id="app-search" value="${esc(state.search)}" placeholder="Search name, reference, email or contact…" class="w-full min-w-[220px] flex-1 ${inputCls}">
        <select id="app-stage" class="${inputCls}">
          <option value="">All stages</option>
          ${PIPELINE.map((stage) => `<option value="${stage}" ${state.stage === stage ? 'selected' : ''}>${label(stage)}</option>`).join('')}
        </select>
        <select id="app-category" class="${inputCls}">
          <option value="">All categories</option>
          ${categoriesCache.map((category) => `<option value="${category.id}" ${String(state.skill_category_id) === String(category.id) ? 'selected' : ''}>${esc(category.name)}</option>`).join('')}
        </select>
        <select id="app-division" class="${inputCls}">
          <option value="">All divisions</option>
          ${DIVISIONS.map((division) => `<option value="${division}" ${state.division === division ? 'selected' : ''}>${label(division)}</option>`).join('')}
        </select>
        <select id="app-province" class="${inputCls}">
          <option value="">All provinces</option>
          ${provincesCache.map((province) => `<option value="${province.id}" ${String(state.province_id) === String(province.id) ? 'selected' : ''}>${esc(province.name_en)}</option>`).join('')}
        </select>
        <select id="app-district" class="${inputCls}" ${state.province_id ? '' : 'disabled'}>
          <option value="">All districts</option>
          ${districts.map((district) => `<option value="${district.id}" ${String(state.district_id) === String(district.id) ? 'selected' : ''}>${esc(district.name_en)}</option>`).join('')}
        </select>
        <input id="app-from" type="date" value="${esc(state.date_from)}" class="${inputCls}" title="Applied from">
        <input id="app-to" type="date" value="${esc(state.date_to)}" class="${inputCls}" title="Applied to">
        <select id="app-sort" class="${inputCls}">
          <option value="desc" ${state.sort === 'desc' ? 'selected' : ''}>Newest first</option>
          <option value="asc" ${state.sort === 'asc' ? 'selected' : ''}>Oldest first</option>
        </select>
        <button id="app-clear" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 hover:bg-slate-50">Clear</button>
      </div>
      ${can.manage ? `<div id="bulk-bar" class="hidden flex-wrap items-center gap-3 border-b border-slate-100 bg-indigo-50/50 p-3 text-sm"></div>` : ''}
    </div>`;

    const search = container.querySelector('#app-search');
    search.addEventListener('keydown', (event) => { if (event.key === 'Enter') { applyFilter('search', event.target.value.trim()); } });
    container.querySelector('#app-stage').addEventListener('change', (event) => applyFilter('stage', event.target.value));
    container.querySelector('#app-category').addEventListener('change', (event) => applyFilter('skill_category_id', event.target.value));
    container.querySelector('#app-division').addEventListener('change', (event) => applyFilter('division', event.target.value));
    container.querySelector('#app-province').addEventListener('change', async (event) => {
        state.province_id = event.target.value;
        state.district_id = '';
        state.page = 1;
        render();
    });
    container.querySelector('#app-district').addEventListener('change', (event) => applyFilter('district_id', event.target.value));
    container.querySelector('#app-from').addEventListener('change', (event) => applyFilter('date_from', event.target.value));
    container.querySelector('#app-to').addEventListener('change', (event) => applyFilter('date_to', event.target.value));
    container.querySelector('#app-sort').addEventListener('change', (event) => applyFilter('sort', event.target.value));
    container.querySelector('#app-clear').addEventListener('click', () => {
        state = { view: state.view, search: '', stage: '', skill_category_id: '', division: '', province_id: '', district_id: '', date_from: '', date_to: '', sort: 'desc', page: 1 };
        selected.clear();
        render();
    });
}

function applyFilter(key, value) {
    state[key] = value;
    state.page = 1;
    render();
}

function renderPagination(container, meta) {
    if (state.view !== 'list' || !meta || meta.last_page <= 1) {
        container.innerHTML = '';
        return;
    }
    container.innerHTML = `<div class="mt-4">${pagination(meta)}</div>`;
    bindPagination(container.querySelector('div'), (next) => {
        state.page = next;
        render();
    });
}

// ---------------- BOARD VIEW ----------------

function renderBoard(container, items, can) {
    if (!items.length) {
        container.innerHTML = `<div class="rounded-xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-400 shadow-sm">No applications match the current filters.</div>`;
        return;
    }
    const byStage = {};
    PIPELINE.forEach((stage) => { byStage[stage] = []; });
    items.forEach((app) => { (byStage[app.stage] || (byStage[app.stage] = [])).push(app); });

    const totalFetched = items.length;
    container.innerHTML = `
    <div class="flex gap-3 overflow-x-auto pb-3">
      ${PIPELINE.map((stage) => `
        <div data-drop-stage="${stage}" class="relative flex w-72 shrink-0 flex-col rounded-xl border border-slate-200 bg-slate-50/70">
          <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2.5">
            <div class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded-full ${TONE[stage]}"></span>
              <span class="text-sm font-semibold text-slate-700">${label(stage)}</span>
            </div>
            <span class="rounded-full bg-white border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-500">${byStage[stage].length}</span>
          </div>
          <div class="h-1 w-full ${TONE[stage]} rounded-t-none"></div>
          <div class="flex flex-1 flex-col gap-2 overflow-y-auto p-2">
            ${byStage[stage].map((app) => boardCard(app, can)).join('') || '<div class="px-2 py-6 text-center text-xs text-slate-300">No applications</div>'}
          </div>
        </div>`).join('')}
    </div>
    ${totalFetched >= 100 ? '<div class="mt-2 text-center text-xs text-slate-400">Showing the first 100 applications that match. Use filters or switch to List view for the full set.</div>' : ''}`;

    container.querySelectorAll('[data-drop-stage]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
            column.classList.add('ring-2', 'ring-indigo-300');
        });
        column.addEventListener('dragleave', () => column.classList.remove('ring-2', 'ring-indigo-300'));
        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('ring-2', 'ring-indigo-300');
            const id = Number(event.dataTransfer.getData('text/plain'));
            const app = items.find((item) => Number(item.id) === id) || currentItems.find((item) => Number(item.id) === id);
            if (app) stageChangeFlow(app, column.dataset.dropStage, can);
        });
    });

    container.querySelectorAll('[data-open-app]').forEach((button) => {
        button.addEventListener('click', () => openDrawer(Number(button.dataset.openApp), items, can));
    });
    container.querySelectorAll('[data-promote-app]').forEach((button) => {
        const app = items.find((item) => Number(item.id) === Number(button.dataset.promoteApp));
        button.addEventListener('click', () => promoteModal(app, () => reload()));
    });
    container.querySelectorAll('[data-email-app]').forEach((button) => {
        const app = items.find((item) => Number(item.id) === Number(button.dataset.emailApp));
        button.addEventListener('click', () => communicationModal(app, 'email', () => reload()));
    });
}

function boardCard(app, can) {
    const draggable = can.manage && app.stage !== 'converted_to_cadet';
    return `
    <div draggable="${draggable}" data-app-id="${app.id}" class="cursor-pointer rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:border-indigo-200 hover:shadow ${draggable ? 'cursor-grab' : ''}">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <div class="truncate text-sm font-semibold text-slate-800">${esc(app.full_name)}</div>
          <div class="mt-0.5 text-xs text-slate-400">${esc(app.reference)}</div>
        </div>
        <button data-open-app="${app.id}" class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50" title="Open details">Open</button>
      </div>
      <div class="mt-2 flex flex-wrap gap-1 text-[11px] text-slate-500">
        <span class="rounded bg-slate-100 px-1.5 py-0.5">${esc(app.skill_category?.name || 'No category')}</span>
        ${app.division ? `<span class="rounded bg-slate-100 px-1.5 py-0.5">${esc(label(app.division))}</span>` : ''}
      </div>
      <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-400">
        <span class="truncate">${esc(locationText(app))}</span>
        <span>${fmtDate(app.created_at)}</span>
      </div>
      ${(can.promote && app.stage !== 'converted_to_cadet' && app.stage !== 'rejected' && app.stage !== 'withdrawn') || (can.communicate && hasContact(app, 'email')) ? `<div class="mt-2 flex gap-1.5">
        ${can.promote && app.stage !== 'converted_to_cadet' && app.stage !== 'rejected' && app.stage !== 'withdrawn' ? `<button data-promote-app="${app.id}" class="flex-1 rounded-md bg-emerald-50 border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Promote</button>` : ''}
        ${can.communicate && hasContact(app, 'email') ? `<button data-email-app="${app.id}" class="flex-1 rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Email</button>` : ''}
      </div>` : ''}
    </div>`;
}

// ---------------- LIST VIEW ----------------

function renderList(container, items, can) {
    if (!items.length) {
        container.innerHTML = `<div class="rounded-xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-400 shadow-sm">No applications match the current filters.</div>`;
        return;
    }
    container.innerHTML = `
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ui-sticky-col overflow-x-auto">
      <table class="min-w-[960px] text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="w-10 px-4 py-3"><input type="checkbox" id="select-all" title="Select all on this page" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></th>
            <th class="px-4 py-3">Applicant</th>
            <th class="px-4 py-3">Category</th>
            <th class="px-4 py-3">Location</th>
            <th class="px-4 py-3">Contact</th>
            <th class="px-4 py-3">Stage</th>
            <th class="px-4 py-3">Applied</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          ${items.map((app) => `
            <tr class="hover:bg-slate-50/60 ${selected.has(app.id) ? 'bg-indigo-50/40' : ''}">
              <td class="px-4 py-3"><input type="checkbox" data-select-id="${app.id}" ${selected.has(app.id) ? 'checked' : ''} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"></td>
              <td class="px-4 py-3 align-top">
                <button data-open-app="${app.id}" class="font-semibold text-slate-800 hover:text-indigo-700 hover:underline">${esc(app.full_name)}</button>
                <div class="mt-0.5 text-xs text-slate-400">${esc(app.reference)}</div>
              </td>
              <td class="px-4 py-3 align-top text-xs text-slate-600">${esc(app.skill_category?.name || '—')}</td>
              <td class="px-4 py-3 align-top text-xs text-slate-600">${esc(locationText(app))}</td>
              <td class="px-4 py-3 align-top text-xs">
                <div class="text-slate-700">${app.contact_number ? `<a href="${phoneHref(app.contact_number)}" class="hover:text-indigo-700">${esc(app.contact_number)}</a>` : '—'}</div>
                <div class="text-slate-400">${esc(app.email || '—')}</div>
              </td>
              <td class="px-4 py-3 align-top">${stageBadge(app.stage)}</td>
              <td class="px-4 py-3 align-top text-xs text-slate-500">${fmtDate(app.created_at)}</td>
              <td class="px-4 py-3 text-right align-top">
                <div class="flex justify-end gap-1.5">
                  ${hasContact(app, 'email') && can.communicate ? `<button data-email-app="${app.id}" class="rounded border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Email</button>` : ''}
                  ${can.promote && app.stage !== 'converted_to_cadet' && app.stage !== 'rejected' && app.stage !== 'withdrawn' ? `<button data-promote-app="${app.id}" class="rounded bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Promote</button>` : ''}
                </div>
              </td>
            </tr>`).join('')}
        </tbody>
      </table>
    </div>`;

    container.querySelector('#select-all').addEventListener('change', (event) => {
        items.forEach((app) => { if (event.target.checked) selected.add(app.id); else selected.delete(app.id); });
        renderSelectionState();
    });
    container.querySelectorAll('[data-select-id]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const id = Number(checkbox.dataset.selectId);
            if (checkbox.checked) selected.add(id); else selected.delete(id);
            renderSelectionState();
        });
    });
container.querySelectorAll('[data-open-app]').forEach((button) => button.addEventListener('click', () => openDrawer(Number(button.dataset.openApp), items, can)));
    container.querySelectorAll('[data-promote-app]').forEach((button) => button.addEventListener('click', () => promoteModal(items.find((item) => Number(item.id) === Number(button.dataset.promoteApp)), () => reload())));
    container.querySelectorAll('[data-email-app]').forEach((button) => {
        const app = items.find((item) => Number(item.id) === Number(button.dataset.emailApp));
        button.addEventListener('click', () => communicationModal(app, 'email', () => reload()));
    });
    container.querySelectorAll('[data-app-id]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', String(card.dataset.appId));
            event.dataTransfer.effectAllowed = 'move';
        });
    });
}

function renderSelectionState() {
    document.querySelectorAll('[data-select-id]').forEach((checkbox) => {
        checkbox.checked = selected.has(Number(checkbox.dataset.selectId));
    });
    const all = document.querySelector('#select-all');
    const boxes = Array.from(document.querySelectorAll('[data-select-id]'));
    if (all && boxes.length) all.checked = boxes.every((box) => box.checked);

    const bar = document.getElementById('bulk-bar');
    if (!bar) return;
    if (selected.size === 0) {
        bar.classList.add('hidden');
        bar.classList.remove('flex');
        bar.innerHTML = '';
        return;
    }
    bar.classList.remove('hidden');
    bar.classList.add('flex');
    bar.innerHTML = `
      <span class="font-semibold text-indigo-700">${selected.size} selected</span>
      <button id="bulk-stage" class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Change stage</button>
      ${store.hasPermission('recruitment.communication.send') ? '<button id="bulk-email" class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Compose email</button>' : ''}
      <button id="bulk-clear" class="rounded-lg px-3 py-1.5 text-xs text-slate-500 hover:bg-slate-100">Clear</button>`;
    bar.querySelector('#bulk-clear').addEventListener('click', () => { selected.clear(); renderSelectionState(); });
    bar.querySelector('#bulk-email')?.addEventListener('click', () => bulkEmail());
    bar.querySelector('#bulk-stage').addEventListener('click', () => bulkStage());
}

// ---------------- STAGE CHANGES ----------------

function stageChangeFlow(app, target, can) {
    if (app.stage === target) return;
    if (target === 'converted_to_cadet') {
        toast('Promotion is handled by the dedicated promote-to-cadet flow.', 'info');
        return;
    }
    if (target === 'rejected') {
        rejectionModal(app, can);
        return;
    }
    if (TERMINAL.includes(app.stage)) {
        confirmModal(`Reopen ${esc(app.full_name)}?`, `<p class="text-sm text-slate-500">This application is in the terminal stage <strong>${esc(label(app.stage))}</strong>. Moving it to <strong>${esc(label(target))}</strong> reopens the pipeline and clears the rejection details.</p><div class="mt-5 flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button id="confirm-reopen" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Reopen and move</button></div>`, () => {
            patchStage(app, target, can, { reopen: true });
        });
        return;
    }
    if (target === 'interview_scheduled') {
        interviewModal(app, can);
        return;
    }
    patchStage(app, target, can, {});
}

function patchStage(app, target, can, extra) {
    performStageChange(app, target, extra)
        .then(() => {
            toast('Application stage updated.', 'success');
            reload();
            if (document.getElementById('application-drawer')) loadDrawerContent(app, can);
        })
        .catch((error) => toast(error.message || 'Could not update stage.', 'error'));
}

const marker = '<span class="text-rose-500">*</span>';
const inputCls = 'rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100';

function rejectionModal(app, can, onDone = () => reload()) {
    modal('Reject application', `<p class="text-sm text-slate-500">Rejecting <strong>${esc(app.full_name)}</strong> requires a reason recorded for the applicant.</p><form id="reject-form" class="mt-5 space-y-4"><label class="block text-sm font-medium text-slate-700">Rejection reason ${marker}<select id="reason-preset" class="mt-1 w-full ${inputCls}"><option value="">Type or choose below…</option>${REJECTION_REASONS.map((reason) => `<option value="${esc(reason)}">${esc(reason)}</option>`).join('')}</select></label><label class="block text-sm font-medium text-slate-700">Custom reason<textarea id="reason-custom" rows="3" class="mt-1 w-full ${inputCls}"></textarea></label><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Reject application</button></div></form>`, () => {
        document.querySelector('#reject-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const preset = document.querySelector('#reason-preset').value.trim();
            const custom = document.querySelector('#reason-custom').value.trim();
            const reason = custom || preset;
            if (!reason) { toast('Please provide a rejection reason.', 'error'); return; }
            performStageChange(app, 'rejected', { rejection_reason: reason })
                .then(() => { closeModal(); toast('Application rejected.', 'success'); onDone(); })
                .catch((error) => { closeModal(); toast(error.message || 'Could not reject application.', 'error'); });
        });
    });
}

function interviewModal(app, can) {
    modal('Schedule interview', `<p class="text-sm text-slate-500">Move <strong>${esc(app.full_name)}</strong> to Interview scheduled. Interview details are optional.</p><form id="interview-form" class="mt-5 space-y-4"><label class="block text-sm font-medium text-slate-700">Interview date &amp; time<input id="interview-at" type="datetime-local" class="mt-1 w-full ${inputCls}"></label><label class="block text-sm font-medium text-slate-700">Location<input id="interview-location" class="mt-1 w-full ${inputCls}" placeholder="e.g. Kathmandu, NCCAA office"></label><label class="block text-sm font-medium text-slate-700">Video link<input id="interview-link" type="url" class="mt-1 w-full ${inputCls}" placeholder="https://meet.google.com/…"></label><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Schedule</button></div></form>`, () => {
        document.querySelector('#interview-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const interview_scheduled_at = document.querySelector('#interview-at').value || null;
            const interview_location = document.querySelector('#interview-location').value.trim() || null;
            const interview_link = document.querySelector('#interview-link').value.trim() || null;
            performStageChange(app, 'interview_scheduled', { interview_scheduled_at, interview_location, interview_link })
                .then(() => { closeModal(); toast('Application moved to Interview scheduled.', 'success'); reload(); })
                .catch((error) => { closeModal(); toast(error.message || 'Could not schedule interview.', 'error'); });
        });
    });
}

function confirmModal(title, body, onConfirm) {
    modal(title, body, () => {
        document.querySelector('#confirm-reopen')?.addEventListener('click', () => { closeModal(); onConfirm(); });
    });
}

async function performStageChange(app, target, extra) {
    const body = { stage: target, ...extra };
    const result = await api(`/applications/${app.id}/stage`, { method: 'PATCH', body });
    Object.assign(app, result);
    return result;
}

// ---------------- BULK ACTIONS ----------------

function selectedApps() {
    return currentItems.filter((app) => selected.has(app.id));
}

function bulkStage() {
    const apps = selectedApps();
    if (!apps.length) return;
    modal('Bulk change stage', `<p class="text-sm text-slate-500">Apply a stage change to <strong>${apps.length}</strong> selected applications.</p><form id="bulk-stage-form" class="mt-5 space-y-4"><label class="block text-sm font-medium text-slate-700">Move to ${marker}<select id="bulk-target" class="mt-1 w-full ${inputCls}"><option value="">Select stage…</option>${PIPELINE.filter((stage) => stage !== 'converted_to_cadet').map((stage) => `<option value="${stage}">${label(stage)}</option>`).join('')}</select></label><div id="bulk-extra"></div><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Apply to ${apps.length}</button></div></form>`, () => {
        const targetSelect = document.querySelector('#bulk-target');
        const extraBox = document.querySelector('#bulk-extra');
        targetSelect.addEventListener('change', () => {
            const target = targetSelect.value;
            if (target === 'rejected') {
                extraBox.innerHTML = `<label class="block text-sm font-medium text-slate-700">Rejection reason ${marker}<textarea id="bulk-reason" rows="2" class="mt-1 w-full ${inputCls}"></textarea></label>`;
            } else if (target === 'interview_scheduled') {
                extraBox.innerHTML = `<label class="block text-sm font-medium text-slate-700">Video link (optional)<input id="bulk-interview-link" type="url" class="mt-1 w-full ${inputCls}" placeholder="https://meet.google.com/…"></label>`;
            } else {
                extraBox.innerHTML = '';
            }
        });
        document.querySelector('#bulk-stage-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const target = targetSelect.value;
            if (!target) { toast('Please select a target stage.', 'error'); return; }
            const extra = { rejection_reason: null, reopen: false, interview_scheduled_at: null, interview_location: null, interview_link: null };
            if (target === 'rejected') {
                extra.rejection_reason = document.querySelector('#bulk-reason').value.trim();
                if (!extra.rejection_reason) { toast('Please provide a rejection reason.', 'error'); return; }
            }
            if (target === 'interview_scheduled') {
                const linkInput = document.querySelector('#bulk-interview-link');
                if (linkInput && linkInput.value.trim()) extra.interview_link = linkInput.value.trim();
            }
            const needsReopen = apps.some((app) => TERMINAL.includes(app.stage));
            extra.reopen = needsReopen;
            if (needsReopen && !window.confirm('Some selected applications are in a terminal stage and will be reopened. Continue?')) return;

            const submit = document.querySelector('#bulk-stage-form button[type="submit"]');
            submit.disabled = true;
            submit.textContent = 'Applying…';
            Promise.all(apps.map((app) => performStageChange(app, target, extra)))
                .then(() => { closeModal(); toast(`Updated ${apps.length} applications.`, 'success'); selected.clear(); reload(); })
                .catch((error) => { submit.disabled = false; submit.textContent = `Apply to ${apps.length}`; toast(error.message || 'Bulk update failed.', 'error'); });
        });
    });
}

function bulkEmail() {
    const apps = selectedApps().filter((app) => hasContact(app, 'email'));
    if (!apps.length) { modal('Compose email', '<p class="text-sm text-slate-500">None of the selected applications have an email address on file.</p><div class="mt-5 flex justify-end"><button data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button></div>'); return; }
    modal(`Compose email`, `<p class="text-sm text-slate-500">Send an email to <strong>${apps.length}</strong> selected applicant${apps.length > 1 ? 's' : ''} through the configured SMTP mailer.</p><form id="bulk-email-form" class="mt-5 space-y-4"><label class="block text-sm font-medium text-slate-700">Subject<input id="bulk-email-subject" value="${esc(DEFAULT_SUBJECT)}" class="mt-1 w-full ${inputCls}"></label><label class="block text-sm font-medium text-slate-700">Message<textarea id="bulk-email-message" rows="6" class="mt-1 w-full ${inputCls}" placeholder="Dear applicant,&#10;&#10;Thank you for applying to the NCCAA recruitment program…"></textarea></label><div class="flex justify-end gap-2"><button type="button" data-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Send to ${apps.length}</button></div></form>`, () => {
        document.querySelector('#bulk-email-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const subject = document.querySelector('#bulk-email-subject').value.trim();
            const message = document.querySelector('#bulk-email-message').value.trim();
            if (!subject || !message) { toast('Subject and message are required.', 'error'); return; }
            const submit = document.querySelector('#bulk-email-form button[type="submit"]');
            submit.disabled = true;
            submit.textContent = 'Sending…';
            let sent = 0;
            let failed = 0;
            const sendNext = async (index) => {
                if (index >= apps.length) {
                    closeModal();
                    toast(`Email sent to ${sent} applicant${sent !== 1 ? 's' : ''}${failed ? ` · ${failed} failed` : ''}.`, failed ? 'info' : 'success');
                    return;
                }
                try {
                    await api(`/recruitment-candidates/${apps[index].id}/communication`, { method: 'POST', body: { channel: 'email', subject, message } });
                    sent += 1;
                } catch {
                    failed += 1;
                }
                sendNext(index + 1);
            };
            sendNext(0);
        });
    });
}

async function exportCsv() {
    try {
        const rows = [];
        let page = 1;
        for (;;) {
            const data = await api('/applications', { params: { ...listParams(), per_page: 100, view: undefined, page } });
            rows.push(...(data.items || []));
            if (page >= (data.meta?.last_page ?? 1)) break;
            page += 1;
        }
        const headers = ['Reference', 'Full name', 'Stage', 'Category', 'Division', 'Province', 'District', 'Contact', 'Email', 'Applied', 'Rejection reason'];
        const lines = [headers.join(',')];
        rows.forEach((app) => {
            const values = [
                app.reference,
                csvCell(app.full_name),
                app.stage,
                csvCell(app.skill_category?.name || ''),
                app.division || '',
                csvCell(app.province?.name_en || ''),
                csvCell(app.district?.name_en || ''),
                csvCell(app.contact_number || ''),
                csvCell(app.email || ''),
                app.created_at || '',
                csvCell(app.rejection_reason || ''),
            ];
            lines.push(values.join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `applications-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        toast(`Exported ${rows.length} applications.`, 'success');
    } catch (error) {
        toast(error.message || 'Export failed.', 'error');
    }
}

function csvCell(value) {
    return `"${String(value).replaceAll('"', '""')}"`;
}

// ---------------- DETAIL DRAWER ----------------

function openDrawer(id, items, can) {
    const index = items.findIndex((item) => Number(item.id) === id);
    openDrawerAt(index, items, can);
}

function openDrawerAt(index, items, can) {
    drawerIndex = index < 0 ? 0 : index;
    drawerList = items;
    const app = drawerList[drawerIndex];
    document.body.insertAdjacentHTML('beforeend', `
      <div id="application-drawer" class="fixed inset-0 z-50">
        <div id="drawer-backdrop" class="absolute inset-0 bg-slate-900/40"></div>
        <aside class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-2xl">
          <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
              <div class="text-xs font-medium text-indigo-600">${esc(app.reference)}</div>
              <h3 class="truncate text-lg font-bold text-slate-900">${esc(app.full_name)}</h3>
              <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-slate-500"><span id="drawer-header-badge">${stageBadge(app.stage)}</span><span>${esc(locationText(app))}</span></div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button id="drawer-prev" title="Previous application" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50" ${drawerIndex <= 0 ? 'disabled' : ''}>‹</button>
              <button id="drawer-next" title="Next application" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50" ${drawerIndex >= drawerList.length - 1 ? 'disabled' : ''}>›</button>
              <button id="drawer-close" aria-label="Close drawer" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-500 hover:bg-slate-50">✕</button>
            </div>
          </div>
          <div id="drawer-content" class="flex-1 overflow-y-auto px-5 py-4">${spinner()}</div>
        </aside>
      </div>`);

    const drawer = document.getElementById('application-drawer');
    drawer.querySelector('#drawer-close').addEventListener('click', () => drawer.remove());
    drawer.querySelector('#drawer-backdrop').addEventListener('click', () => drawer.remove());
    drawer.querySelector('#drawer-prev').addEventListener('click', () => openDrawerAt(drawerIndex - 1, drawerList, can));
    drawer.querySelector('#drawer-next').addEventListener('click', () => openDrawerAt(drawerIndex + 1, drawerList, can));

    loadDrawerContent(drawerList[drawerIndex], can);
}

async function loadDrawerContent(app, can) {
    const contentEl = document.getElementById('drawer-content');
    try {
        const detail = await api(`/applications/${app.id}`);
        Object.assign(app, detail);
        const headerBadge = document.getElementById('drawer-header-badge');
        if (headerBadge) headerBadge.innerHTML = stageBadge(app.stage);
        contentEl.innerHTML = drawerBody(app, can);
        bindDrawerActions(contentEl, app, can);
    } catch (error) {
        contentEl.innerHTML = `<div class="p-6 text-sm text-rose-600">${esc(error.message || 'Could not load application.')}</div>`;
    }
}

function drawerBody(app, can) {
    const notes = (app.notes || []).map((note) => `
      <div class="border-b border-slate-100 py-3 last:border-0">
        <div class="flex items-center justify-between gap-2 text-xs">
          <span class="font-semibold text-slate-700">${esc(note.user?.name || 'Unknown')}</span>
          <span class="text-slate-400">${fmtDateTime(note.created_at)}</span>
        </div>
        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600">${esc(note.note)}</p>
      </div>`).join('') || '<p class="py-4 text-center text-sm text-slate-400">No internal notes yet.</p>';

    const activities = (app.activities || []).map((activity) => `
      <li class="border-l-2 border-slate-100 pl-4 pb-4">
        <div class="text-xs font-semibold text-slate-700">${esc(label(activity.type))}</div>
        <p class="mt-0.5 text-sm text-slate-600">${esc(activity.description)}</p>
        <div class="mt-1 text-xs text-slate-400">${esc(activity.performed_by?.name || 'System')} · ${fmtDateTime(activity.performed_at)}</div>
      </li>`).join('');

    const duplicates = (app.duplicates || []).map((dup) => `
      <div class="flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
        <div class="min-w-0">
          <div class="truncate text-sm font-semibold text-slate-800">${esc(dup.full_name)}</div>
          <div class="mt-0.5 text-xs text-slate-500">${esc(dup.record_type)}${dup.cadet_number ? ` · ${esc(dup.cadet_number)}` : ''}${dup.email ? ` · ${esc(dup.email)}` : ''}</div>
        </div>
        <div class="text-right">
          <span class="rounded bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">${esc(dup.reason)}</span>
          ${dup.recruitment_status ? `<div class="mt-1 text-[11px] text-slate-500">${esc(label(dup.recruitment_status))}</div>` : ''}
        </div>
      </div>`).join('') || '<p class="py-4 text-center text-sm text-slate-400">No duplicate records detected.</p>';

    const stageOptions = PIPELINE.filter((stage) => stage !== 'converted_to_cadet' && stage !== app.stage)
        .map((stage) => `<option value="${stage}">${label(stage)}</option>`).join('');

    const overviewRows = [
        ['Gender', esc(app.gender || '—')],
        ['Cadet number', esc(app.cadet_number || '—')],
        ['Citizenship number', esc(app.citizenship_number || '—')],
        ['Division', esc(app.division ? label(app.division) : '—')],
        ['Batch / Year', esc([app.ncc_batch, app.ncc_year].filter(Boolean).join(' · ') || '—')],
        ['School', esc(app.school || '—')],
        ['Address', esc(app.address || '—')],
        ['Category', esc(app.skill_category?.name || '—')],
        ['Training centre', esc(app.training_center?.name_en || '—')],
        ['Source', esc(label(app.source || 'portal'))],
        ['Applied', fmtDateTime(app.created_at)],
        ['Stage last updated', `${fmtDateTime(app.stage_updated_at)}${app.stage_updated_by ? ` by ${esc(app.stage_updated_by.name)}` : ''}`],
    ].map(([key, value]) => `<div class="flex justify-between gap-6 py-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">${key}</dt><dd class="text-right text-sm text-slate-700">${value}</dd></div>`).join('');

    const interviewBlock = app.stage === 'interview_scheduled' && (app.interview_scheduled_at || app.interview_location || app.interview_link) ? `
      <div class="mt-3 rounded-lg border border-violet-100 bg-violet-50/60 p-3 text-xs text-violet-800">
        <div class="font-semibold">Interview details</div>
        <div class="mt-1">${app.interview_scheduled_at ? `Scheduled: ${fmtDateTime(app.interview_scheduled_at)}` : 'No time set'}</div>
        ${app.interview_location ? `<div>Location: ${esc(app.interview_location)}</div>` : ''}
        ${app.interview_link ? `<div>Link: <a href="${esc(app.interview_link)}" target="_blank" rel="noreferrer" class="underline">Open</a></div>` : ''}
      </div>` : '';

    const rejectionBlock = app.rejection_reason ? `<div class="mt-3 rounded-lg border border-rose-100 bg-rose-50/60 p-3 text-xs text-rose-700">Rejected: ${esc(app.rejection_reason)}</div>` : '';

    const documents = [
        app.photo_url ? ['Photo', app.photo_url] : null,
        app.cv_url ? ['CV', app.cv_url] : null,
        app.proof_url ? [`Proof ${app.proof_is_link ? '(link)' : '(file)'}`, app.proof_url] : null,
    ].filter(Boolean).map(([name, url]) => `<a href="${esc(url)}" target="_blank" rel="noreferrer" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50"><span>${name}</span><span>↗</span></a>`).join('') || '<p class="py-4 text-center text-sm text-slate-400">No documents uploaded.</p>';

    const urgentMc = app.skill_specific_data && Object.keys(app.skill_specific_data).length ? Object.entries(app.skill_specific_data).slice(0, 8).map(([key, value]) => `<div class="flex justify-between gap-4 py-1.5 text-sm"><dt class="text-slate-500">${esc(label(key))}</dt><dd class="text-right text-slate-700">${esc(Array.isArray(value) ? value.join(', ') : value)}</dd></div>`).join('') : '';

    return `
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
      <div class="flex flex-wrap items-center gap-2">
        ${can.manage ? `<select id="drawer-stage" class="flex-1 min-w-[180px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400"><option value="">Change stage…</option>${stageOptions}</select>` : ''}
        ${can.promote && app.stage !== 'converted_to_cadet' ? '<button id="drawer-promote" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Promote to cadet</button>' : ''}
        ${hasContact(app, 'email') && can.communicate ? '<button id="drawer-email" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Email</button>' : ''}
        ${app.contact_number ? `<div class="flex gap-1"><a href="${phoneHref(app.contact_number)}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 hover:bg-slate-50" title="Call">☎</a><a href="${whatsappHref(app.contact_number)}" target="_blank" rel="noreferrer" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 hover:bg-slate-50" title="WhatsApp">◉</a></div>` : ''}
      </div>
    </div>

    <div class="mb-4 flex gap-1 border-b border-slate-100 text-sm">
      ${['Overview', 'Documents', 'Activity', 'Notes', 'Duplicates'].map((tab, index) => `<button data-drawer-tab="${index}" class="px-3 pb-2.5 font-medium transition ${index === 0 ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-400 hover:text-slate-600'}">${tab}</button>`).join('')}
    </div>

    <div data-drawer-panel="0" class="drawer-panel">
      ${overviewRows ? `<dl class="divide-y divide-slate-100">${overviewRows}</dl>` : ''}
      ${urgentMc ? `<div class="mt-4 rounded-lg border border-slate-200 p-3"><div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Application details</div><dl>${urgentMc}</dl></div>` : ''}
      ${interviewBlock}
      ${rejectionBlock}
      ${app.converted_user_id ? `<div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50/60 p-3 text-xs text-emerald-700">Promoted to active cadet${app.converted_at ? ` on ${fmtDateTime(app.converted_at)}` : ''}.</div>` : ''}
    </div>
    <div data-drawer-panel="1" class="drawer-panel hidden">${documents}</div>
    <div data-drawer-panel="2" class="drawer-panel hidden"><ul>${activities || '<p class="py-4 text-center text-sm text-slate-400">No activity yet.</p>'}</ul></div>
    <div data-drawer-panel="3" class="drawer-panel hidden"><div class="mb-3 space-y-2">${notes}</div>${can.note ? `<form id="drawer-note-form" class="flex gap-2"><input id="drawer-note-input" placeholder="Add an internal note…" class="flex-1 ${inputCls}"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add</button></form>` : ''}</div>
    <div data-drawer-panel="4" class="drawer-panel hidden">${duplicates}</div>`;
}

function bindDrawerActions(container, app, can) {
    container.querySelectorAll('[data-drawer-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const index = Number(button.dataset.drawerTab);
            container.querySelectorAll('[data-drawer-tab]').forEach((tab, tabIndex) => {
                tab.className = `px-3 pb-2.5 font-medium transition ${tabIndex === index ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-400 hover:text-slate-600'}`;
            });
            container.querySelectorAll('.drawer-panel').forEach((panel) => panel.classList.add('hidden'));
            container.querySelector(`[data-drawer-panel="${index}"]`).classList.remove('hidden');
        });
    });

    container.querySelector('#drawer-stage')?.addEventListener('change', (event) => {
        const target = event.target.value;
        event.target.value = '';
        if (target) stageChangeFlow(app, target, can);
    });
    container.querySelector('#drawer-promote')?.addEventListener('click', () => promoteModal(app, () => reload()));
    container.querySelector('#drawer-email')?.addEventListener('click', () => communicationModal(app, 'email', () => reload()));
    container.querySelector('#drawer-note-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = container.querySelector('#drawer-note-input');
        const note = input.value.trim();
        if (!note) return;
        try {
            await api(`/applications/${app.id}/notes`, { method: 'POST', body: { note } });
            input.value = '';
            toast('Note added.', 'success');
            loadDrawerContent(app, can);
        } catch (error) {
            toast(error.message || 'Could not add note.', 'error');
        }
    });
}

// ---------------- MODAL HELPERS ----------------

function modal(title, body, bind) {
    document.body.insertAdjacentHTML('beforeend', `<div id="applications-modal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4"><div class="max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl"><div class="flex items-start justify-between gap-4"><h3 class="text-lg font-bold text-slate-900">${title}</h3><button data-close aria-label="Close dialog" class="text-slate-400">✕</button></div>${body}</div></div>`);
    document.querySelectorAll('#applications-modal [data-close]').forEach((button) => button.addEventListener('click', closeModal));
    bind?.();
}

function closeModal() {
    document.getElementById('applications-modal')?.remove();
}

async function reload() {
    const [stats, data] = await Promise.all([
        api('/applications/stats'),
        api('/applications', { params: listParams() }),
    ]);
    currentItems = data.items || [];
    const statsEl = document.getElementById('apps-stats');
    const viewEl = document.getElementById('apps-view');
    const pageEl = document.getElementById('apps-pagination');
    if (statsEl) renderStats(statsEl, stats);
    if (viewEl) {
        if (state.view === 'board') renderBoard(viewEl, currentItems, { manage: store.hasPermission('applications.manage'), promote: store.hasPermission('recruitment.promote-to-cadet'), communicate: store.hasPermission('recruitment.communication.send') });
        else renderList(viewEl, currentItems, { manage: store.hasPermission('applications.manage'), promote: store.hasPermission('recruitment.promote-to-cadet'), communicate: store.hasPermission('recruitment.communication.send') });
    }
    if (pageEl) renderPagination(pageEl, data.meta);
}