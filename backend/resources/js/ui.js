export function esc(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function toast(message, type = 'success') {
    const colors = {
        success: 'bg-emerald-600',
        error: 'bg-rose-600',
        info: 'bg-slate-700',
    };
    const container = document.getElementById('toasts');
    if (!container) {
        return;
    }
    const node = document.createElement('div');
    node.className = `pointer-events-auto ${colors[type] || colors.info} text-white text-sm px-4 py-3 rounded-lg shadow-lg`;
    node.textContent = message;
    container.appendChild(node);
    setTimeout(() => {
        node.style.opacity = '0';
        node.style.transition = 'opacity 300ms';
        setTimeout(() => node.remove(), 320);
    }, 3000);
}

export function fieldError(errors, key) {
    const matches = Object.entries(errors || {}).filter(([k]) => k === key || k.startsWith(`${key}.`));
    if (!matches.length) {
        return '';
    }
    return `<p class="mt-1 text-xs text-rose-600">${esc(matches[0][1][0])}</p>`;
}

export function spinner() {
    return '<div class="py-16 text-center text-slate-400">Loading…</div>';
}

export function badge(status) {
    const map = {
        active: 'bg-emerald-100 text-emerald-700',
        inactive: 'bg-slate-100 text-slate-600',
        suspended: 'bg-amber-100 text-amber-700',
        graduated: 'bg-indigo-100 text-indigo-700',
        disabled: 'bg-rose-100 text-rose-700',
    };
    const cls = map[status] || 'bg-slate-100 text-slate-600';
    return `<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}">${esc(status)}</span>`;
}

export function pagination(meta, onPage) {
    if (!meta || meta.last_page <= 1) {
        return '';
    }
    const current = meta.current_page;
    const last = meta.last_page;
    const pages = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - current) <= 2) {
            pages.push(p);
        } else if (pages[pages.length - 1] !== '…') {
            pages.push('…');
        }
    }
    return `
    <div class="mt-4 flex items-center justify-between text-sm">
      <span class="text-slate-500">Page ${current} of ${last} · ${meta.total} total</span>
      <div class="flex gap-1">
        <button data-page="${current - 1}" class="px-3 py-1 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40" ${current <= 1 ? 'disabled' : ''}>Prev</button>
        ${pages
            .map((p) =>
                p === '…'
                    ? `<span class="px-2 py-1 text-slate-400">…</span>`
                    : `<button data-page="${p}" class="px-3 py-1 rounded border ${p === current ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'}">${p}</button>`,
            )
            .join('')}
        <button data-page="${current + 1}" class="px-3 py-1 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40" ${current >= last ? 'disabled' : ''}>Next</button>
      </div>
    </div>`;
}

export function bindPagination(root, onPage) {
    root.querySelectorAll('[data-page]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const p = parseInt(btn.dataset.page, 10);
            if (!Number.isNaN(p)) {
                onPage(p);
            }
        });
    });
}
