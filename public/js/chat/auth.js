let token = localStorage.getItem('apiToken') || null;
let authRequester = null; // function that shows UI and resolves when logged in

export function getToken(){ return token; }

export async function login(email, password){
  const r = await fetch('/api/auth/login', { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({ email, password })});
  if (!r.ok) throw new Error(await r.text());
  const json = await r.json();
  token = json.token; localStorage.setItem('apiToken', token);
  return json;
}

export async function register(name, email, password){
  const r = await fetch('/api/auth/register', { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({ name, email, password })});
  if (!r.ok) throw new Error(await r.text());
  const json = await r.json();
  token = json.token; localStorage.setItem('apiToken', token);
  return json;
}

export async function logout(){
  if (!token) return;
  await fetch('/api/auth/logout', { method:'POST', headers:{'Authorization': `Bearer ${token}`, 'Accept':'application/json'} });
  token = null; localStorage.removeItem('apiToken');
}

export async function ensureAuth(){
  if (token) return true;
  if (typeof authRequester === 'function') {
    return await authRequester();
  }
  // Fallback prompt flow
  const email = window.prompt('Email:');
  if (!email) return false;
  const password = window.prompt('Password:');
  if (!password) return false;
  try { await login(email, password); return true; }
  catch {
    const name = window.prompt('Enter name to sign up:');
    if (!name) return false; await register(name, email, password); return true;
  }
}

export function attachAuthRequester(fn){ authRequester = fn; }
