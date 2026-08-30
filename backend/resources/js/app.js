import { store } from './api';
import { shell } from './layout';
import { renderLogin } from './pages/login';
import * as dashboard from './pages/dashboard';
import * as users from './pages/users';
import * as roles from './pages/roles';
import * as cadets from './pages/cadets';
import * as profile from './pages/profile';
import * as recruitment from './pages/recruitment';
import * as email from './pages/email';

const routes = {
    '#/dashboard': dashboard.render,
    '#/users': users.render,
    '#/profile': profile.render,
    '#/roles': roles.render,
    '#/cadets': cadets.render,
    '#/recruitment': recruitment.render,
    '#/email': email.render,
    '#/login': renderLogin,
};

function currentHash() {
    const hash = window.location.hash || '';
    const normalized = hash.split('?')[0] || '#';
    if (!normalized || normalized === '#') {
        return '#/dashboard';
    }
    return routes[normalized] ? normalized : '#/dashboard';
}

async function route() {
    const hash = currentHash();
    const user = store.getUser();
    const token = store.getToken();

    if (hash === '#/login') {
        if (token && user) {
            renderRoute('#/dashboard');
        } else {
            renderLogin();
        }
        return;
    }

    if (!token || !user) {
        shellLoginFallback();
        return;
    }

    shell(user);
    await renderRoute(hash);
}

function shellLoginFallback() {
    renderLogin();
}

async function renderRoute(hash) {
    try {
        await routes[hash]();
    } catch (err) {
        const main = document.getElementById('page-content');
        if (main) {
            main.innerHTML = `<div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg p-4 text-sm">${err.message}</div>`;
        }
    }
}

window.addEventListener('hashchange', () => route());
route();
