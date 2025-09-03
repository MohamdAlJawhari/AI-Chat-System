import { ensureAuth, getToken } from './auth.js';

function authHeaders() {
  const hdrs = { 'Accept': 'application/json' };
  const t = getToken();
  if (t) hdrs['Authorization'] = `Bearer ${t}`;
  return hdrs;
}

export async function apiGet(path) {
  const doFetch = () => fetch(path, { headers: authHeaders() });
  let r = await doFetch();
  if (r.status === 401) {
    const ok = await ensureAuth();
    if (ok) r = await doFetch();
  }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiPost(path, body) {
  const doFetch = () => fetch(path, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(body ?? {}) });
  let r = await doFetch();
  if (r.status === 401) {
    const ok = await ensureAuth();
    if (ok) r = await doFetch();
  }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiPatch(path, body) {
  const doFetch = () => fetch(path, { method: 'PATCH', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(body ?? {}) });
  let r = await doFetch();
  if (r.status === 401) {
    const ok = await ensureAuth();
    if (ok) r = await doFetch();
  }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiDelete(path) {
  const doFetch = () => fetch(path, { method: 'DELETE', headers: authHeaders() });
  let r = await doFetch();
  if (r.status === 401) {
    const ok = await ensureAuth();
    if (ok) r = await doFetch();
  }
  if (!r.ok) throw new Error(await r.text());
  return r.json().catch(() => ({}));
}

export function getAuthHeaders() { return authHeaders(); }
