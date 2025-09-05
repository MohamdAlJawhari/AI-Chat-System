function csrf() {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el ? el.getAttribute('content') : '';
}

function baseHeaders() {
  return { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
}

export async function apiGet(path) {
  const r = await fetch(path, { headers: baseHeaders(), credentials: 'same-origin' });
  if (r.status === 401) { try{ window.location.assign('/login'); }catch{} throw new Error('Unauthorized'); }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiPost(path, body) {
  const r = await fetch(path, { method: 'POST', headers: { ...baseHeaders(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify(body ?? {}) });
  if (r.status === 401) { try{ window.location.assign('/login'); }catch{} throw new Error('Unauthorized'); }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiPatch(path, body) {
  const r = await fetch(path, { method: 'PATCH', headers: { ...baseHeaders(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin', body: JSON.stringify(body ?? {}) });
  if (r.status === 401) { try{ window.location.assign('/login'); }catch{} throw new Error('Unauthorized'); }
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

export async function apiDelete(path) {
  const r = await fetch(path, { method: 'DELETE', headers: { ...baseHeaders(), 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' });
  if (r.status === 401) { try{ window.location.assign('/login'); }catch{} throw new Error('Unauthorized'); }
  if (!r.ok) throw new Error(await r.text());
  return r.json().catch(() => ({}));
}

export function getAuthHeaders() { return baseHeaders(); }
