import { apiGet, apiPost, apiDelete } from '/js/chat/api/api.js';

function authEmail() {
  const el = document.querySelector('meta[name="auth-email"]');
  return el ? el.getAttribute('content') : '';
}

function el(tag, cls, text) {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text != null) e.textContent = text;
  return e;
}

function fmtDate(v) {
  try { return new Date(v).toLocaleString(); } catch { return v; }
}

async function loadUsers() {
  const tbody = document.getElementById('users-tbody');
  const countEl = document.getElementById('user-count');
  tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Loading…</td></tr>';
  try {
    const users = await apiGet('/api/admin/users');
    countEl.textContent = `${users.length} users`;
    renderUsers(tbody, users);
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-red-500">${e}</td></tr>`;
    countEl.textContent = 'Error';
  }
}

function renderUsers(tbody, users) {
  const me = authEmail();
  tbody.innerHTML = '';
  if (!users.length) {
    tbody.appendChild(el('tr', '', '')).appendChild(el('td', 'px-3 py-10 text-center text-slate-500', 'No users found')).setAttribute('colspan', '7');
    return;
  }
  for (const u of users) {
    const tr = el('tr');
    tr.appendChild(el('td', 'px-3 py-2', String(u.id)));
    tr.appendChild(el('td', 'px-3 py-2', u.name ?? ''));
    tr.appendChild(el('td', 'px-3 py-2', u.email ?? ''));
    tr.appendChild(el('td', 'px-3 py-2', u.role ?? 'user'));
    tr.appendChild(el('td', 'px-3 py-2', u.is_blocked ? 'Yes' : 'No'));
    tr.appendChild(el('td', 'px-3 py-2', fmtDate(u.created_at)));

    const actions = el('td', 'px-3 py-2 text-right');

    // Promote/Demote
    const isAdmin = String(u.role) === 'admin';
    const roleBtn = el('button', `inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium ${isAdmin ? 'border-amber-500/40 text-amber-400 hover:bg-amber-500/10' : 'border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10'}`,
      isAdmin ? 'Make User' : 'Make Admin');
    roleBtn.addEventListener('click', async () => {
      roleBtn.disabled = true;
      try {
        if (isAdmin) await apiPost(`/api/admin/users/${u.id}/make-user`);
        else await apiPost(`/api/admin/users/${u.id}/make-admin`);
        await loadUsers();
      } catch (e) {
        alert(e);
      } finally {
        roleBtn.disabled = false;
      }
    });
    actions.appendChild(roleBtn);

    // Delete (hide for self by email)
    const canDelete = (u.email && u.email !== me);
    const delBtn = el('button', 'ml-2 inline-flex items-center rounded-md border border-rose-500/40 px-2 py-1 text-xs font-medium text-rose-400 hover:bg-rose-500/10', 'Delete');
    if (!canDelete) {
      delBtn.classList.add('opacity-50', 'cursor-not-allowed');
      delBtn.title = 'Cannot delete your own account';
    } else {
      delBtn.addEventListener('click', async () => {
        if (!confirm(`Delete user ${u.email}? This cannot be undone.`)) return;
        delBtn.disabled = true;
        try {
          await apiDelete(`/api/admin/users/${u.id}`);
          await loadUsers();
        } catch (e) {
          alert(e);
        } finally {
          delBtn.disabled = false;
        }
      });
    }
    actions.appendChild(delBtn);

    tr.appendChild(actions);
    tbody.appendChild(tr);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const role = (document.querySelector('meta[name="auth-role"]')?.getAttribute('content') || '').toLowerCase();
  if (role !== 'admin') {
    const tbody = document.getElementById('users-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-10 text-center text-red-500">Forbidden</td></tr>';
    return;
  }
  loadUsers();
  initCreateForm();
});

function initCreateForm(){
  const form = document.getElementById('create-user-form');
  const result = document.getElementById('create-user-result');
  if (!form) return;
  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    result.textContent = '';
    const fd = new FormData(form);
    const payload = {
      name: String(fd.get('name')||'').trim(),
      email: String(fd.get('email')||'').trim(),
      role: String(fd.get('role')||'user'),
      send_reset: fd.get('send_reset') ? true : false,
    };
    try {
      const r = await apiPost('/api/admin/users', payload);
      const pw = r.temporary_password ? `Temporary password: ${r.temporary_password}` : '';
      const mail = r.reset_link_sent ? `Password email: ${r.mail_status || 'sent'}` : 'Email not sent';
      result.className = 'text-sm text-emerald-400';
      result.textContent = `Created ${r.user.email}. ${pw} ${mail}`.trim();
      form.reset();
      // keep role default as user and send_reset checked
      form.querySelector('select[name="role"]').value = 'user';
      form.querySelector('input[name="send_reset"]').checked = true;
      await loadUsers();
    } catch (err) {
      result.className = 'text-sm text-rose-400';
      result.textContent = String(err);
    }
  });
}
