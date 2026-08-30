import { store, api } from '../api';
import { content } from '../layout';

export async function render() {
    const user = store.getUser();
    const stats = await loadStats();
    content(`
    <div class="mb-6">
      <p class="text-slate-500">Welcome back, <span class="font-medium text-slate-700">${user?.name || 'Administrator'}</span>.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm text-slate-500">Total Users</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">${stats.totalUsers}</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm text-slate-500">Total Cadets</div>
        <div class="mt-1 text-3xl font-bold text-slate-900">${stats.totalCadets}</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm text-slate-500">Active Users</div>
        <div class="mt-1 text-3xl font-bold text-emerald-600">${stats.activeUsers}</div>
      </div>
    </div>
    <div class="mt-6">
      <a href="#/users" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Manage Users →</a>
      <span class="mx-3 text-slate-300">|</span>
      <a href="#/cadets" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Manage Cadets →</a>
    </div>`);
}

async function loadStats() {
    try {
        const [usersRaw, cadetsRaw] = await Promise.all([
            api('/users', { params: { per_page: 1 } }),
            api('/cadets', { params: { per_page: 1 } }),
        ]);
        return {
            totalUsers: usersRaw?.meta?.total ?? 0,
            totalCadets: cadetsRaw?.meta?.total ?? 0,
            activeUsers: 0,
        };
    } catch {
        return { totalUsers: 0, totalCadets: 0, activeUsers: 0 };
    }
}
