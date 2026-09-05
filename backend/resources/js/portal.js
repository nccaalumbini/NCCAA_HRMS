const app = document.getElementById('portal-app');

const state = {
    categories: [],
    meta: null,
    category: null,
    fields: [],
    values: {},
    files: {},
    errors: {},
    step: 1,
    geos: { provinces: [], districts: [] },
    submitting: false,
};

const STEP_LABELS = ['Personal & NCC', 'Address & Documents', 'Skill Details', 'Review & Submit'];

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function valHtml(value) {
    return String(value ?? '');
}

async function api(path, options = {}) {
    const headers = options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' };
    const response = await fetch(path, { ...options, headers });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        if (response.status === 422) {
            const error = new Error(payload.message || 'Please fix the highlighted fields.');
            error.status = 422;
            error.errors = payload.errors || {};
            throw error;
        }
        if (response.status === 429) {
            throw new Error('Too many requests. Please try again in a few minutes.');
        }
        throw new Error(payload.message || 'Something went wrong. Please try again.');
    }
    return payload.data;
}

const ICONS = {
    palette: '<path d="M12 3a9 9 0 1 0 0 18h.75a1.75 1.75 0 0 0 1.7-2.2c-.2-.7.2-1.3.9-1.3h1.9a4.75 4.75 0 0 0 4.75-4.75C22 8.6 17.5 3 12 3Z"/><circle cx="7.5" cy="12" r="1"/><circle cx="11" cy="7.5" r="1"/><circle cx="15.5" cy="9.5" r="1"/>',
    layout: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
    code: '<path d="m8 9-3 3 3 3M16 9l3 3-3 3M13 5l-2 14"/>',
    server: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 17.5h.01"/>',
    camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"/><circle cx="12" cy="13" r="3"/>',
    pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>',
    video: '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
    scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.1 15.9M14.5 10.5 20 20M8.1 8.1 12 12"/>',
    briefcase: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
};
const DEFAULT_ICON = 'briefcase';

function iconMarkup(name) {
    return `<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ICONS[DEFAULT_ICON]}</svg>`;
}

/* ------------------------------------------------------------------ */
/* Landing                                                             */
/* ------------------------------------------------------------------ */

async function renderLanding() {
    state.categories = [];
    state.meta = null;

    const data = await api('/api/v1/job-categories');
    state.categories = data.items || [];
    state.meta = data.meta || {};

    app.innerHTML = `
      <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white">N</div>
            <div>
              <div class="text-sm font-bold tracking-tight text-slate-900">NCC Job Portal</div>
              <div class="text-xs text-slate-500">National Cadet Corps Alumni Association of Nepal</div>
            </div>
          </div>
          <a href="/" class="text-xs font-medium text-slate-500 hover:text-slate-800">Admin Console →</a>
        </div>
      </header>

      <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <section class="max-w-2xl">
          <p class="text-sm font-semibold text-indigo-600">Careers at NCC</p>
          <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Apply for a role with your skills</h1>
          <p class="mt-3 text-base text-slate-600">Browse the open categories below. Each role has its own short application tailored to what you do — pick one to get started.</p>
        </section>

        <section aria-label="Open job categories" class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          ${state.categories.map((category) => `
            <a href="/apply/${esc(category.slug)}" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white">${iconMarkup(category.icon)}</span>
              <h2 class="mt-4 text-lg font-semibold text-slate-900">${esc(category.name)}</h2>
              <p class="mt-1 flex-1 text-sm text-slate-500">${esc(category.description)}</p>
              <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">Apply now <span aria-hidden="true">→</span></span>
            </a>
          `).join('')}
          ${state.categories.length === 0 ? '<p class="text-sm text-slate-500">No positions are currently open.</p>' : ''}
        </section>
      </main>

      <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-center text-xs text-slate-400 sm:px-6">NCCAA · National Cadet Corps Alumni Association of Nepal</div>
      </footer>`;
}

/* ------------------------------------------------------------------ */
/* Wizard                                                              */
/* ------------------------------------------------------------------ */

async function initWizard() {
    const data = await api('/api/v1/job-categories');
    state.categories = data.items || [];
    state.meta = data.meta || {};

    const slug = document.body.dataset.skill;
    if (!slug || !state.categories.some((category) => category.slug === slug)) {
        window.location.replace('/apply');
        return;
    }

    const categoryData = await api(`/api/v1/job-categories/${slug}`);
    state.category = { slug, name: categoryData.name, icon: categoryData.icon, description: categoryData.description };
    state.fields = categoryData.fields || [];

    await loadProvinces();
    renderWizard();
}

async function loadProvinces() {
    if (state.geos.provinces.length) return;
    const data = await api('/api/v1/provinces');
    state.geos.provinces = data.items || [];
}

async function loadDistricts(provinceId) {
    const data = await api(`/api/v1/districts/${provinceId}`);
    state.geos.districts = data.items || [];
}

function renderWizard() {
    app.innerHTML = `
      <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
          <button id="portal-back" type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            All positions
          </button>
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">${iconMarkup(state.category.icon)}<span class="hidden sm:inline">${esc(state.category.name)}</span></div>
        </div>
      </header>

      <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <div id="wizard-stepper" class="mb-8"></div>
        <div id="wizard-panel"></div>
      </main>

      <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-6 text-center text-xs text-slate-400 sm:px-6">Fields marked <span class="text-rose-500">*</span> are required unless noted.</div>
      </footer>`;

    document.getElementById('portal-back').addEventListener('click', () => window.location.replace('/apply'));
    renderStepper();
    renderStep();
}

function renderStepper() {
    const el = document.getElementById('wizard-stepper');
    el.innerHTML = `
      <ol class="flex items-center gap-1 sm:gap-2">
        ${STEP_LABELS.map((label, index) => {
            const step = index + 1;
            const done = step < state.step;
            const active = step === state.step;
            const number = done ? '<span class="text-emerald-600">✓</span>' : step;
            return `
              <li class="flex min-w-0 flex-1 items-center gap-1 sm:gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${active ? 'bg-indigo-600 text-white' : done ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'}">${active ? step : number}</span>
                <span class="truncate text-[11px] font-medium ${active ? 'text-slate-900' : 'text-slate-400'} sm:text-xs">${label}</span>
                ${index < STEP_LABELS.length - 1 ? '<span class="ml-1 h-px min-w-2 flex-1 bg-slate-200 sm:ml-2"></span>' : ''}
              </li>`;
        }).join('')}
      </ol>`;
}

function stepLabel(step) {
    return STEP_LABELS[step - 1] ?? '';
}

function renderStep() {
    const panel = document.getElementById('wizard-panel');
    panel.innerHTML = STEP_MARKUP[state.step]();
    panel.addEventListener('input', onInput);
    panel.addEventListener('change', onChange);

    if (state.step === 4) {
        renderReview();
    }
    renderNav(state.step);

    if (state.errors.global) {
        panel.insertAdjacentHTML('afterbegin', `<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${esc(state.errors.global)}</div>`);
    }
}

/* ------------------------------------------------------------------ */
/* Field helpers                                                       */
/* ------------------------------------------------------------------ */

function fieldWrap(fieldKey, label, control, hint, errorKey) {
    const error = state.errors[errorKey ?? fieldKey];
    return `
      <div>
        <label class="block text-sm font-medium text-slate-700">${label}</label>
        <div class="mt-1">${control}</div>
        ${hint ? `<p class="mt-1 text-xs text-slate-400">${hint}</p>` : ''}
        ${error ? `<p class="mt-1 text-xs text-rose-600">${error}</p>` : ''}
      </div>`;
}

function baseInput(fieldKey, type, value, extra = '') {
    return `<input id="f-${fieldKey}" name="${fieldKey}" type="${type}" value="${esc(value)}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 ${state.errors[fieldKey] ? 'border-rose-400' : ''}" ${extra}>`;
}

const STEP_MARKUP = {
    /* 1 - Personal & NCC details */
    1: () => `
          <h2 class="text-xl font-bold text-slate-900">Tell us about yourself</h2>
          <p class="mt-1 text-sm text-slate-500">Your NCC background and the best way to reach you.</p>

          <div class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            ${fieldWrap('full_name', 'Full Name *', baseInput('full_name', 'text', state.values.full_name, 'required autocomplete="name"'))}
            ${fieldWrap('cadet_number', 'Cadet Number *', baseInput('cadet_number', 'text', state.values.cadet_number, 'required autocomplete="off"'), 'We check this is unique as you type.')}
            ${fieldWrap('phone', 'Phone Number *', baseInput('phone', 'tel', state.values.phone, 'required autocomplete="tel" inputmode="tel"'), 'Enter digits with or without +977.')}
            ${fieldWrap('email', 'Email Address *', baseInput('email', 'email', state.values.email, 'required autocomplete="email"'))}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              ${divisionField()}
              ${trainingBatchField()}
              ${nccYearField()}
              ${trainingCenterField()}
            </div>
            ${fieldWrap('school', 'School', baseInput('school', 'text', state.values.school, ''))}
          </div>

          <div id="wizard-nav" class="mt-6 flex items-center justify-end gap-3"></div>`,

    /* 2 - Address & Documents */
    2: () => {
        const provinceOptions = state.geos.provinces.map((province) => `<option value="${province.id}" ${Number(state.values.province_id) === province.id ? 'selected' : ''}>${esc(province.name_en)}</option>`).join('');
        const districtOptions = state.geos.districts.map((district) => `<option value="${district.id}" ${Number(state.values.district_id) === district.id ? 'selected' : ''}>${esc(district.name_en)}</option>`).join('');

        return `
          <h2 class="text-xl font-bold text-slate-900">Address & documents</h2>
          <p class="mt-1 text-sm text-slate-500">Your location, citizenship, and the files we need to review you.</p>

          <div class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              ${fieldWrap('province_id', 'Province *', provinceSelect(provinceOptions), undefined, 'province_id')}
              ${fieldWrap('district_id', 'District *', districtSelect(districtOptions), undefined, 'district_id')}
            </div>
            ${addressField()}
            ${citizenshipField()}
          </div>

          <div class="mt-5 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            ${photoField()}
            ${uniformConfirmField()}
          </div>

          <div class="mt-5 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            ${proofField()}
            ${cvField()}
          </div>

          <div id="wizard-nav" class="mt-6 flex items-center justify-between gap-3"></div>`;
    },

    /* 3 - Skill-specific */
    3: () => state.fields.length
        ? `
          <h2 class="text-xl font-bold text-slate-900">Your ${esc(state.category.name)} experience</h2>
          <p class="mt-1 text-sm text-slate-500">A few role-specific questions so we can match you well.</p>

          <div class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
            ${state.fields.map((field) => skillFieldMarkup(field)).join('')}
          </div>

          <div id="wizard-nav" class="mt-6 flex items-center justify-between gap-3"></div>`
        : `
          <h2 class="text-xl font-bold text-slate-900">Review & submit</h2>
          <div id="wizard-nav" class="mt-6 flex items-center justify-between gap-3"></div>`,

    /* 4 - Review & submit */
    4: () => `
          <h2 class="text-xl font-bold text-slate-900">Review your application</h2>
          <p class="mt-1 text-sm text-slate-500">Check everything looks right before you submit.</p>
          <div id="review-summary" class="mt-6 space-y-6"></div>
          <div id="wizard-nav" class="mt-6 flex items-center justify-between gap-3"></div>`,
};

function provinceSelect(provinceOptions) {
    return `<select id="f-province_id" name="province_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.province_id ? 'border-rose-400' : ''}"><option value="">— Select —</option>${provinceOptions}</select>`;
}

function districtSelect(districtOptions) {
    return `<select id="f-district_id" name="district_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.district_id ? 'border-rose-400' : ''}"><option value="">— Select —</option>${districtOptions}</select>`;
}

function addressField() {
    return fieldWrap('address', 'Address *', `<textarea id="f-address" name="address" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.address ? 'border-rose-400' : ''}" placeholder="Full address — locality, ward, municipality">${valHtml(state.values.address)}</textarea>`, undefined, 'address');
}

function citizenshipField() {
    return fieldWrap('citizenship_number', 'Citizenship Number *', baseInput('citizenship_number', 'text', state.values.citizenship_number, 'required autocomplete="off"'), '6–30 letters, digits, or hyphens.', 'citizenship_number');
}

function photoField() {
    const photo = state.files.photo;
    const preview = photo && photo.type.startsWith('image/')
        ? `<div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white"><img id="photo-preview" src="${URL.createObjectURL(photo)}" alt="Photo preview" class="h-full w-full object-cover"></div>`
        : `<span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="13" r="3"/></svg></span>`;

    return fieldWrap('photo', 'Passport-size Photo (in NCC uniform) *', `
      <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 ${photo ? 'border-emerald-300 bg-emerald-50/40' : ''}">
        <input id="f-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
        ${preview}
        <span class="min-w-0 text-sm">${photo ? esc(photo.name) : 'Upload a recent photo in NCC dress (JPG/PNG/WebP, max 2MB)'}</span>
      </label>`, 'Upload a recent passport-size photo. This is reviewed manually — it must show you in NCC uniform.', 'photo');
}

function uniformConfirmField() {
    return fieldWrap('uniform_photo_confirmed', 'NCC dress confirmation', `
      <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <input id="f-uniform_photo_confirmed" name="uniform_photo_confirmed" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 accent-indigo-600" ${state.values.uniform_photo_confirmed ? 'checked' : ''}>
        <span class="text-sm text-slate-600">I confirm that the uploaded photo shows me in NCC uniform, as required.</span>
      </label>`, undefined, 'uniform_photo_confirmed');
}

function proofField() {
    const proofFile = state.files.proof_of_work_file;
    const proofUrl = state.values.proof_of_work_url;
    const proofMode = proofFile ? 'file' : state.values.proof_mode || 'url';
    const proofControl = proofMode === 'file'
        ? `<label class="flex cursor-pointer items-center gap-2 text-sm text-emerald-700"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg><input id="f-proof-file" name="proof_of_work_file" type="file" accept="application/pdf,image/jpeg,image/png" class="sr-only">${proofFile ? esc(proofFile.name) : 'Choose a file…'}</label>`
        : `<input id="f-proof-url" name="proof_of_work_url" type="url" value="${esc(proofUrl)}" placeholder="https://... portfolio, GitHub, Behance" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">`;

    return fieldWrap('proof_of_work', 'Proof of Work / Portfolio *', `
      <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row">
          <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border ${proofMode === 'file' ? 'border-indigo-300 bg-indigo-50/50' : 'border-slate-300 bg-white'} p-3 text-sm">
            <input id="f-proof-file-opt" name="proof_mode" type="radio" value="file" class="accent-indigo-600" ${proofMode === 'file' ? 'checked' : ''}>
            <span>Upload a file</span>
          </label>
          <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border ${proofMode === 'url' ? 'border-indigo-300 bg-indigo-50/50' : 'border-slate-300 bg-white'} p-3 text-sm">
            <input id="f-proof-url-opt" name="proof_mode" type="radio" value="url" class="accent-indigo-600" ${proofMode === 'url' ? 'checked' : ''}>
            <span>Paste a link instead</span>
          </label>
        </div>
        ${proofControl}
      </div>`, 'Either a file (PDF/image, max 10MB) or a link is required.', 'proof_of_work');
}

function divisionField() {
    const divisions = (state.meta.divisions || []).map((value) => `<option value="${esc(value)}" ${state.values.division === value ? 'selected' : ''}>${esc(capitalize(value))}</option>`).join('');
    return fieldWrap('division', 'Division *', `
      <select id="f-division" name="division" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.division ? 'border-rose-400' : ''}" autocomplete="off">
        <option value="">— Select —</option>${divisions}
      </select>`, undefined, 'division');
}

function trainingBatchField() {
    const division = state.values.division;
    const max = division ? (state.meta.batch_ranges?.[division] ?? 51) : 0;
    const batches = division
        ? Array.from({ length: max }, (_, index) => String(index + 1))
            .map((batch) => `<option value="${batch}" ${String(state.values.ncc_batch) === batch ? 'selected' : ''}>${batch}</option>`)
            .join('')
        : '';
    const disabled = !division;
    const selectedValue = disabled ? '' : state.values.ncc_batch;

    return fieldWrap('ncc_batch', 'NCC Training Batch *', `
      <select id="f-ncc_batch" name="ncc_batch" ${disabled ? 'disabled' : ''} class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.ncc_batch ? 'border-rose-400' : ''}" autocomplete="off">
        <option value="">${disabled ? 'Select Division first' : '— Select —'}</option>${batches}
      </select>`, division ? getBatchHint() : undefined, 'ncc_batch');
}

function getBatchHint() {
    const max = state.meta.batch_ranges?.[state.values.division] ?? 51;
    return `${capitalize(state.values.division)} division: batches 1–${max}.`;
}

function nccYearField() {
    const years = (state.meta.years || []).map((year) => `<option value="${esc(year)}" ${String(state.values.ncc_year) === year ? 'selected' : ''}>${year} B.S.</option>`).join('');
    return fieldWrap('ncc_year', 'Year (B.S.) *', `<select id="f-ncc_year" name="ncc_year" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.ncc_year ? 'border-rose-400' : ''}"><option value="">— Select —</option>${years}</select>`, 'In the Nepali Bikram Sambat calendar.', 'ncc_year');
}

function trainingCenterField() {
    const centers = (state.meta.training_centers || []).map((center) => `<option value="${center.id}" ${Number(state.values.ncc_training_center_id) === Number(center.id) ? 'selected' : ''}>${esc(center.name_ne)}</option>`).join('');
    return fieldWrap('ncc_training_center_id', 'NCC Passout Training Center *', `
      <select id="f-ncc_training_center_id" name="ncc_training_center_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${state.errors.ncc_training_center_id ? 'border-rose-400' : ''}">
        <option value="">— Select —</option>${centers}
      </select>`, undefined, 'ncc_training_center_id');
}

function capitalize(value) {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : value;
}

function trainingCenterName(id) {
    const center = (state.meta.training_centers || []).find((c) => Number(c.id) === Number(id));
    return center ? center.name_ne : '';
}

function cvField() {
    const cv = state.files.cv;
    return fieldWrap('cv', 'CV / Resume (PDF only, max 5MB)', `
      <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 ${cv ? 'border-emerald-300 bg-emerald-50/40' : ''}">
        <input id="f-cv" name="cv" type="file" accept="application/pdf" class="sr-only">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M10 12H8M16 12h-4M10 16H8M16 16h-4"/></svg>
        </span>
        <span class="min-w-0 truncate text-sm">${cv ? esc(cv.name) : 'Upload your CV (PDF only)'}</span>
      </label>`, undefined, 'cv');
}

function skillFieldMarkup(field) {
    const key = `skill_${field.key}`;
    const error = state.errors[`skill_data.${field.key}`] || state.errors[`f-${key}`];
    const value = state.values[key];
    const file = state.files[key];
    const required = field.required ? '*' : '';
    const label = `${field.label}${required}`;
    const requiredAttr = field.required ? 'required' : '';

    let control;
    switch (field.type) {
        case 'url':
            control = baseInput(key, 'url', value, `${requiredAttr} placeholder="https://..."`);
            break;
        case 'textarea':
            control = `<textarea id="f-${key}" name="${key}" rows="3" placeholder="..." class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${error ? 'border-rose-400' : ''}">${valHtml(value)}</textarea>`;
            break;
        case 'select':
            control = `<select id="f-${key}" name="${key}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm ${error ? 'border-rose-400' : ''}">
                <option value="">— Select —</option>
                ${(field.options || []).map((option) => `<option value="${esc(option)}" ${value === option ? 'selected' : ''}>${esc(option)}</option>`).join('')}
              </select>`;
            break;
        case 'multiselect':
            control = `<div class="space-y-2">${(field.options || []).map((option) => {
                const checked = Array.isArray(value) && value.includes(option);
                return `<label class="flex items-center gap-2.5 rounded-lg border ${checked ? 'border-indigo-300 bg-indigo-50/50' : 'border-slate-200 bg-white'} px-3 py-2 text-sm text-slate-700 cursor-pointer">
                  <input type="checkbox" class="h-4 w-4 rounded border-slate-300 accent-indigo-600" data-ms="${esc(field.key)}" value="${esc(option)}" ${checked ? 'checked' : ''}>${esc(option)}</label>`;
            }).join('')}</div>`;
            break;
        case 'file':
            control = `<label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 ${file ? 'border-emerald-300 bg-emerald-50/40' : ''}">
                <input id="f-${key}" name="${key}" type="file" accept="${field.accept || ''}" class="sr-only">
                <span class="min-w-0 truncate text-sm">${file ? esc(file.name) : label}</span></label>`;
            break;
        default:
            control = baseInput(key, 'text', value, requiredAttr);
            break;
    }

    return fieldWrap(`f-${key}`, `${label}${required ? '' : ' (optional)'}`, control, field.type === 'multiselect' ? 'Choose all that apply.' : undefined, `skill_data.${field.key}`);
}

/* ------------------------------------------------------------------ */
/* Validation                                                          */
/* ------------------------------------------------------------------ */

function clearErrors() {
    state.errors = {};
}

function setError(key, message) {
    state.errors[key] = message;
}

function validateStep(step) {
    const errors = [];
    if (step === 1) {
        if (!String(state.values.full_name || '').trim()) errors.push(setError('full_name', 'Full name is required.'));
        if (!String(state.values.cadet_number || '').trim()) errors.push(setError('cadet_number', 'Cadet number is required.'));
        if (!String(state.values.phone || '').trim()) errors.push(setError('phone', 'Phone number is required.'));
        const email = String(state.values.email || '').trim();
        if (!email) errors.push(setError('email', 'Email address is required.'));
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push(setError('email', 'Enter a valid email address.'));
        const division = state.values.division;
        if (!division) errors.push(setError('division', 'Select your division.'));
        else {
            const max = state.meta.batch_ranges?.[division] ?? null;
            const batch = state.values.ncc_batch;
            if (!batch) errors.push(setError('ncc_batch', 'Select your training batch.'));
            else if (max !== null && (Number(batch) < 1 || Number(batch) > max)) errors.push(setError('ncc_batch', `Invalid batch for ${division} division.`));
        }
        if (!state.values.ncc_year) errors.push(setError('ncc_year', 'Select your year (B.S.).'));
        if (!state.values.ncc_training_center_id) errors.push(setError('ncc_training_center_id', 'Select your passout training center.'));
        if (state.errors.cadet_number_extra) errors.push(state.errors.cadet_number_extra);
    } else if (step === 2) {
        if (!state.values.province_id) errors.push(setError('province_id', 'Select your province.'));
        if (!state.values.district_id) errors.push(setError('district_id', 'Select your district.'));
        if (!String(state.values.address || '').trim()) errors.push(setError('address', 'Address is required.'));
        const citizen = String(state.values.citizenship_number || '').trim();
        if (!citizen) errors.push(setError('citizenship_number', 'Citizenship number is required.'));
        else if (!/^[A-Za-z0-9-]{6,30}$/.test(citizen)) errors.push(setError('citizenship_number', 'Use 6–30 letters, digits, or hyphens.'));
        if (!state.files.photo) errors.push(setError('photo', 'Upload your passport-size photo.'));
        if (!state.values.uniform_photo_confirmed) errors.push(setError('uniform_photo_confirmed', 'Please confirm the NCC dress requirement.'));
        const hasProofFile = !!state.files.proof_of_work_file;
        const hasProofUrl = !!String(state.values.proof_of_work_url || '').trim();
        if (!hasProofFile && !hasProofUrl) errors.push(setError('proof_of_work', 'Provide proof of work as a file or a link.'));
        if (state.files.proof_of_work_file && state.files.proof_of_work_file.size > 10 * 1024 * 1024) errors.push(setError('proof_of_work', 'Proof of work must be under 10MB.'));
        if (state.files.cv && (state.files.cv.type !== 'application/pdf' || state.files.cv.size > 5 * 1024 * 1024)) errors.push(setError('cv', 'CV must be a PDF under 5MB.'));
    } else if (step === 3) {
        state.fields.forEach((field) => {
            const key = `skill_${field.key}`;
            if (field.required) {
                if (field.type === 'multiselect' && (!Array.isArray(state.values[key]) || !state.values[key].length)) {
                    errors.push(setError(`skill_data.${field.key}`, `Select at least one ${field.label.toLowerCase()}.`));
                } else if (field.type === 'file' && !state.files[key]) {
                    errors.push(setError(`skill_data.${field.key}`, `${field.label} is required.`));
                } else if (!['multiselect', 'file'].includes(field.type) && !String(state.values[key] || '').trim()) {
                    errors.push(setError(`skill_data.${field.key}`, `${field.label} is required.`));
                }
            }
        });
    }
    return errors.length === 0;
}

/* ------------------------------------------------------------------ */
/* Navigation + events                                                 */
/* ------------------------------------------------------------------ */

function renderNav(step) {
    const nav = document.getElementById('wizard-nav');
    if (!nav) return;
    nav.innerHTML = `
      ${step > 1 ? '<button id="nav-back" type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</button>' : ''}
      ${step < 4
          ? '<button id="nav-next" type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Continue <span aria-hidden="true">→</span></button>'
          : `<button id="nav-submit" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" ${state.submitting ? 'disabled' : ''}>${state.submitting ? 'Submitting…' : 'Submit application'}</button>`}`;

    document.getElementById('nav-back')?.addEventListener('click', () => {
        state.errors = {};
        state.step -= 1;
        renderStepper();
        renderStep();
    });
    document.getElementById('nav-next')?.addEventListener('click', () => {
        if (!validateStep(state.step)) {
            renderStep();
            return;
        }
        state.errors = {};
        state.step += 1;
        renderStepper();
        renderStep();
    });
    document.getElementById('nav-submit')?.addEventListener('click', submitApplication);
}

function onInput(event) {
    const field = event.target;
    const name = field.name;
    if (!name) return;
    state.values[name] = field.type === 'checkbox' ? field.checked : field.value;

    if (name === 'cadet_number') {
        clearErrorFor(name);
        debounceCadetCheck(field.value);
    }
    if (name === 'proof_of_work_url') {
        delete state.errors.proof_of_work;
    }
}

function onChange(event) {
    const field = event.target;
    const name = field.name;

    if (field.tagName === 'SELECT') {
        state.values[name] = field.value;
        if (name === 'province_id') {
            state.values.district_id = '';
            state.geos.districts = [];
            if (field.value) {
                loadDistricts(field.value).then(() => renderStep());
                return;
            }
            renderStep();
            return;
        }
        if (name === 'division') {
            state.values.ncc_batch = '';
            clearErrorFor(['division', 'ncc_batch']);
            renderStep();
            return;
        }
        if (name === 'ncc_batch' || name === 'ncc_year' || name === 'ncc_training_center_id') {
            clearErrorFor(name);
            renderStep();
            return;
        }
        if (name === 'district_id') {
            clearErrorFor('district_id');
        }
        return;
    }

    if (name === 'uniform_photo_confirmed') {
        state.values[name] = field.checked;
        clearErrorFor(name);
        return;
    }
    if (field.type === 'checkbox' && field.dataset.ms) {
        handleMultiselect(field);
        return;
    }
    if (field.type === 'file') {
        handleFileField(field);
        return;
    }
    if (field.type === 'radio' && name === 'proof_mode') {
        state.values.proof_mode = field.value;
        if (field.value === 'url') {
            delete state.files.proof_of_work_file;
        }
        delete state.errors.proof_of_work;
        renderStep();
        return;
    }
    clearErrorFor(name);
}

function clearErrorFor(key) {
    for (const k of Array.isArray(key) ? key : [key]) {
        if (k in state.errors) delete state.errors[k];
    }
}

function handleMultiselect(checkbox) {
    const schemaKey = checkbox.dataset.ms;
    const key = `skill_${schemaKey}`;
    const current = Array.isArray(state.values[key]) ? state.values[key] : [];
    state.values[key] = checkbox.checked ? [...new Set([...current, checkbox.value])] : current.filter((item) => item !== checkbox.value);
    clearErrorFor(`skill_data.${schemaKey}`);
}

function handleFileField(input) {
    const name = input.name;
    const file = input.files && input.files[0];
    if (!file) return;

    if (name === 'photo') {
        if (!file.type.startsWith('image/')) {
            setError('photo', 'Photo must be an image (JPG/PNG/WebP).');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            setError('photo', 'Photo must be under 2MB.');
            return;
        }
        state.files.photo = file;
        clearErrorFor('photo');
    } else if (name === 'cv') {
        if (file.type !== 'application/pdf') {
            setError('cv', 'CV must be a PDF file.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            setError('cv', 'CV must be under 5MB.');
            return;
        }
        state.files.cv = file;
        clearErrorFor('cv');
    } else if (name === 'proof_of_work_file') {
        const ok = ['application/pdf', 'image/jpeg', 'image/png'].includes(file.type);
        if (!ok) {
            setError('proof_of_work', 'Proof of work must be a PDF or image.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            setError('proof_of_work', 'Proof of work must be under 10MB.');
            return;
        }
        state.files.proof_of_work_file = file;
        state.values.proof_mode = 'file';
        state.values.proof_of_work_url = '';
        clearErrorFor('proof_of_work');
    } else if (name.startsWith('skill_')) {
        state.files[name] = file;
        clearErrorFor(`skill_data.${name.slice(6)}`);
    }
    renderStep();
}

let cadetTimer = null;
let lastCadetChecked = '';
function debounceCadetCheck(raw) {
    clearTimeout(cadetTimer);
    const value = String(raw || '').trim();
    if (!value) return;
    cadetTimer = setTimeout(() => checkCadetNumber(value), 450);
}

async function checkCadetNumber(value) {
    if (value === lastCadetChecked) return;
    lastCadetChecked = value;
    try {
        const data = await api(`/api/v1/job-applications/check-cadet-number?cadet_number=${encodeURIComponent(value)}`);
        if (!data.available) {
            setError('cadet_number', 'This Cadet Number has already been used. If you already applied, contact us.');
        } else {
            clearErrorFor('cadet_number');
        }
        const cadetField = document.getElementById('f-cadet_number');
        const siblings = cadetField?.closest('div')?.querySelectorAll('.text-rose-600');
        siblings?.forEach((node) => node.remove());
        if (cadetField && state.errors.cadet_number) {
            cadetField.insertAdjacentHTML('afterend', `<p class="mt-1 text-xs text-rose-600">${esc(state.errors.cadet_number)}</p>`);
        }
    } catch (error) {
        // Rate-limited or network hiccup: let the server decide on submit.
    }
}

/* ------------------------------------------------------------------ */
/* Review + submit                                                     */
/* ------------------------------------------------------------------ */

function reviewRows() {
    const personal = [
        ['Full name', state.values.full_name],
        ['Cadet number', state.values.cadet_number],
        ['Phone', state.values.phone],
        ['Email', state.values.email],
        ['NCC batch', state.values.ncc_batch],
        ['Year (B.S.)', state.values.ncc_year],
        ['Division', state.values.division ? capitalize(state.values.division) : ''],
        ['Training center', trainingCenterName(state.values.ncc_training_center_id)],
        ['School', state.values.school],
    ];
    const documents = [
        ['Province', state.geos.provinces.find((province) => Number(province.id) === Number(state.values.province_id))?.name_en],
        ['District', state.geos.districts.find((district) => Number(district.id) === Number(state.values.district_id))?.name_en],
        ['Address', state.values.address],
        ['Citizenship number', state.values.citizenship_number],
        ['Photo', state.files.photo?.name],
        ['Proof of work', state.files.proof_of_work_file?.name || state.values.proof_of_work_url],
        ['CV', state.files.cv?.name || 'Not provided'],
    ];
    const skills = state.fields.map((field) => {
        const value = state.values[`skill_${field.key}`];
        const file = state.files[`skill_${field.key}`];
        const display = Array.isArray(value) ? value.join(', ') : file?.name || value || 'Not provided';
        return [field.label, display];
    });
    return [['Personal & NCC', personal], ['Address & Documents', documents], ['Skill Details', skills]];
}

function renderReview() {
    const container = document.getElementById('review-summary');
    if (!container) return;
    container.innerHTML = reviewRows().map(([title, rows]) => `
      <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">${esc(title)}</h3>
        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
          ${rows.map(([label, value]) => `<div class="min-w-0"><dt class="text-xs font-medium text-slate-400">${label}</dt><dd class="mt-0.5 break-words text-sm text-slate-700">${value ? esc(value) : '<span class="text-slate-300">Not provided</span>'}</dd></div>`).join('')}
        </dl>
      </section>`).join('');
}

async function submitApplication() {
    if (state.submitting) return;
    state.submitting = true;
    renderNav(4);

    const fd = new FormData();
    fd.append('category_slug', state.category.slug);
    fd.append('full_name', String(state.values.full_name || '').trim());
    fd.append('cadet_number', String(state.values.cadet_number || '').trim());
    fd.append('phone', String(state.values.phone || '').trim());
    fd.append('email', String(state.values.email || '').trim());
    fd.append('ncc_batch', state.values.ncc_batch || '');
    fd.append('ncc_year', state.values.ncc_year || '');
    fd.append('division', state.values.division || '');
    fd.append('ncc_training_center_id', String(state.values.ncc_training_center_id || ''));
    fd.append('school', String(state.values.school || '').trim());
    fd.append('province_id', String(state.values.province_id || ''));
    fd.append('district_id', String(state.values.district_id || ''));
    fd.append('address', String(state.values.address || '').trim());
    fd.append('citizenship_number', String(state.values.citizenship_number || '').trim());
    fd.append('uniform_photo_confirmed', state.values.uniform_photo_confirmed ? '1' : '0');
    if (state.files.photo) fd.append('photo', state.files.photo);
    if (state.files.cv) fd.append('cv', state.files.cv);
    if (state.files.proof_of_work_file) {
        fd.append('proof_of_work_file', state.files.proof_of_work_file);
    } else if (String(state.values.proof_of_work_url || '').trim()) {
        fd.append('proof_of_work_url', String(state.values.proof_of_work_url).trim());
    }
    state.fields.forEach((field) => {
        const key = `skill_${field.key}`;
        const file = state.files[key];
        if (file) {
            fd.append(`skill_data[${field.key}]`, file);
        } else if (field.type === 'multiselect') {
            (state.values[key] || []).forEach((item) => fd.append(`skill_data[${field.key}][]`, item));
        } else if (state.values[key] !== undefined && state.values[key] !== '') {
            fd.append(`skill_data[${field.key}]`, String(state.values[key]));
        }
    });

    try {
        const result = await api('/api/v1/job-applications', { method: 'POST', body: fd });
        renderSuccess(result);
    } catch (error) {
        state.submitting = false;
        if (error.status === 422 && error.errors) {
            const firstStep = errorStep(error.errors);
            state.errors = {};
            Object.entries(error.errors).forEach(([key, messages]) => {
                if (key.startsWith('skill_data.')) {
                    setError(key, Array.isArray(messages) ? messages[0] : messages);
                } else {
                    setError(key, Array.isArray(messages) ? messages[0] : messages);
                }
            });
            state.step = firstStep;
            renderStepper();
            renderStep();
            return;
        }
        state.errors.global = error.message;
        renderStep();
    }
}

function errorStep(errors) {
    const skillKeys = Object.keys(errors).filter((key) => key.startsWith('skill_data.')).length;
    if (skillKeys) return 3;
    const documentKeys = ['province_id', 'district_id', 'address', 'citizenship_number', 'photo', 'uniform_photo_confirmed', 'proof_of_work', 'cv'];
    if (Object.keys(errors).some((key) => documentKeys.includes(key))) return 2;
    return 1;
}

function renderSuccess(result) {
    app.innerHTML = `
      <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <h1 class="mt-5 text-2xl font-bold text-slate-900">Application submitted</h1>
          <p class="mt-2 text-sm text-slate-500">Your application for <strong>${esc(state.category.name)}</strong> has been received. We will review it and contact you at ${esc(state.values.email)} if your profile matches our needs.</p>
          <div class="mt-6 rounded-lg bg-slate-50 px-4 py-3 text-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Reference number</div>
            <div class="mt-1 font-semibold text-slate-800">${esc(result.reference)}</div>
          </div>
          <button id="portal-done" type="button" class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Back to open positions</button>
        </div>
      </div>`;
    document.getElementById('portal-done').addEventListener('click', () => window.location.replace('/apply'));
}

/* ------------------------------------------------------------------ */
/* Boot                                                                */
/* ------------------------------------------------------------------ */

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.skill) {
        initWizard().catch(renderFatal);
    } else {
        renderLanding().catch(renderFatal);
    }
});

function renderFatal(error) {
    app.innerHTML = `<div class="min-h-screen px-4 py-16 text-center">
      <h1 class="text-lg font-semibold text-slate-900">We could not load the portal</h1>
      <p class="mt-2 text-sm text-slate-500">${esc(error.message)}</p>
      <button id="retry" class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Retry</button>
    </div>`;
    document.getElementById('retry')?.addEventListener('click', () => {
        if (document.body.dataset.skill) initWizard(); else renderLanding();
    });
}