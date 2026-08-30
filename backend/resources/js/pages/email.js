import { api, store } from '../api';
import { content, setActive } from '../layout';
import { bindPagination, esc, pagination, spinner, toast } from '../ui';
import { loadDistricts, loadProvinces, loadRanks } from '../reference';

let activeTab = 'campaigns'; // 'campaigns' | 'compose' | 'settings'
let state = {
    campaignSearch: '',
    campaignStatus: '',
    campaignPage: 1,
    recipientSearch: '',
    recipientProvince: '',
    recipientDistrict: '',
    recipientRank: '',
    recipientPage: 1,
    selectedRecipientIds: new Set(),
};

export async function render() {
    setActive('email');
    const user = store.getUser();
    const can = {
        viewCampaigns: store.hasPermission('email.campaigns.view'),
        createCampaigns: store.hasPermission('email.campaigns.create'),
        sendCampaigns: store.hasPermission('email.campaigns.send'),
        cancelCampaigns: store.hasPermission('email.campaigns.cancel'),
        viewDelivery: store.hasPermission('email.delivery.view'),
        viewSettings: store.hasPermission('email.settings.view'),
        manageSettings: store.hasPermission('email.settings.manage'),
        testSmtp: store.hasPermission('email.smtp.test'),
    };

    const root = content('');
    root.innerHTML = `
      <div class="mx-auto max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Email &amp; Bulk Campaigns</h2>
            <p class="mt-1 text-sm text-slate-500">Send announcements, directives, and notifications to cadets and members.</p>
          </div>
          <div class="flex gap-2">
            ${can.createCampaigns ? `<button id="btn-compose-tab" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Compose Campaign</button>` : ''}
          </div>
        </div>

        <div class="mb-6 border-b border-slate-200">
          <nav class="flex space-x-6 text-sm font-medium">
            ${can.viewCampaigns ? `<button id="tab-campaigns" class="pb-3 border-b-2 transition ${activeTab === 'campaigns' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'}">Campaign History</button>` : ''}
            ${can.createCampaigns ? `<button id="tab-compose" class="pb-3 border-b-2 transition ${activeTab === 'compose' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'}">Compose &amp; Send</button>` : ''}
            ${can.viewSettings ? `<button id="tab-settings" class="pb-3 border-b-2 transition ${activeTab === 'settings' ? 'border-indigo-600 text-indigo-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'}">SMTP Configuration</button>` : ''}
          </nav>
        </div>

        <div id="email-tab-content"></div>
      </div>`;

    root.querySelector('#btn-compose-tab')?.addEventListener('click', () => {
        activeTab = 'compose';
        render();
    });
    root.querySelector('#tab-campaigns')?.addEventListener('click', () => {
        activeTab = 'campaigns';
        render();
    });
    root.querySelector('#tab-compose')?.addEventListener('click', () => {
        activeTab = 'compose';
        render();
    });
    root.querySelector('#tab-settings')?.addEventListener('click', () => {
        activeTab = 'settings';
        render();
    });

    const tabContainer = root.querySelector('#email-tab-content');
    if (activeTab === 'compose' && can.createCampaigns) {
        await renderCompose(tabContainer, can);
    } else if (activeTab === 'settings' && can.viewSettings) {
        await renderSettings(tabContainer, can);
    } else {
        await renderCampaigns(tabContainer, can);
    }
}

// ---------------- CAMPAIGNS TAB ----------------

async function renderCampaigns(container, can) {
    container.innerHTML = spinner();
    const data = await api('/email/campaigns', {
        params: { search: state.campaignSearch, status: state.campaignStatus, page: state.campaignPage },
    });

    container.innerHTML = `
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 bg-slate-50/60 p-4">
          <input id="campaign-search" value="${esc(state.campaignSearch)}" placeholder="Search campaign subject or title…"
            class="min-w-[240px] flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500">
          <select id="campaign-status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All statuses</option>
            <option value="draft" ${state.campaignStatus === 'draft' ? 'selected' : ''}>Draft</option>
            <option value="queued" ${state.campaignStatus === 'queued' ? 'selected' : ''}>Queued</option>
            <option value="processing" ${state.campaignStatus === 'processing' ? 'selected' : ''}>Processing</option>
            <option value="completed" ${state.campaignStatus === 'completed' ? 'selected' : ''}>Completed</option>
            <option value="partial" ${state.campaignStatus === 'partial' ? 'selected' : ''}>Partial</option>
            <option value="failed" ${state.campaignStatus === 'failed' ? 'selected' : ''}>Failed</option>
            <option value="cancelled" ${state.campaignStatus === 'cancelled' ? 'selected' : ''}>Cancelled</option>
          </select>
        </div>
        <div id="campaigns-table" class="overflow-x-auto"></div>
        <div id="campaigns-pagination" class="border-t border-slate-100 px-4 py-3"></div>
      </div>`;

    container.querySelector('#campaign-search').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.campaignSearch = e.target.value.trim();
            state.campaignPage = 1;
            renderCampaigns(container, can);
        }
    });

    container.querySelector('#campaign-status').addEventListener('change', (e) => {
        state.campaignStatus = e.target.value;
        state.campaignPage = 1;
        renderCampaigns(container, can);
    });

    const tableEl = container.querySelector('#campaigns-table');
    if (!data.items.length) {
        tableEl.innerHTML = '<div class="p-12 text-center text-sm text-slate-400">No email campaigns found.</div>';
    } else {
        tableEl.innerHTML = `
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Campaign</th>
                <th class="px-4 py-3">Created By</th>
                <th class="px-4 py-3">Recipients</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              ${data.items.map((c) => `
                <tr class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 align-top">
                    <div class="font-semibold text-slate-800">${esc(c.title)}</div>
                    <div class="text-xs text-slate-500 mt-0.5">${esc(c.subject)}</div>
                  </td>
                  <td class="px-4 py-3 align-top text-xs text-slate-600">
                    ${esc(c.created_by?.name || 'System')}
                    <div class="text-slate-400">${esc(c.province?.name_en || 'Nationwide')}</div>
                  </td>
                  <td class="px-4 py-3 align-top text-xs">
                    <span class="font-semibold text-slate-700">${c.total_recipients} total</span>
                    <div class="text-emerald-600">${c.sent_count} sent · <span class="text-rose-500">${c.failed_count} failed</span></div>
                  </td>
                  <td class="px-4 py-3 align-top">${campaignBadge(c.status)}</td>
                  <td class="px-4 py-3 align-top text-xs text-slate-500">${new Date(c.created_at).toLocaleDateString()}</td>
                  <td class="px-4 py-3 text-right align-top space-x-2">
                    <button data-view-campaign="${c.id}" class="rounded border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Details</button>
                    ${can.sendCampaigns && (c.status === 'draft' || c.status === 'failed') ? `<button data-send-campaign="${c.id}" class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">Send</button>` : ''}
                    ${can.cancelCampaigns && (c.status === 'queued' || c.status === 'processing') ? `<button data-cancel-campaign="${c.id}" class="rounded bg-rose-50 border border-rose-200 px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-100">Cancel</button>` : ''}
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>`;

        tableEl.querySelectorAll('[data-view-campaign]').forEach((btn) => {
            btn.addEventListener('click', () => showCampaignModal(btn.dataset.viewCampaign, can));
        });

        tableEl.querySelectorAll('[data-send-campaign]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (confirm('Are you sure you want to queue this campaign for delivery?')) {
                    await api(`/email/campaigns/${btn.dataset.sendCampaign}/send`, { method: 'POST' });
                    toast('Campaign queued for delivery.', 'success');
                    renderCampaigns(container, can);
                }
            });
        });

        tableEl.querySelectorAll('[data-cancel-campaign]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (confirm('Cancel this campaign?')) {
                    await api(`/email/campaigns/${btn.dataset.cancelCampaign}/cancel`, { method: 'POST' });
                    toast('Campaign cancelled.', 'info');
                    renderCampaigns(container, can);
                }
            });
        });
    }

    const pageEl = container.querySelector('#campaigns-pagination');
    pageEl.innerHTML = pagination(data.meta);
    bindPagination(pageEl, (next) => {
        state.campaignPage = next;
        renderCampaigns(container, can);
    });
}

// ---------------- COMPOSE TAB ----------------

async function renderCompose(container, can) {
    container.innerHTML = spinner();
    const provinces = await loadProvinces().catch(() => []);
    const ranks = await loadRanks().catch(() => []);

    container.innerHTML = `
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Compose Left Form -->
        <div class="lg:col-span-7 space-y-5 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <h3 class="text-base font-semibold text-slate-800 border-b border-slate-100 pb-3">1. Compose Message</h3>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Campaign Internal Title *</label>
            <input id="cmp-title" placeholder="e.g. Flood Relief Digital Unit Mobilization" class="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email Subject Line *</label>
            <input id="cmp-subject" placeholder="e.g. Urgent NCC Cadet Notice: Mobilization Update" class="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none">
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Sender Name</label>
              <input id="cmp-sender-name" placeholder="NCCAA Secretariat" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Reply-To Email</label>
              <input id="cmp-reply-to" type="email" placeholder="contact@nccaa.org.np" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-slate-700">HTML Message Body *</label>
              <div class="text-xs text-slate-400">Placeholders: <span class="font-mono bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{{name}}</span>, <span class="font-mono bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{{email}}</span></div>
            </div>
            <textarea id="cmp-body" rows="9" placeholder="<p>Dear {{name}},</p><p>We are writing to announce...</p>" class="w-full font-mono text-xs rounded-lg border border-slate-200 p-3 text-slate-800 focus:border-indigo-500 focus:outline-none"></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Additional Manual Emails (comma separated)</label>
            <input id="cmp-manual-emails" placeholder="coordinator@example.com, officer@example.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <span id="selected-badge" class="text-sm font-semibold text-indigo-600">${state.selectedRecipientIds.size} cadets selected</span>
            <div class="flex gap-2">
              <button id="btn-save-draft" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save as Draft</button>
              <button id="btn-send-now" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Send Campaign</button>
            </div>
          </div>
        </div>

        <!-- Recipient Selector Right Panel -->
        <div class="lg:col-span-5 space-y-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-semibold text-slate-800">2. Select Recipients</h3>
            <button id="btn-select-all" class="text-xs font-medium text-indigo-600 hover:underline">Select all visible</button>
          </div>

          <!-- Filters -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <input id="rec-search" placeholder="Search cadet name, number, email…" class="col-span-full rounded border border-slate-200 px-2.5 py-1.5 focus:outline-none">
            <select id="rec-province" class="rounded border border-slate-200 px-2 py-1.5">
              <option value="">All Provinces</option>
              ${provinces.map((p) => `<option value="${p.id}" ${state.recipientProvince == p.id ? 'selected' : ''}>${esc(p.name_en)}</option>`).join('')}
            </select>
            <select id="rec-rank" class="rounded border border-slate-200 px-2 py-1.5">
              <option value="">All Ranks</option>
              ${ranks.map((r) => `<option value="${r.id}" ${state.recipientRank == r.id ? 'selected' : ''}>${esc(r.name_en)}</option>`).join('')}
            </select>
          </div>

          <div id="recipients-list-container" class="flex-1 overflow-y-auto max-h-[420px] border border-slate-100 rounded-lg divide-y divide-slate-100"></div>
          <div id="recipients-pagination" class="pt-2 text-xs"></div>
        </div>
      </div>`;

    // Hook up recipient search/filter events
    container.querySelector('#rec-search').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            state.recipientSearch = e.target.value.trim();
            state.recipientPage = 1;
            loadRecipientsList(container);
        }
    });
    container.querySelector('#rec-province').addEventListener('change', (e) => {
        state.recipientProvince = e.target.value;
        state.recipientPage = 1;
        loadRecipientsList(container);
    });
    container.querySelector('#rec-rank').addEventListener('change', (e) => {
        state.recipientRank = e.target.value;
        state.recipientPage = 1;
        loadRecipientsList(container);
    });

    container.querySelector('#btn-save-draft').addEventListener('click', () => submitCampaign(container, false));
    container.querySelector('#btn-send-now').addEventListener('click', () => submitCampaign(container, true));

    await loadRecipientsList(container);
}

async function loadRecipientsList(container) {
    const listEl = container.querySelector('#recipients-list-container');
    listEl.innerHTML = spinner();

    const data = await api('/email/recipients', {
        params: {
            search: state.recipientSearch,
            province_id: state.recipientProvince,
            rank_id: state.recipientRank,
            page: state.recipientPage,
        },
    });

    if (!data.items.length) {
        listEl.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">No matching cadets with email found.</div>';
        return;
    }

    listEl.innerHTML = data.items.map((cadet) => `
      <label class="flex items-center gap-3 p-2.5 hover:bg-slate-50 cursor-pointer text-xs">
        <input type="checkbox" data-cadet-id="${cadet.id}" ${state.selectedRecipientIds.has(cadet.id) ? 'checked' : ''} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        <div class="flex-1 truncate">
          <div class="font-medium text-slate-800 truncate">${esc(cadet.name)} <span class="text-slate-400 text-[10px]">(${esc(cadet.cadet_number)})</span></div>
          <div class="text-slate-500 truncate">${esc(cadet.email)} · ${esc(cadet.rank?.short_code || '')}</div>
        </div>
      </label>`).join('');

    listEl.querySelectorAll('[data-cadet-id]').forEach((cb) => {
        cb.addEventListener('change', (e) => {
            const id = Number(e.target.dataset.cadetId);
            if (e.target.checked) {
                state.selectedRecipientIds.add(id);
            } else {
                state.selectedRecipientIds.delete(id);
            }
            updateSelectedBadge(container);
        });
    });

    container.querySelector('#btn-select-all').onclick = () => {
        data.items.forEach((c) => state.selectedRecipientIds.add(c.id));
        listEl.querySelectorAll('[data-cadet-id]').forEach((cb) => (cb.checked = true));
        updateSelectedBadge(container);
    };

    const pageEl = container.querySelector('#recipients-pagination');
    pageEl.innerHTML = pagination(data.meta);
    bindPagination(pageEl, (next) => {
        state.recipientPage = next;
        loadRecipientsList(container);
    });
}

function updateSelectedBadge(container) {
    const badge = container.querySelector('#selected-badge');
    if (badge) {
        badge.textContent = `${state.selectedRecipientIds.size} cadets selected`;
    }
}

async function submitCampaign(container, andSend) {
    const title = container.querySelector('#cmp-title').value.trim();
    const subject = container.querySelector('#cmp-subject').value.trim();
    const body_html = container.querySelector('#cmp-body').value.trim();
    const sender_name = container.querySelector('#cmp-sender-name').value.trim();
    const reply_to = container.querySelector('#cmp-reply-to').value.trim();
    const manual_emails = container.querySelector('#cmp-manual-emails').value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

    if (!title || !subject || !body_html) {
        toast('Please fill out campaign title, subject, and body.', 'error');
        return;
    }

    if (state.selectedRecipientIds.size === 0 && manual_emails.length === 0) {
        toast('Please select at least one recipient or provide manual emails.', 'error');
        return;
    }

    try {
        const campaign = await api('/email/campaigns', {
            method: 'POST',
            body: {
                title,
                subject,
                body_html,
                sender_name: sender_name || null,
                reply_to: reply_to || null,
                cadet_ids: Array.from(state.selectedRecipientIds),
                manual_emails,
            },
        });

        if (andSend) {
            await api(`/email/campaigns/${campaign.id}/send`, { method: 'POST' });
            toast('Campaign created and queued for sending!', 'success');
        } else {
            toast('Campaign saved as draft.', 'success');
        }

        state.selectedRecipientIds.clear();
        activeTab = 'campaigns';
        render();
    } catch (e) {
        toast(e.message || 'Failed to create campaign.', 'error');
    }
}

// ---------------- SETTINGS TAB ----------------

async function renderSettings(container, can) {
    container.innerHTML = spinner();
    const settings = await api('/email/settings').catch(() => ({}));

    container.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl">
        <!-- SMTP Config Form -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
          <h3 class="text-base font-semibold text-slate-800 border-b border-slate-100 pb-3">SMTP Server Details</h3>

          <form id="smtp-settings-form" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Host *</label>
              <input name="host" value="${esc(settings.host || '')}" placeholder="smtp.gmail.com or mail.domain.com" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Port *</label>
                <input name="port" type="number" value="${settings.port || 587}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Encryption *</label>
                <select name="encryption" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                  <option value="tls" ${settings.encryption === 'tls' ? 'selected' : ''}>TLS</option>
                  <option value="ssl" ${settings.encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                  <option value="starttls" ${settings.encryption === 'starttls' ? 'selected' : ''}>STARTTLS</option>
                  <option value="none" ${settings.encryption === 'none' ? 'selected' : ''}>None</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Username</label>
              <input name="username" value="${esc(settings.username || '')}" placeholder="admin@nccaa.org.np" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Password</label>
              <input name="password" type="password" value="${settings.password_masked || ''}" placeholder="${settings.has_password ? '•••••••••••• (Leave blank to keep unchanged)' : 'Enter password'}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">From Sender Address *</label>
              <input name="from_email" type="email" value="${esc(settings.from_email || '')}" placeholder="noreply@nccaa.org.np" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">From Sender Name</label>
              <input name="from_name" value="${esc(settings.from_name || 'NCCAA HRMS')}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Default Reply-To</label>
              <input name="reply_to" type="email" value="${esc(settings.reply_to || '')}" placeholder="info@nccaa.org.np" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            ${can.manageSettings ? `<button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Save Configuration</button>` : ''}
          </form>
        </div>

        <!-- Test Connection Panel -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4 flex flex-col justify-between">
          <div class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800 border-b border-slate-100 pb-3">Test SMTP Connection</h3>
            <p class="text-sm text-slate-500">Send an instant test email to verify authentication, host connectivity, and SSL/TLS certificates.</p>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Test Recipient Email *</label>
              <input id="smtp-test-recipient" type="email" placeholder="your-email@example.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div id="test-result-box" class="hidden rounded-lg p-3 text-xs"></div>
          </div>

          ${can.testSmtp ? `<button id="btn-test-smtp" class="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">Send Test Email</button>` : ''}
        </div>
      </div>`;

    container.querySelector('#smtp-settings-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        try {
            await api('/email/settings', { method: 'PUT', body: Object.fromEntries(form) });
            toast('SMTP configuration saved securely.', 'success');
            renderSettings(container, can);
        } catch (err) {
            toast(err.message || 'Failed to save SMTP settings.', 'error');
        }
    });

    container.querySelector('#btn-test-smtp')?.addEventListener('click', async () => {
        const recipient = container.querySelector('#smtp-test-recipient').value.trim();
        const resultBox = container.querySelector('#test-result-box');
        if (!recipient) {
            toast('Please enter a recipient email for testing.', 'error');
            return;
        }

        resultBox.className = 'rounded-lg p-3 text-xs bg-slate-100 text-slate-600 block';
        resultBox.textContent = 'Testing connection and sending email...';

        try {
            const res = await api('/email/smtp/test', { method: 'POST', body: { recipient } });
            resultBox.className = 'rounded-lg p-3 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 block';
            resultBox.textContent = res.message || 'Connection successful!';
            toast('Test email sent successfully.', 'success');
        } catch (err) {
            resultBox.className = 'rounded-lg p-3 text-xs bg-rose-50 text-rose-700 border border-rose-200 block';
            resultBox.textContent = err.message || 'SMTP Connection failed.';
        }
    });
}

// ---------------- CAMPAIGN DETAIL MODAL ----------------

async function showCampaignModal(campaignId, can) {
    const campaign = await api(`/email/campaigns/${campaignId}`);
    const recipientsData = await api(`/email/campaigns/${campaignId}/recipients`).catch(() => ({ items: [] }));

    const modalHtml = `
      <div id="campaign-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-2xl space-y-5 max-h-[90vh] flex flex-col">
          <div class="flex items-start justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 class="text-lg font-bold text-slate-900">${esc(campaign.title)}</h3>
              <p class="text-xs text-slate-500">${esc(campaign.subject)} · Status: ${campaignBadge(campaign.status)}</p>
            </div>
            <button id="btn-close-modal" class="text-slate-400 hover:text-slate-600">✕</button>
          </div>

          <div class="grid grid-cols-3 gap-3 text-center text-xs">
            <div class="rounded-lg bg-slate-50 p-2.5 border border-slate-100">
              <div class="font-bold text-slate-800 text-base">${campaign.total_recipients}</div>
              <div class="text-slate-500">Total Recipients</div>
            </div>
            <div class="rounded-lg bg-emerald-50 p-2.5 border border-emerald-100 text-emerald-800">
              <div class="font-bold text-base">${campaign.sent_count}</div>
              <div>Delivered</div>
            </div>
            <div class="rounded-lg bg-rose-50 p-2.5 border border-rose-100 text-rose-800">
              <div class="font-bold text-base">${campaign.failed_count}</div>
              <div>Failed</div>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto space-y-4">
            <div>
              <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Message Preview</h4>
              <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50 text-xs text-slate-700 max-h-36 overflow-y-auto">
                ${campaign.body_html}
              </div>
            </div>

            <div>
              <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Recipient Delivery Logs</h4>
              <div class="border border-slate-200 rounded-lg overflow-hidden">
                <table class="min-w-full text-xs">
                  <thead class="bg-slate-50 text-left text-slate-500 font-medium">
                    <tr>
                      <th class="px-3 py-2">Recipient</th>
                      <th class="px-3 py-2">Status</th>
                      <th class="px-3 py-2">Attempts</th>
                      <th class="px-3 py-2">Details</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    ${recipientsData.items.map((r) => `
                      <tr>
                        <td class="px-3 py-2 font-medium text-slate-800">${esc(r.name || 'Cadet')} <span class="text-slate-400 font-normal">(${esc(r.email)})</span></td>
                        <td class="px-3 py-2">${recipientBadge(r.status)}</td>
                        <td class="px-3 py-2">${r.attempts}</td>
                        <td class="px-3 py-2 text-slate-500 truncate max-w-xs">${esc(r.error_message || (r.sent_at ? `Sent ${new Date(r.sent_at).toLocaleTimeString()}` : '—'))}</td>
                      </tr>`).join('')}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-3 border-t border-slate-100">
            <button id="btn-close-modal-bottom" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Close</button>
          </div>
        </div>
      </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modalEl = document.getElementById('campaign-modal');
    modalEl.querySelector('#btn-close-modal').onclick = () => modalEl.remove();
    modalEl.querySelector('#btn-close-modal-bottom').onclick = () => modalEl.remove();
}

function campaignBadge(status) {
    const map = {
        draft: 'bg-slate-100 text-slate-700',
        queued: 'bg-amber-50 text-amber-700 border border-amber-200',
        processing: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        completed: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        partial: 'bg-orange-50 text-orange-700 border border-orange-200',
        failed: 'bg-rose-50 text-rose-700 border border-rose-200',
        cancelled: 'bg-slate-100 text-slate-500',
    };
    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wide ${map[status] || map.draft}">${status}</span>`;
}

function recipientBadge(status) {
    const map = {
        pending: 'text-slate-500',
        sending: 'text-indigo-600 font-semibold',
        sent: 'text-emerald-600 font-semibold',
        failed: 'text-rose-600 font-semibold',
        cancelled: 'text-slate-400',
    };
    return `<span class="${map[status] || map.pending}">${status}</span>`;
}
