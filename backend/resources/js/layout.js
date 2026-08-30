import { esc } from './ui';
import { api, store } from './api';

const NAV = [
    { hash: '#/dashboard', key: 'dashboard', label: 'Dashboard', icon: svgHome() },
    { hash: '#/users', key: 'users', label: 'Users', icon: svgUsers() },
    { hash: '#/roles', key: 'roles', label: 'Roles', icon: svgRoles(), permission: 'roles.view' },
    { hash: '#/cadets', key: 'cadets', label: 'Cadets', icon: svgCadets() },
];

export function shell(user) {
    const app = document.getElementById('app');
    app.innerHTML = `
    <div class="min-h-screen flex">
      <aside id="sidebar" class="w-64 bg-slate-900 text-slate-200 flex flex-col fixed inset-y-0 left-0">
        <div class="px-5 py-5 border-b border-slate-800">
          <div class="font-bold text-white tracking-tight">NCCAA HRMS</div>
          <div class="text-xs text-slate-400 mt-0.5">Admin Console</div>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1" id="nav"></nav>
        <div class="px-5 py-4 border-t border-slate-800 text-xs text-slate-400">${esc(user.name)}</div>
      </aside>
      <div class="flex-1 ml-64">
        <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between sticky top-0 z-10">
          <h1 id="page-title" class="text-lg font-semibold text-slate-800"></h1>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <div class="text-sm font-medium text-slate-700">${esc(user.name)}</div>
              <div class="text-xs text-slate-400">${esc((user.roles || []).join(', '))}</div>
            </div>
            <span id="user-role-badge" class="hidden px-2 py-1 rounded bg-indigo-50 text-indigo-700 text-xs font-medium"></span>
            <button id="logout" class="text-sm px-3 py-1.5 rounded border border-slate-300 text-slate-600 hover:bg-slate-50">Log out</button>
          </div>
        </header>
        <main id="page-content" class="p-6"></main>
      </div>
      <div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
    </div>`;

    const badge = document.getElementById('user-role-badge');
    if (user.roles?.length) {
        badge.textContent = user.roles
            .map((r) => String(r).replaceAll('-', ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
            .join(', ');
        badge.classList.remove('hidden');
    }

    const nav = document.getElementById('nav');
    NAV.forEach((item) => {
        if (item.permission && !store.hasPermission(item.permission)) {
            return;
        }
        const a = document.createElement('a');
        a.href = item.hash;
        a.dataset.key = item.key;
        a.className = 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors';
        a.innerHTML = `${item.icon}<span>${item.label}</span>`;
        nav.appendChild(a);
    });

    nav.querySelectorAll('a').forEach((a) => {
        a.addEventListener('click', () => {
            nav.querySelectorAll('a').forEach((x) => x.classList.remove('bg-slate-800', 'text-white'));
            a.classList.add('bg-slate-800', 'text-white');
        });
    });

    document.getElementById('logout').addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } catch {
            // ignore, token cleared regardless
        }
        store.clear();
        window.location.hash = '#/login';
    });
}

export function setActive(key) {
    const nav = document.getElementById('nav');
    const titles = { dashboard: 'Dashboard', users: 'Users', roles: 'Roles', cadets: 'Cadets' };
    const title = document.getElementById('page-title');
    if (title) {
        title.textContent = titles[key] || '';
    }
    nav?.querySelectorAll('a').forEach((a) => {
        if (a.dataset.key === key) {
            a.classList.add('bg-slate-800', 'text-white');
        } else {
            a.classList.remove('bg-slate-800', 'text-white');
        }
    });
}

export function content(node) {
    const main = document.getElementById('page-content');
    if (typeof node === 'string') {
        main.innerHTML = node;
        return main;
    }
    main.replaceChildren(node);
    return main;
}

function svgHome() {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10"/></svg>';
}

function svgUsers() {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-1a3 3 0 10-3-3"/></svg>';
}

function svgCadets() {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11a5 5 0 10-4.6-8 5 5 0 00-1.4 9.9M8 21H6a2 2 0 01-2-2v-1a5 5 0 0110 0v1a2 2 0 01-2 2h0"/></svg>';
}

function svgRoles() {
    return '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42M12 14l-6.16-3.42M12 20l9-5-9-5-9 5 9 5z"/></svg>';
}
