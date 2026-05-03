/* ═══════════════════════════════════════════════════════════════════════════
   ERP Base — Vanilla JS SPA
   No frameworks, no build step, no external libraries.
   ═══════════════════════════════════════════════════════════════════════════ */

// ── Toast ─────────────────────────────────────────────────────────────────

const toastContainer = (() => {
  const el = document.createElement('div');
  el.id = 'toast-container';
  document.body.appendChild(el);
  return el;
})();

function toast(msg, type = 'info') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
  t.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
  toastContainer.appendChild(t);
  setTimeout(() => t.remove(), 3100);
}

// ── Base path (works at root OR in a sub-folder like /erp_base_php/) ─────────
// Injected by index.php as window.__APP_BASE__ (e.g. "/" or "/erp_base_php/").
const BASE = window.__APP_BASE__ ?? '/';

// Prepend BASE to a logical path for use in fetch() or history.pushState().
function absPath(path) {
  // path always starts with /; BASE always ends with /
  return BASE.slice(0, -1) + path;
}

// Strip BASE prefix from location.pathname to get the logical route path.
function logicalPath() {
  const full = decodeURIComponent(location.pathname);
  if (BASE === '/') return full;
  const prefix = BASE.slice(0, -1); // e.g. /erp_base_php
  return full.startsWith(prefix) ? full.slice(prefix.length) || '/' : full;
}

// ── Auth token storage ─────────────────────────────────────────────────────

function getToken() { return localStorage.getItem('token'); }
function setToken(t) { t ? localStorage.setItem('token', t) : localStorage.removeItem('token'); }
function getUser() {
  try { return JSON.parse(localStorage.getItem('user') || 'null'); } catch { return null; }
}
function setUser(u) { u ? localStorage.setItem('user', JSON.stringify(u)) : localStorage.removeItem('user'); }
function logout() {
  setToken(null);
  setUser(null);
  navigate('/login');
}

// ── API client ─────────────────────────────────────────────────────────────

async function apiFetch(path, opts = {}) {
  const { json, headers: h = {}, ...rest } = opts;
  const headers = { ...h };
  if (json !== undefined) headers['Content-Type'] = 'application/json';
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(absPath(path), {
    ...rest,
    headers,
    body: json !== undefined ? JSON.stringify(json) : rest.body,
  });
  if (res.status === 204) return undefined;
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error ?? `http_${res.status}`);
  return data;
}

const api = {
  register:    (b)           => apiFetch('/auth/register',    { method: 'POST', json: b }),
  login:       (b)           => apiFetch('/auth/login',       { method: 'POST', json: b }),
  me:          ()            => apiFetch('/me'),
  changePassword: (b)        => apiFetch('/me/password',      { method: 'PUT',  json: b }),

  projects:    ()            => apiFetch('/projects'),
  createProject: (b)         => apiFetch('/projects',         { method: 'POST', json: b }),
  getProject:  (id)          => apiFetch(`/projects/${id}`),
  updateProject: (id, b)     => apiFetch(`/projects/${id}`,   { method: 'PATCH', json: b }),
  deleteProject: (id)        => apiFetch(`/projects/${id}`,   { method: 'DELETE' }),

  keys:        (pid)         => apiFetch(`/projects/${pid}/api-keys`),
  createKey:   (pid, b)      => apiFetch(`/projects/${pid}/api-keys`,          { method: 'POST', json: b }),
  revokeKey:   (pid, kid)    => apiFetch(`/projects/${pid}/api-keys/${kid}/revoke`, { method: 'POST' }),

  getData:     (pid, path)   => apiFetch(`/projects/${pid}/data${path ? `?path=${encodeURIComponent(path)}` : ''}`),
  putData:     (pid, b)      => apiFetch(`/projects/${pid}/data`,  { method: 'PUT',    json: b }),
  patchData:   (pid, b)      => apiFetch(`/projects/${pid}/data`,  { method: 'PATCH',  json: b }),
  deleteData:  (pid, path)   => apiFetch(`/projects/${pid}/data?path=${encodeURIComponent(path)}`, { method: 'DELETE' }),

  contacts:    (pid, role)   => apiFetch(`/projects/${pid}/contacts${role ? `?role=${encodeURIComponent(role)}` : ''}`),
  addContact:  (pid, b)      => apiFetch(`/projects/${pid}/contacts`,           { method: 'POST', json: b }),
  deleteContact: (pid, cid)  => apiFetch(`/projects/${pid}/contacts/${cid}`,    { method: 'DELETE' }),

  payments:    (pid)         => apiFetch(`/projects/${pid}/payments`),
  paymentIntent: (pid, b)    => apiFetch(`/projects/${pid}/payments/intent`,    { method: 'POST', json: b }),

  pdfJobs:     (pid)         => apiFetch(`/projects/${pid}/pdf/jobs`),
  createPdf:   (pid, b)      => apiFetch(`/projects/${pid}/pdf/jobs`,           { method: 'POST', json: b }),

  pushConfig:  (pid)         => apiFetch(`/projects/${pid}/push/config`),
  savePush:    (pid, b)      => apiFetch(`/projects/${pid}/push/config`,        { method: 'PUT',  json: b }),
  pushSubs:    (pid)         => apiFetch(`/projects/${pid}/push/subscriptions`),
  pushTest:    (pid, b)      => apiFetch(`/projects/${pid}/push/test`,          { method: 'POST', json: b }),

  dbConfig:    (pid)         => apiFetch(`/projects/${pid}/db/config`),
  saveDbConfig:(pid, b)      => apiFetch(`/projects/${pid}/db/config`,          { method: 'PUT',  json: b }),
  testDb:      (pid)         => apiFetch(`/projects/${pid}/db/test`,            { method: 'POST', json: {} }),

  emailConfig: (pid)         => apiFetch(`/projects/${pid}/email/config`),
  saveEmail:   (pid, b)      => apiFetch(`/projects/${pid}/email/config`,       { method: 'PUT',  json: b }),
  testEmail:   (pid, b)      => apiFetch(`/projects/${pid}/email/test`,         { method: 'POST', json: b ?? {} }),
  sendEmail:   (pid, b)      => apiFetch(`/projects/${pid}/email/send`,         { method: 'POST', json: b }),

  adminStats:    ()          => apiFetch('/admin/stats'),
  adminUsers:    ()          => apiFetch('/admin/users'),
  adminProjects: ()          => apiFetch('/admin/projects'),
};

// ── Router ─────────────────────────────────────────────────────────────────

const routes = [];

function addRoute(pattern, handler) {
  const keys = [];
  const regex = new RegExp(
    '^' + pattern.replace(/:([^/]+)/g, (_, k) => { keys.push(k); return '([^/]+)'; }) + '/?$'
  );
  routes.push({ regex, keys, handler });
}

function matchRoute(path) {
  for (const r of routes) {
    const m = path.match(r.regex);
    if (m) {
      const params = {};
      r.keys.forEach((k, i) => params[k] = decodeURIComponent(m[i + 1]));
      return { handler: r.handler, params };
    }
  }
  return null;
}

const appEl = document.getElementById('app');

async function navigate(path, replace = false) {
  const url = absPath(path);
  if (replace) history.replaceState(null, '', url);
  else         history.pushState(null, '', url);
  await render(path);
}

window.addEventListener('popstate', () => render(logicalPath()));

async function render(path) {
  const match = matchRoute(path);
  if (!match) {
    appEl.innerHTML = `<div class="auth-wrap"><div class="auth-card"><h1>404</h1><p>Page not found.</p><button class="btn btn-secondary mt-2" onclick="navigate('/dashboard')">Go home</button></div></div>`;
    return;
  }
  await match.handler(match.params);
}

// ── Helpers ────────────────────────────────────────────────────────────────

function el(tag, attrs = {}, ...children) {
  const e = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') e.className = v;
    else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
    else e.setAttribute(k, v);
  }
  for (const c of children) {
    if (c == null) continue;
    e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  }
  return e;
}

function fmtDate(s) {
  if (!s) return '—';
  return new Date(s).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function fmtDateTime(s) {
  if (!s) return '—';
  return new Date(s).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function loadingEl() {
  return el('div', { class: 'loading-center' }, el('div', { class: 'spinner' }));
}

function requireAuth() {
  if (!getToken()) { navigate('/login', true); return false; }
  return true;
}

// ── Modal ──────────────────────────────────────────────────────────────────

function openModal(content) {
  const overlay = el('div', { class: 'modal-overlay' });
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
  overlay.appendChild(content);
  document.body.appendChild(overlay);
  return { close: () => overlay.remove() };
}

function buildModal(title, bodyHtml, footerFn) {
  const m = el('div', { class: 'modal' });
  const hdr = el('div', { class: 'modal-header' });
  hdr.innerHTML = `<h2>${title}</h2>`;
  const closeBtn = el('button', { class: 'modal-close', type: 'button' }, '✕');
  hdr.appendChild(closeBtn);
  const body = el('div', { class: 'modal-body' });
  body.innerHTML = bodyHtml;
  const footer = el('div', { class: 'modal-footer' });
  m.append(hdr, body, footer);
  const { close } = openModal(m);
  closeBtn.onclick = close;
  if (footerFn) footerFn(footer, body, close);
  return { modal: m, close };
}

// ── Layout ─────────────────────────────────────────────────────────────────

function renderShell(activeNav, contentFn) {
  const user = getUser();
  const initial = (user?.email?.[0] ?? '?').toUpperCase();

  const shell = el('div', { class: 'app-shell' });

  const sidebar = el('aside', { class: 'sidebar' });
  const logo = el('div', { class: 'sidebar-logo' });
  logo.innerHTML = `<span>⚡</span> ERP Base`;
  const nav = el('nav', { class: 'sidebar-nav' });

  const navItems = [
    { href: '/dashboard', icon: '⊞', label: 'Projects' },
    ...(user?.role === 'ADMIN' ? [{ href: '/admin', icon: '⚙', label: 'Admin' }] : []),
    { href: '/settings', icon: '◎', label: 'Settings' },
  ];

  for (const item of navItems) {
    const a = el('a', {
      class: 'nav-link' + (activeNav === item.href ? ' active' : ''),
      href: item.href,
      onclick: (e) => { e.preventDefault(); navigate(item.href); },
    });
    a.innerHTML = `<span class="nav-icon">${item.icon}</span> ${item.label}`;
    nav.appendChild(a);
  }

  const sidebarFooter = el('div', { class: 'sidebar-footer' });
  sidebarFooter.innerHTML = `
    <div class="user-pill">
      <div class="avatar">${initial}</div>
      <div>
        <strong>${user?.email ?? ''}</strong>
        <div class="text-xs text-muted">${user?.role ?? ''}</div>
      </div>
    </div>`;
  const logoutBtn = el('button', { class: 'btn btn-secondary btn-sm w-full mt-1', onclick: logout }, 'Sign out');
  sidebarFooter.appendChild(logoutBtn);

  sidebar.append(logo, nav, sidebarFooter);

  const main = el('main', { class: 'main-content' });
  contentFn(main);

  shell.append(sidebar, main);
  appEl.innerHTML = '';
  appEl.appendChild(shell);
}

// ══════════════════════════════════════════════════════════════════════════
//  Pages
// ══════════════════════════════════════════════════════════════════════════

// ── Login ──────────────────────────────────────────────────────────────────

async function pageLogin() {
  if (getToken()) { navigate('/dashboard', true); return; }

  appEl.innerHTML = `
    <div class="auth-wrap">
      <div class="auth-card">
        <h1>Sign in</h1>
        <p>Welcome back to ERP Base</p>
        <div id="auth-error"></div>
        <form id="login-form">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" required placeholder="you@example.com" autofocus />
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" required placeholder="••••••••" />
          </div>
          <button class="btn btn-primary btn-full" type="submit" id="submit-btn">Sign in</button>
        </form>
        <p style="margin-top:1rem;text-align:center">
          No account? <a href="/register" id="reg-link">Register</a>
        </p>
      </div>
    </div>`;

  document.getElementById('reg-link').onclick = e => { e.preventDefault(); navigate('/register'); };

  document.getElementById('login-form').onsubmit = async e => {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const errEl = document.getElementById('auth-error');
    errEl.innerHTML = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    try {
      const data = await api.login({
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
      });
      setToken(data.token);
      setUser(data.user);
      navigate('/dashboard', true);
    } catch (err) {
      errEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      btn.disabled = false;
      btn.textContent = 'Sign in';
    }
  };
}

// ── Register ───────────────────────────────────────────────────────────────

async function pageRegister() {
  if (getToken()) { navigate('/dashboard', true); return; }

  appEl.innerHTML = `
    <div class="auth-wrap">
      <div class="auth-card">
        <h1>Create account</h1>
        <p>Get started with ERP Base</p>
        <div id="auth-error"></div>
        <form id="reg-form">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" required placeholder="you@example.com" autofocus />
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" required placeholder="Min. 8 characters" minlength="8" />
          </div>
          <button class="btn btn-primary btn-full" type="submit" id="submit-btn">Create account</button>
        </form>
        <p style="margin-top:1rem;text-align:center">
          Have an account? <a href="/login" id="login-link">Sign in</a>
        </p>
      </div>
    </div>`;

  document.getElementById('login-link').onclick = e => { e.preventDefault(); navigate('/login'); };

  document.getElementById('reg-form').onsubmit = async e => {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const errEl = document.getElementById('auth-error');
    errEl.innerHTML = '';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    try {
      const data = await api.register({
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
      });
      setToken(data.token);
      setUser(data.user);
      navigate('/dashboard', true);
    } catch (err) {
      errEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      btn.disabled = false;
      btn.textContent = 'Create account';
    }
  };
}

// ── Dashboard ──────────────────────────────────────────────────────────────

async function pageDashboard() {
  if (!requireAuth()) return;

  renderShell('/dashboard', (main) => {
    main.innerHTML = '';
    const header = el('div', { class: 'page-header' });
    header.innerHTML = `<div><h1>Projects</h1><p>Your workspaces</p></div>`;
    const newBtn = el('button', { class: 'btn btn-primary', onclick: openCreateProject }, '+ New project');
    header.appendChild(newBtn);
    main.appendChild(header);

    const grid = el('div', { class: 'card-grid' });
    main.appendChild(grid);
    grid.appendChild(loadingEl());

    api.projects().then(({ projects }) => {
      grid.innerHTML = '';
      if (!projects.length) {
        grid.innerHTML = `<div class="empty-state"><p>No projects yet. Create your first one.</p></div>`;
        return;
      }
      for (const p of projects) {
        const card = el('div', { class: 'project-card', onclick: () => navigate(`/projects/${p.id}`) });
        card.innerHTML = `
          <h3>${p.name}</h3>
          <div class="slug">${p.slug}</div>
          <div class="meta">${p._count?.apiKeys ?? 0} API keys · created ${fmtDate(p.createdAt)}</div>`;
        grid.appendChild(card);
      }
    }).catch(err => {
      grid.innerHTML = `<div class="error-banner">${err.message}</div>`;
    });
  });

  function openCreateProject() {
    buildModal('New project',
      `<div class="form-group"><label>Name</label><input type="text" id="proj-name" placeholder="My Project" required /></div>
       <div class="form-group"><label>Slug</label><input type="text" id="proj-slug" placeholder="my-project" required /></div>`,
      (footer, body, close) => {
        const nameEl = body.querySelector('#proj-name');
        nameEl.addEventListener('input', () => {
          const slug = body.querySelector('#proj-slug');
          if (!slug._touched) slug.value = nameEl.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
        });
        body.querySelector('#proj-slug').addEventListener('input', function() { this._touched = true; });

        const cancelBtn = el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel');
        const saveBtn = el('button', { class: 'btn btn-primary', type: 'button' }, 'Create');
        saveBtn.onclick = async () => {
          const name = body.querySelector('#proj-name').value.trim();
          const slug = body.querySelector('#proj-slug').value.trim();
          if (!name || !slug) return;
          saveBtn.disabled = true;
          saveBtn.innerHTML = '<span class="spinner"></span>';
          try {
            await api.createProject({ name, slug });
            close();
            toast('Project created', 'success');
            navigate('/dashboard');
          } catch (err) {
            toast(err.message, 'error');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Create';
          }
        };
        footer.append(cancelBtn, saveBtn);
      });
  }
}

// ── Project detail ─────────────────────────────────────────────────────────

async function pageProject({ id }) {
  if (!requireAuth()) return;

  renderShell('/dashboard', (main) => {
    main.innerHTML = '';
    main.appendChild(loadingEl());

    api.getProject(id).then(({ project: p }) => {
      main.innerHTML = '';

      const back = el('button', { class: 'back-link', onclick: () => navigate('/dashboard') }, '← Projects');
      const header = el('div', { class: 'page-header' });
      header.innerHTML = `<div><h1>${p.name}</h1><p class="text-mono text-sm">${p.slug}</p></div>`;

      const actionsDiv = el('div', { class: 'flex gap-2' });
      const renameBtn = el('button', { class: 'btn btn-secondary btn-sm', onclick: () => openRename(p) }, 'Rename');
      const deleteBtn = el('button', { class: 'btn btn-danger btn-sm', onclick: () => openDelete(p) }, 'Delete');
      actionsDiv.append(renameBtn, deleteBtn);
      header.appendChild(actionsDiv);

      const tabsBar = el('div', { class: 'tabs-bar' });
      const tabContent = el('div', { id: 'tab-content' });

      const tabs = [
        { id: 'api-keys',    label: 'API Keys' },
        { id: 'data',        label: 'Tree Data' },
        { id: 'contacts',    label: 'Contacts' },
        { id: 'payments',    label: 'Payments' },
        { id: 'pdf',         label: 'PDF Jobs' },
        { id: 'push',        label: 'Push' },
        { id: 'datastore',   label: 'DataStore' },
        { id: 'email',       label: 'Email' },
      ];

      let activeTab = 'api-keys';
      const tabBtns = {};

      function switchTab(tid) {
        activeTab = tid;
        for (const [k, btn] of Object.entries(tabBtns)) {
          btn.className = 'tab-btn' + (k === tid ? ' active' : '');
        }
        renderTab(tid);
      }

      for (const t of tabs) {
        const btn = el('button', { class: 'tab-btn' + (t.id === activeTab ? ' active' : ''), onclick: () => switchTab(t.id) }, t.label);
        tabBtns[t.id] = btn;
        tabsBar.appendChild(btn);
      }

      main.append(back, header, tabsBar, tabContent);
      renderTab(activeTab);

      function renderTab(tid) {
        tabContent.innerHTML = '';
        tabContent.appendChild(loadingEl());
        const renderers = {
          'api-keys':  () => renderApiKeys(tabContent, p.id),
          'data':      () => renderTreeData(tabContent, p.id),
          'contacts':  () => renderContacts(tabContent, p.id),
          'payments':  () => renderPayments(tabContent, p.id),
          'pdf':       () => renderPdfJobs(tabContent, p.id),
          'push':      () => renderPush(tabContent, p.id),
          'datastore': () => renderDataStore(tabContent, p.id),
          'email':     () => renderEmail(tabContent, p.id),
        };
        renderers[tid]?.();
      }

    }).catch(() => { main.innerHTML = `<div class="error-banner">Failed to load project</div>`; });
  });

  function openRename(p) {
    buildModal('Rename project',
      `<div class="form-group"><label>Name</label><input type="text" id="proj-name" value="${p.name}" required /></div>`,
      (footer, body, close) => {
        footer.append(
          el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
          (() => {
            const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Save');
            s.onclick = async () => {
              const name = body.querySelector('#proj-name').value.trim();
              if (!name) return;
              s.disabled = true;
              try {
                await api.updateProject(p.id, { name });
                close();
                toast('Project renamed', 'success');
                navigate(`/projects/${p.id}`);
              } catch (err) { toast(err.message, 'error'); s.disabled = false; }
            };
            return s;
          })()
        );
      });
  }

  function openDelete(p) {
    buildModal('Delete project',
      `<p>Are you sure you want to delete <strong>${p.name}</strong>? This cannot be undone.</p>`,
      (footer, _body, close) => {
        footer.append(
          el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
          (() => {
            const d = el('button', { class: 'btn btn-danger', type: 'button' }, 'Delete');
            d.onclick = async () => {
              d.disabled = true;
              try {
                await api.deleteProject(p.id);
                close();
                toast('Project deleted', 'success');
                navigate('/dashboard');
              } catch (err) { toast(err.message, 'error'); d.disabled = false; }
            };
            return d;
          })()
        );
      });
  }
}

// ── Tab: API Keys ──────────────────────────────────────────────────────────

async function renderApiKeys(container, pid) {
  try {
    const { keys } = await api.keys(pid);
    container.innerHTML = '';
    const hdr = el('div', { class: 'section-header' });
    hdr.innerHTML = `<h2>API Keys</h2>`;
    const addBtn = el('button', { class: 'btn btn-primary btn-sm', onclick: () => openCreateKey(pid, container) }, '+ New key');
    hdr.appendChild(addBtn);
    container.appendChild(hdr);

    if (!keys.length) {
      container.innerHTML += `<div class="empty-state"><p>No API keys yet.</p></div>`;
      return;
    }
    const wrap = el('div', { class: 'table-wrap' });
    wrap.innerHTML = `
      <table>
        <thead><tr>
          <th>Name</th><th>Prefix</th><th>Scopes</th><th>Created</th><th>Status</th><th></th>
        </tr></thead>
        <tbody id="keys-tbody"></tbody>
      </table>`;
    container.appendChild(wrap);
    const tbody = wrap.querySelector('#keys-tbody');
    for (const k of keys) {
      const tr = el('tr');
      tr.innerHTML = `
        <td><strong>${k.name}</strong></td>
        <td><span class="mono">${k.prefix}…${k.last4}</span></td>
        <td>${k.scopes.map(s => `<span class="badge badge-blue">${s}</span>`).join(' ')}</td>
        <td>${fmtDate(k.createdAt)}</td>
        <td>${k.revokedAt
          ? `<span class="badge badge-red">Revoked</span>`
          : `<span class="badge badge-green">Active</span>`}</td>
        <td></td>`;
      if (!k.revokedAt) {
        const rev = el('button', { class: 'btn btn-danger btn-sm' }, 'Revoke');
        rev.onclick = async () => {
          if (!confirm(`Revoke key "${k.name}"?`)) return;
          rev.disabled = true;
          try {
            await api.revokeKey(pid, k.id);
            toast('Key revoked', 'success');
            renderApiKeys(container, pid);
          } catch (err) { toast(err.message, 'error'); rev.disabled = false; }
        };
        tr.lastElementChild.appendChild(rev);
      }
      tbody.appendChild(tr);
    }
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function openCreateKey(pid, container) {
  buildModal('New API key',
    `<div class="form-group"><label>Name</label><input type="text" id="key-name" placeholder="My key" required /></div>
     <div class="form-group"><label>Scopes</label>
       <div class="checkbox-group">
         <label class="checkbox-label"><input type="checkbox" value="read" checked /> read</label>
         <label class="checkbox-label"><input type="checkbox" value="write" /> write</label>
       </div>
     </div>`,
    (footer, body, close) => {
      footer.append(
        el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
        (() => {
          const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Create');
          s.onclick = async () => {
            const name = body.querySelector('#key-name').value.trim();
            const scopes = [...body.querySelectorAll('input[type="checkbox"]:checked')].map(c => c.value);
            if (!name) return;
            s.disabled = true;
            try {
              const { fullKey } = await api.createKey(pid, { name, scopes });
              close();
              buildModal('Key created',
                `<p>Copy this key now — it will not be shown again.</p>
                 <div class="key-reveal">${fullKey}</div>`,
                (f2, _b2, c2) => {
                  const copyBtn = el('button', { class: 'btn btn-secondary' }, 'Copy');
                  copyBtn.onclick = () => { navigator.clipboard.writeText(fullKey); toast('Copied', 'success'); };
                  f2.append(copyBtn, el('button', { class: 'btn btn-primary', onclick: c2 }, 'Done'));
                });
              renderApiKeys(container, pid);
            } catch (err) { toast(err.message, 'error'); s.disabled = false; s.textContent = 'Create'; }
          };
          return s;
        })()
      );
    });
}

// ── Tab: Tree Data ─────────────────────────────────────────────────────────

async function renderTreeData(container, pid) {
  try {
    const { data } = await api.getData(pid);
    container.innerHTML = '';
    const hdr = el('div', { class: 'section-header' });
    hdr.innerHTML = `<h2>Tree Data</h2>`;
    const editBtn = el('button', { class: 'btn btn-secondary btn-sm', onclick: () => openEditData(pid, data, container) }, 'Edit JSON');
    hdr.appendChild(editBtn);
    container.appendChild(hdr);

    const pre = el('pre', { class: 'code-block' });
    pre.textContent = JSON.stringify(data, null, 2);
    container.appendChild(pre);
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function openEditData(pid, current, container) {
  const m = el('div', { class: 'modal modal-wide' });
  const hdr = el('div', { class: 'modal-header' });
  hdr.innerHTML = `<h2>Edit JSON data</h2>`;
  const closeBtn = el('button', { class: 'modal-close' }, '✕');
  hdr.appendChild(closeBtn);

  const ta = el('textarea', { style: 'font-family:var(--mono);font-size:.8rem;min-height:320px;' });
  ta.value = JSON.stringify(current, null, 2);

  const errBanner = el('div');
  const footer = el('div', { class: 'modal-footer' });

  m.append(hdr, errBanner, ta, footer);
  const { close } = openModal(m);
  closeBtn.onclick = close;

  footer.append(
    el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
    (() => {
      const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Save');
      s.onclick = async () => {
        errBanner.innerHTML = '';
        let parsed;
        try { parsed = JSON.parse(ta.value); } catch { errBanner.innerHTML = `<div class="error-banner">Invalid JSON</div>`; return; }
        s.disabled = true;
        try {
          await api.putData(pid, parsed);
          close();
          toast('Data saved', 'success');
          renderTreeData(container, pid);
        } catch (err) { errBanner.innerHTML = `<div class="error-banner">${err.message}</div>`; s.disabled = false; }
      };
      return s;
    })()
  );
}

// ── Tab: Contacts ──────────────────────────────────────────────────────────

async function renderContacts(container, pid) {
  try {
    const { contacts } = await api.contacts(pid);
    container.innerHTML = '';
    const hdr = el('div', { class: 'section-header' });
    hdr.innerHTML = `<h2>Contacts</h2>`;
    const addBtn = el('button', { class: 'btn btn-primary btn-sm', onclick: () => openAddContact(pid, container) }, '+ Add contact');
    hdr.appendChild(addBtn);
    container.appendChild(hdr);

    if (!contacts.length) {
      container.innerHTML += `<div class="empty-state"><p>No contacts yet.</p></div>`;
      return;
    }
    const wrap = el('div', { class: 'table-wrap' });
    wrap.innerHTML = `
      <table>
        <thead><tr><th>Email</th><th>Name</th><th>Added</th><th></th></tr></thead>
        <tbody id="contacts-tbody"></tbody>
      </table>`;
    container.appendChild(wrap);
    const tbody = wrap.querySelector('#contacts-tbody');
    for (const c of contacts) {
      const tr = el('tr');
      tr.innerHTML = `<td><strong>${c.email}</strong></td><td>${c.name ?? '—'}</td><td>${fmtDate(c.createdAt)}</td><td></td>`;
      const del = el('button', { class: 'btn btn-danger btn-sm' }, 'Remove');
      del.onclick = async () => {
        if (!confirm(`Remove ${c.email}?`)) return;
        del.disabled = true;
        try {
          await api.deleteContact(pid, c.id);
          toast('Contact removed', 'success');
          renderContacts(container, pid);
        } catch (err) { toast(err.message, 'error'); del.disabled = false; }
      };
      tr.lastElementChild.appendChild(del);
      tbody.appendChild(tr);
    }
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function openAddContact(pid, container) {
  buildModal('Add contact',
    `<div class="form-group"><label>Email</label><input type="email" id="c-email" required /></div>
     <div class="form-group"><label>Name (optional)</label><input type="text" id="c-name" /></div>
     <div class="form-group"><label>Role (optional)</label><input type="text" id="c-role" placeholder="e.g. customer" /></div>`,
    (footer, body, close) => {
      footer.append(
        el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
        (() => {
          const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Add');
          s.onclick = async () => {
            const email = body.querySelector('#c-email').value.trim();
            if (!email) return;
            s.disabled = true;
            try {
              await api.addContact(pid, {
                email,
                name: body.querySelector('#c-name').value.trim() || undefined,
                role: body.querySelector('#c-role').value.trim() || undefined,
              });
              close();
              toast('Contact added', 'success');
              renderContacts(container, pid);
            } catch (err) { toast(err.message, 'error'); s.disabled = false; }
          };
          return s;
        })()
      );
    });
}

// ── Tab: Payments ──────────────────────────────────────────────────────────

async function renderPayments(container, pid) {
  try {
    const { payments } = await api.payments(pid);
    container.innerHTML = '';
    const hdr = el('div', { class: 'section-header' });
    hdr.innerHTML = `<h2>Payments</h2>`;
    const addBtn = el('button', { class: 'btn btn-primary btn-sm', onclick: () => openCreateIntent(pid, container) }, '+ Create intent');
    hdr.appendChild(addBtn);
    container.appendChild(hdr);

    if (!payments.length) {
      container.innerHTML += `<div class="empty-state"><p>No payments yet.</p></div>`;
      return;
    }
    const wrap = el('div', { class: 'table-wrap' });
    wrap.innerHTML = `
      <table>
        <thead><tr><th>ID</th><th>Amount</th><th>Currency</th><th>Status</th><th>Created</th></tr></thead>
        <tbody id="pay-tbody"></tbody>
      </table>`;
    container.appendChild(wrap);
    const tbody = wrap.querySelector('#pay-tbody');
    for (const p of payments) {
      const tr = el('tr');
      const badgeCls = p.status === 'succeeded' ? 'badge-green' : p.status === 'requires_payment_method' ? 'badge-yellow' : 'badge-gray';
      tr.innerHTML = `
        <td><span class="mono text-xs">${p.id.slice(0,8)}…</span></td>
        <td><strong>${(p.amountCents / 100).toFixed(2)}</strong></td>
        <td>${p.currency.toUpperCase()}</td>
        <td><span class="badge ${badgeCls}">${p.status}</span></td>
        <td>${fmtDate(p.createdAt)}</td>`;
      tbody.appendChild(tr);
    }
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function openCreateIntent(pid, container) {
  buildModal('Create payment intent',
    `<div class="form-group"><label>Amount (cents)</label><input type="number" id="pi-amount" min="1" value="1000" required /></div>
     <div class="form-group"><label>Currency</label>
       <select id="pi-currency">
         <option value="usd">USD</option><option value="eur">EUR</option><option value="gbp">GBP</option>
       </select>
     </div>`,
    (footer, body, close) => {
      footer.append(
        el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
        (() => {
          const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Create');
          s.onclick = async () => {
            s.disabled = true;
            try {
              await api.paymentIntent(pid, {
                amountCents: parseInt(body.querySelector('#pi-amount').value),
                currency: body.querySelector('#pi-currency').value,
              });
              close();
              toast('Payment intent created', 'success');
              renderPayments(container, pid);
            } catch (err) { toast(err.message, 'error'); s.disabled = false; }
          };
          return s;
        })()
      );
    });
}

// ── Tab: PDF Jobs ──────────────────────────────────────────────────────────

async function renderPdfJobs(container, pid) {
  try {
    const { jobs } = await api.pdfJobs(pid);
    container.innerHTML = '';
    const hdr = el('div', { class: 'section-header' });
    hdr.innerHTML = `<h2>PDF Jobs</h2>`;
    const addBtn = el('button', { class: 'btn btn-primary btn-sm', onclick: () => openCreatePdf(pid, container) }, '+ New job');
    hdr.appendChild(addBtn);
    container.appendChild(hdr);

    if (!jobs.length) {
      container.innerHTML += `<div class="empty-state"><p>No PDF jobs yet.</p></div>`;
      return;
    }
    const wrap = el('div', { class: 'table-wrap' });
    wrap.innerHTML = `
      <table>
        <thead><tr><th>ID</th><th>Template</th><th>Status</th><th>Output</th><th>Created</th></tr></thead>
        <tbody id="pdf-tbody"></tbody>
      </table>`;
    container.appendChild(wrap);
    const tbody = wrap.querySelector('#pdf-tbody');
    for (const j of jobs) {
      const tr = el('tr');
      const statusCls = j.status === 'completed' ? 'badge-green' : j.status === 'failed' ? 'badge-red' : 'badge-yellow';
      tr.innerHTML = `
        <td><span class="mono text-xs">${j.id.slice(0,8)}…</span></td>
        <td>${j.templateId ?? '—'}</td>
        <td><span class="badge ${statusCls}">${j.status}</span></td>
        <td>${j.outputUrl ? `<a href="${j.outputUrl}" target="_blank">Download</a>` : '—'}</td>
        <td>${fmtDate(j.createdAt)}</td>`;
      tbody.appendChild(tr);
    }
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function openCreatePdf(pid, container) {
  buildModal('New PDF job',
    `<div class="form-group"><label>Title (optional)</label><input type="text" id="pdf-title" /></div>
     <div class="form-group"><label>Template ID (optional)</label><input type="text" id="pdf-tpl" /></div>
     <div class="form-group"><label>Input JSON (optional)</label><textarea id="pdf-input" placeholder='{"key":"value"}'></textarea></div>`,
    (footer, body, close) => {
      footer.append(
        el('button', { class: 'btn btn-secondary', type: 'button', onclick: close }, 'Cancel'),
        (() => {
          const s = el('button', { class: 'btn btn-primary', type: 'button' }, 'Create');
          s.onclick = async () => {
            s.disabled = true;
            let input;
            const raw = body.querySelector('#pdf-input').value.trim();
            if (raw) {
              try { input = JSON.parse(raw); } catch { toast('Invalid input JSON', 'error'); s.disabled = false; return; }
            }
            try {
              await api.createPdf(pid, {
                title: body.querySelector('#pdf-title').value.trim() || undefined,
                templateId: body.querySelector('#pdf-tpl').value.trim() || undefined,
                input,
              });
              close();
              toast('PDF job created', 'success');
              renderPdfJobs(container, pid);
            } catch (err) { toast(err.message, 'error'); s.disabled = false; }
          };
          return s;
        })()
      );
    });
}

// ── Tab: Push ──────────────────────────────────────────────────────────────

async function renderPush(container, pid) {
  try {
    const { config, vapidPublicKey } = await api.pushConfig(pid);
    const { subscriptions } = await api.pushSubs(pid);
    container.innerHTML = '';

    const hdr = el('div', { class: 'section-header mb-2' });
    hdr.innerHTML = `<h2>Push Notifications</h2>`;
    container.appendChild(hdr);

    // Config card
    const card = el('div', { class: 'card mb-3' });
    card.innerHTML = `
      <h3 style="margin-bottom:.75rem">Provider config</h3>
      ${vapidPublicKey ? `<p class="text-sm mb-2">VAPID public key: <code class="mono">${vapidPublicKey.slice(0,20)}…</code></p>` : ''}
      <form id="push-form">
        <div class="form-row">
          <div class="form-group">
            <label>Provider</label>
            <select id="push-provider">
              <option value="vapid" ${config?.provider === 'vapid' ? 'selected' : ''}>VAPID (Web Push)</option>
              <option value="fcm"   ${config?.provider === 'fcm'   ? 'selected' : ''}>FCM</option>
            </select>
          </div>
          <div class="form-group">
            <label>Server secret</label>
            <input type="password" id="push-secret" placeholder="${config?.hasSecret ? '(saved)' : 'Enter secret'}" />
          </div>
        </div>
        <div id="push-msg"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save config</button>
      </form>`;
    container.appendChild(card);

    card.querySelector('#push-form').onsubmit = async e => {
      e.preventDefault();
      const btn = card.querySelector('[type="submit"]');
      btn.disabled = true;
      try {
        await api.savePush(pid, {
          provider: card.querySelector('#push-provider').value,
          serverSecret: card.querySelector('#push-secret').value,
        });
        toast('Push config saved', 'success');
      } catch (err) { toast(err.message, 'error'); }
      btn.disabled = false;
    };

    // Test card
    const testCard = el('div', { class: 'card mb-3' });
    testCard.innerHTML = `
      <h3 style="margin-bottom:.75rem">Send test push</h3>
      <div class="form-group"><label>Title</label><input type="text" id="push-title" placeholder="Test" /></div>
      <div class="form-group"><label>Message</label><input type="text" id="push-msg-txt" placeholder="Hello from ERP Base" /></div>
      <div id="push-test-result"></div>
      <button class="btn btn-secondary btn-sm" id="push-test-btn">Send to all subscribers</button>`;
    container.appendChild(testCard);

    testCard.querySelector('#push-test-btn').onclick = async () => {
      const btn = testCard.querySelector('#push-test-btn');
      btn.disabled = true;
      try {
        const res = await api.pushTest(pid, {
          title: testCard.querySelector('#push-title').value || undefined,
          message: testCard.querySelector('#push-msg-txt').value || undefined,
        });
        testCard.querySelector('#push-test-result').innerHTML =
          `<div class="success-banner">Sent: ${res.sent ?? 0}/${res.total ?? 0}</div>`;
      } catch (err) {
        testCard.querySelector('#push-test-result').innerHTML = `<div class="error-banner">${err.message}</div>`;
      }
      btn.disabled = false;
    };

    // Subscriptions
    const subHdr = el('div', { class: 'section-header' });
    subHdr.innerHTML = `<h2>Subscriptions <span class="badge badge-blue">${subscriptions.length}</span></h2>`;
    container.appendChild(subHdr);

    if (subscriptions.length) {
      const wrap = el('div', { class: 'table-wrap' });
      wrap.innerHTML = `
        <table>
          <thead><tr><th>Endpoint</th><th>User</th><th>Created</th></tr></thead>
          <tbody></tbody>
        </table>`;
      const tbody = wrap.querySelector('tbody');
      for (const s of subscriptions) {
        const tr = el('tr');
        tr.innerHTML = `
          <td class="mono text-xs" style="max-width:320px;overflow:hidden;text-overflow:ellipsis">${s.endpoint}</td>
          <td>${s.userId ?? '—'}</td>
          <td>${fmtDate(s.createdAt)}</td>`;
        tbody.appendChild(tr);
      }
      container.appendChild(wrap);
    } else {
      container.innerHTML += `<div class="empty-state"><p>No subscribers yet.</p></div>`;
    }
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

// ── Tab: DataStore ─────────────────────────────────────────────────────────

async function renderDataStore(container, pid) {
  try {
    const res = await api.dbConfig(pid);
    container.innerHTML = '';

    const hdr = el('div', { class: 'section-header mb-2' });
    hdr.innerHTML = `<h2>DataStore</h2>`;
    container.appendChild(hdr);

    const statusBadge = res.connected
      ? `<span class="badge badge-green">Connected</span>`
      : `<span class="badge badge-red">Disconnected</span>`;

    const card = el('div', { class: 'card' });
    card.innerHTML = `
      <div class="flex items-center gap-2 mb-3">
        <h3>Configuration</h3> ${statusBadge}
      </div>
      <form id="db-form">
        <div class="form-group">
          <label>Store type</label>
          <select id="db-type">
            <option value="sql_json">SQL JSON (built-in)</option>
            <option value="file">File (JSON)</option>
            <option value="lowdb">LowDB</option>
            <option value="mongo">MongoDB</option>
            <option value="postgres">PostgreSQL</option>
            <option value="mysql">MySQL</option>
            <option value="sqlite_file">SQLite file</option>
          </select>
        </div>
        <div id="db-extra"></div>
        <div id="db-test-result"></div>
        <div class="flex gap-2">
          <button class="btn btn-secondary btn-sm" type="button" id="db-test-btn">Test connection</button>
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </div>
      </form>`;
    container.appendChild(card);

    const typeEl = card.querySelector('#db-type');
    typeEl.value = res.storeType;
    renderDbExtra(typeEl.value, card.querySelector('#db-extra'), res.maskedConfig);
    typeEl.onchange = () => renderDbExtra(typeEl.value, card.querySelector('#db-extra'), {});

    card.querySelector('#db-form').onsubmit = async e => {
      e.preventDefault();
      const btn = card.querySelector('[type="submit"]');
      btn.disabled = true;
      try {
        await api.saveDbConfig(pid, buildDbBody(typeEl.value, card));
        toast('DataStore config saved', 'success');
      } catch (err) { toast(err.message, 'error'); }
      btn.disabled = false;
    };

    card.querySelector('#db-test-btn').onclick = async () => {
      const btn = card.querySelector('#db-test-btn');
      const resultEl = card.querySelector('#db-test-result');
      btn.disabled = true;
      try {
        const r = await api.testDb(pid);
        resultEl.innerHTML = `<div class="success-banner">Connected · ${r.latencyMs}ms</div>`;
      } catch (err) {
        resultEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      }
      btn.disabled = false;
    };
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

function renderDbExtra(type, container, current) {
  const mc = current ?? {};
  container.innerHTML = '';
  if (type === 'sql_json') return;
  if (type === 'file' || type === 'lowdb') {
    container.innerHTML = `<div class="form-group"><label>Path</label><input type="text" id="db-path" value="${mc.path ?? ''}" placeholder="./data.json" /></div>`;
  } else if (type === 'sqlite_file') {
    container.innerHTML = `<div class="form-group"><label>Path</label><input type="text" id="db-path" value="${mc.path ?? ''}" placeholder="./data.sqlite" /></div>`;
  } else if (type === 'mongo') {
    container.innerHTML = `<div class="form-group"><label>URI</label><input type="text" id="db-uri" value="" placeholder="mongodb://localhost:27017/mydb" /></div>`;
  } else if (type === 'postgres' || type === 'mysql') {
    container.innerHTML = `
      <div class="form-row">
        <div class="form-group"><label>Host</label><input type="text" id="db-host" value="${mc.host ?? ''}" /></div>
        <div class="form-group"><label>Port</label><input type="number" id="db-port" value="${mc.port ?? (type === 'postgres' ? 5432 : 3306)}" /></div>
      </div>
      <div class="form-group"><label>Database</label><input type="text" id="db-name" value="${mc.database ?? ''}" /></div>
      <div class="form-row">
        <div class="form-group"><label>User</label><input type="text" id="db-user" value="${mc.user ?? ''}" /></div>
        <div class="form-group"><label>Password</label><input type="password" id="db-pass" /></div>
      </div>`;
  }
}

function buildDbBody(type, card) {
  const g = id => card.querySelector(`#${id}`)?.value ?? '';
  if (type === 'sql_json') return { type };
  if (type === 'file' || type === 'lowdb') return { type, path: g('db-path') || undefined };
  if (type === 'sqlite_file') return { type, path: g('db-path') };
  if (type === 'mongo') return { type, uri: g('db-uri') };
  if (type === 'postgres' || type === 'mysql') return {
    type,
    host: g('db-host'), port: parseInt(g('db-port')) || (type === 'postgres' ? 5432 : 3306),
    database: g('db-name'), user: g('db-user'), password: g('db-pass'),
  };
  return { type };
}

// ── Tab: Email ─────────────────────────────────────────────────────────────

async function renderEmail(container, pid) {
  try {
    const cfg = await api.emailConfig(pid);
    container.innerHTML = '';

    const hdr = el('div', { class: 'section-header mb-2' });
    hdr.innerHTML = `<h2>Email Config</h2>`;
    container.appendChild(hdr);

    const card = el('div', { class: 'card mb-3' });
    card.innerHTML = `
      <h3 style="margin-bottom:.75rem">SMTP settings</h3>
      <form id="email-form">
        <div class="form-row">
          <div class="form-group"><label>Host</label><input type="text" id="em-host" value="${cfg.host ?? ''}" required /></div>
          <div class="form-group"><label>Port</label><input type="number" id="em-port" value="${cfg.port ?? 587}" required /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>From address</label><input type="email" id="em-from" value="${cfg.fromAddress ?? ''}" required /></div>
          <div class="form-group"><label>From name</label><input type="text" id="em-name" value="${cfg.fromName ?? ''}" /></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Username</label><input type="text" id="em-user" placeholder="${cfg.hasCredentials ? '(saved)' : ''}" /></div>
          <div class="form-group"><label>Password</label><input type="password" id="em-pass" placeholder="${cfg.hasCredentials ? '(saved)' : ''}" /></div>
        </div>
        <div class="form-group">
          <label class="checkbox-label"><input type="checkbox" id="em-secure" ${cfg.secure ? 'checked' : ''} /> TLS (port 465)</label>
        </div>
        <div id="email-msg"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save</button>
      </form>`;
    container.appendChild(card);

    card.querySelector('#email-form').onsubmit = async e => {
      e.preventDefault();
      const btn = card.querySelector('[type="submit"]');
      btn.disabled = true;
      try {
        await api.saveEmail(pid, {
          host:        card.querySelector('#em-host').value,
          port:        parseInt(card.querySelector('#em-port').value),
          secure:      card.querySelector('#em-secure').checked,
          fromAddress: card.querySelector('#em-from').value,
          fromName:    card.querySelector('#em-name').value || undefined,
          user:        card.querySelector('#em-user').value || undefined,
          password:    card.querySelector('#em-pass').value || undefined,
        });
        toast('Email config saved', 'success');
      } catch (err) { toast(err.message, 'error'); }
      btn.disabled = false;
    };

    // Test
    const testCard = el('div', { class: 'card mb-3' });
    testCard.innerHTML = `
      <h3 style="margin-bottom:.75rem">Test connection</h3>
      <div class="form-group"><label>Send test to (optional)</label><input type="email" id="test-to" placeholder="you@example.com" /></div>
      <div id="test-result"></div>
      <button class="btn btn-secondary btn-sm" id="test-btn">Send test</button>`;
    container.appendChild(testCard);

    testCard.querySelector('#test-btn').onclick = async () => {
      const btn = testCard.querySelector('#test-btn');
      const resultEl = testCard.querySelector('#test-result');
      btn.disabled = true;
      try {
        const r = await api.testEmail(pid, { to: testCard.querySelector('#test-to').value || undefined });
        resultEl.innerHTML = `<div class="success-banner">OK · ${r.latencyMs ?? 0}ms</div>`;
      } catch (err) {
        resultEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      }
      btn.disabled = false;
    };

    // Send email
    const sendCard = el('div', { class: 'card' });
    sendCard.innerHTML = `
      <h3 style="margin-bottom:.75rem">Send email</h3>
      <form id="send-form">
        <div class="form-group"><label>To</label><input type="email" id="send-to" required /></div>
        <div class="form-group"><label>Subject</label><input type="text" id="send-subject" required /></div>
        <div class="form-group"><label>HTML body</label><textarea id="send-html" required placeholder="<p>Hello!</p>"></textarea></div>
        <div id="send-result"></div>
        <button class="btn btn-primary btn-sm" type="submit">Send</button>
      </form>`;
    container.appendChild(sendCard);

    sendCard.querySelector('#send-form').onsubmit = async e => {
      e.preventDefault();
      const btn = sendCard.querySelector('[type="submit"]');
      const resultEl = sendCard.querySelector('#send-result');
      btn.disabled = true;
      try {
        await api.sendEmail(pid, {
          to:      sendCard.querySelector('#send-to').value,
          subject: sendCard.querySelector('#send-subject').value,
          html:    sendCard.querySelector('#send-html').value,
        });
        resultEl.innerHTML = `<div class="success-banner">Email sent!</div>`;
        sendCard.querySelector('#send-form').reset();
      } catch (err) {
        resultEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      }
      btn.disabled = false;
    };
  } catch (err) {
    container.innerHTML = `<div class="error-banner">${err.message}</div>`;
  }
}

// ── Admin ──────────────────────────────────────────────────────────────────

async function pageAdmin() {
  if (!requireAuth()) return;
  const user = getUser();
  if (user?.role !== 'ADMIN') { navigate('/dashboard', true); return; }

  renderShell('/admin', (main) => {
    main.innerHTML = '';
    main.appendChild(loadingEl());

    Promise.all([api.adminStats(), api.adminUsers(), api.adminProjects()]).then(([
      { users, projects, apiKeys, contacts, payments, pdfJobs, pushSubscriptions },
      { users: allUsers },
      { projects: allProjects },
    ]) => {
      main.innerHTML = '';
      main.innerHTML += `<div class="page-header"><h1>Admin</h1></div>`;

      // Stats
      const statsGrid = el('div', { class: 'stats-grid' });
      const tiles = [
        { label: 'Users',    value: users },
        { label: 'Projects', value: projects },
        { label: 'API Keys', value: `${apiKeys.active}/${apiKeys.total}` },
        { label: 'Contacts', value: contacts },
        { label: 'Payments', value: payments },
        { label: 'PDF Jobs', value: `${pdfJobs.completed}/${pdfJobs.total}` },
        { label: 'Push Subs',value: pushSubscriptions },
      ];
      for (const t of tiles) {
        statsGrid.innerHTML += `
          <div class="stat-tile">
            <div class="label">${t.label}</div>
            <div class="value">${t.value}</div>
          </div>`;
      }
      main.appendChild(statsGrid);

      // Users table
      main.innerHTML += `<h2 style="margin-bottom:.75rem">Users</h2>`;
      const uw = el('div', { class: 'table-wrap mb-3' });
      uw.innerHTML = `
        <table>
          <thead><tr><th>Email</th><th>Role</th><th>Projects</th><th>Joined</th></tr></thead>
          <tbody></tbody>
        </table>`;
      const utbody = uw.querySelector('tbody');
      for (const u of allUsers) {
        const tr = el('tr');
        tr.innerHTML = `
          <td><strong>${u.email}</strong></td>
          <td><span class="badge ${u.role === 'ADMIN' ? 'badge-blue' : 'badge-gray'}">${u.role}</span></td>
          <td>${u._count.projects}</td>
          <td>${fmtDate(u.createdAt)}</td>`;
        utbody.appendChild(tr);
      }
      main.appendChild(uw);

      // Projects table
      main.innerHTML += `<h2 style="margin-bottom:.75rem">Projects</h2>`;
      const pw = el('div', { class: 'table-wrap' });
      pw.innerHTML = `
        <table>
          <thead><tr><th>Name</th><th>Slug</th><th>Owner</th><th>Keys</th><th>Contacts</th><th>Created</th></tr></thead>
          <tbody></tbody>
        </table>`;
      const ptbody = pw.querySelector('tbody');
      for (const p of allProjects) {
        const tr = el('tr');
        tr.innerHTML = `
          <td><strong>${p.name}</strong></td>
          <td><span class="mono text-xs">${p.slug}</span></td>
          <td>${p.owner.email}</td>
          <td>${p.counts.apiKeys}</td>
          <td>${p.counts.contacts}</td>
          <td>${fmtDate(p.createdAt)}</td>`;
        ptbody.appendChild(tr);
      }
      main.appendChild(pw);
    }).catch(err => {
      main.innerHTML = `<div class="error-banner">${err.message}</div>`;
    });
  });
}

// ── Account settings ───────────────────────────────────────────────────────

async function pageSettings() {
  if (!requireAuth()) return;
  const user = getUser();

  renderShell('/settings', (main) => {
    main.innerHTML = '';
    main.innerHTML += `<div class="page-header"><h1>Account settings</h1></div>`;

    // Profile card
    const profileCard = el('div', { class: 'card mb-3' });
    profileCard.innerHTML = `
      <h2 style="margin-bottom:.75rem">Profile</h2>
      <div class="flex items-center gap-3">
        <div class="avatar" style="width:3rem;height:3rem;font-size:1.1rem">${(user?.email?.[0] ?? '?').toUpperCase()}</div>
        <div>
          <div style="font-weight:600">${user?.email}</div>
          <div class="text-sm text-muted">${user?.role}</div>
        </div>
      </div>`;
    main.appendChild(profileCard);

    // Password card
    const pwCard = el('div', { class: 'card' });
    pwCard.innerHTML = `
      <h2 style="margin-bottom:.75rem">Change password</h2>
      <form id="pw-form" style="max-width:380px">
        <div class="form-group">
          <label>Current password</label>
          <input type="password" id="cur-pw" required />
        </div>
        <div class="form-group">
          <label>New password</label>
          <input type="password" id="new-pw" required minlength="8" />
        </div>
        <div class="form-group">
          <label>Confirm new password</label>
          <input type="password" id="confirm-pw" required minlength="8" />
        </div>
        <div id="pw-msg"></div>
        <button class="btn btn-primary" type="submit" id="pw-btn">Update password</button>
      </form>`;
    main.appendChild(pwCard);

    pwCard.querySelector('#pw-form').onsubmit = async e => {
      e.preventDefault();
      const msgEl = pwCard.querySelector('#pw-msg');
      const btn = pwCard.querySelector('#pw-btn');
      msgEl.innerHTML = '';
      const newPw = pwCard.querySelector('#new-pw').value;
      const confirm = pwCard.querySelector('#confirm-pw').value;
      if (newPw !== confirm) {
        msgEl.innerHTML = `<div class="error-banner">Passwords do not match</div>`;
        return;
      }
      btn.disabled = true;
      try {
        await api.changePassword({
          currentPassword: pwCard.querySelector('#cur-pw').value,
          newPassword: newPw,
        });
        msgEl.innerHTML = `<div class="success-banner">Password updated successfully</div>`;
        pwCard.querySelector('#pw-form').reset();
      } catch (err) {
        msgEl.innerHTML = `<div class="error-banner">${err.message}</div>`;
      }
      btn.disabled = false;
    };
  });
}

// ── Route definitions ──────────────────────────────────────────────────────

addRoute('/login',              pageLogin);
addRoute('/register',           pageRegister);
addRoute('/dashboard',          pageDashboard);
addRoute('/projects/:id',       pageProject);
addRoute('/admin',              pageAdmin);
addRoute('/settings',           pageSettings);
addRoute('/',                   () => navigate(getToken() ? '/dashboard' : '/login', true));

// ── Boot ───────────────────────────────────────────────────────────────────

render(logicalPath());
