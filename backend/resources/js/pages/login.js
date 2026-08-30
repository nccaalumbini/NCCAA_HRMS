import { api, store } from '../api';
import { esc, toast } from '../ui';

export function renderLogin() {
    const app = document.getElementById('app');
    app.innerHTML = `
    <div class="min-h-screen flex items-center justify-center px-4">
      <div class="w-full max-w-sm">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-indigo-600 text-white font-bold text-xl mb-3">N</div>
          <h1 class="text-2xl font-bold text-slate-900">NCCAA HRMS</h1>
          <p class="text-sm text-slate-500 mt-1">Sign in to the Admin Console</p>
        </div>
        <form id="login-form" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Username or email</label>
            <input id="login-input" type="text" autocomplete="username" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <input id="password-input" type="password" autocomplete="current-password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
            <p id="login-error" class="mt-1 text-xs text-rose-600 hidden"></p>
          </div>
          <button type="submit" id="login-btn" class="w-full rounded-md bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Sign in</button>
        </form>
        <p class="text-center text-xs text-slate-400 mt-4">Super admin (dev): admin@nccaa.gov.np / password</p>
      </div>
    </div>`;

    const form = document.getElementById('login-form');
    const error = document.getElementById('login-error');
    const btn = document.getElementById('login-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'Signing in…';

        try {
            const data = await api('/auth/login', {
                method: 'POST',
                body: {
                    login: document.getElementById('login-input').value.trim(),
                    password: document.getElementById('password-input').value,
                },
            });
            store.setToken(data.token);
            const me = await api('/auth/me');
            me.permissions = Array.isArray(me.permissions) ? me.permissions : [];
            store.setUser(me);
            window.location.hash = '#/dashboard';
        } catch (err) {
            error.textContent = esc(err.message);
            error.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Sign in';
        }
    });
}
