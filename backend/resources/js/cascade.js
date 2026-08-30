import { loadDistricts, loadLocalLevels, loadProvinces, loadWards } from './reference';

export function cascadeSelect({ container, provinceId, districtId, localLevelId, wardId, onData }) {
    const els = {};

    function selectEl(id, label) {
        return `
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">${label}</label>
          <select id="${id}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">—</option>
          </select>
        </div>`;
    }

    container.innerHTML = `
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        ${selectEl('geo-province', 'Province')}
        ${selectEl('geo-district', 'District')}
        ${selectEl('geo-locallevel', 'Local Level')}
        ${selectEl('geo-ward', 'Ward')}
      </div>`;

    els.province = container.querySelector('#geo-province');
    els.district = container.querySelector('#geo-district');
    els.localLevel = container.querySelector('#geo-locallevel');
    els.ward = container.querySelector('#geo-ward');

    function emit() {
        onData?.({
            province_id: els.province.value ? Number(els.province.value) : null,
            district_id: els.district.value ? Number(els.district.value) : null,
            local_level_id: els.localLevel.value ? Number(els.localLevel.value) : null,
            ward_id: els.ward.value ? Number(els.ward.value) : null,
        });
    }

    function fill(select, items, selectedId, labelKey = 'name_en') {
        select.innerHTML = '<option value="">—</option>';
        items.forEach((item) => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = `${item[labelKey]}`;
            if (item.id === selectedId) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    async function loadProvince() {
        const provinces = await loadProvinces();
        fill(els.province, provinces, provinceId);
        els.district.innerHTML = '<option value="">—</option>';
        els.localLevel.innerHTML = '<option value="">—</option>';
        els.ward.innerHTML = '<option value="">—</option>';
    }

    els.province.addEventListener('change', async () => {
        const pid = els.province.value ? Number(els.province.value) : null;
        if (!pid) {
            els.district.innerHTML = '<option value="">—</option>';
            els.localLevel.innerHTML = '<option value="">—</option>';
            els.ward.innerHTML = '<option value="">—</option>';
            emit();
            return;
        }
        const districts = await loadDistricts(pid);
        fill(els.district, districts, districtId);
        els.localLevel.innerHTML = '<option value="">—</option>';
        els.ward.innerHTML = '<option value="">—</option>';
        emit();
    });

    els.district.addEventListener('change', async () => {
        const did = els.district.value ? Number(els.district.value) : null;
        if (!did) {
            els.localLevel.innerHTML = '<option value="">—</option>';
            els.ward.innerHTML = '<option value="">—</option>';
            emit();
            return;
        }
        const levels = await loadLocalLevels(did);
        fill(els.localLevel, levels, localLevelId);
        els.ward.innerHTML = '<option value="">—</option>';
        emit();
    });

    els.localLevel.addEventListener('change', async () => {
        const lid = els.localLevel.value ? Number(els.localLevel.value) : null;
        if (!lid) {
            els.ward.innerHTML = '<option value="">—</option>';
            emit();
            return;
        }
        const wards = await loadWards(lid);
        fill(els.ward, wards, wardId, 'ward_number');
        els.ward.querySelectorAll('option').forEach((o, i) => {
            if (i > 0 && Number(o.value) === wardId) {
                o.selected = true;
            }
        });
        emit();
    });

    loadProvince().then(emit);

    return {
        populate(existing) {
            if (existing?.province_id) {
                els.province.value = existing.province_id;
                loadDistricts(existing.province_id).then((ds) => {
                    fill(els.district, ds, existing.district_id);
                    if (existing.district_id) {
                        loadLocalLevels(existing.district_id).then((ls) => {
                            fill(els.localLevel, ls, existing.local_level_id);
                            if (existing.local_level_id) {
                                loadWards(existing.local_level_id).then((ws) => {
                                    fill(els.ward, ws, existing.ward_id, 'ward_number');
                                    emit();
                                });
                            }
                        });
                    }
                });
            }
        },
    };
}
