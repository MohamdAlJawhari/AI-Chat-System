(() => {
  const CACHE_KEY = 'uchat:filter-options:v1';
  const TTL_MS = 1000 * 60 * 60 * 12; // 12 hours
  const ENDPOINT = '/api/filter-options';

  const datalists = {
    category: 'filter-category-options',
    country: 'filter-country-options',
    city: 'filter-city-options',
  };

  function ensureDatalist(id) {
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('datalist');
      el.id = id;
      document.body.appendChild(el);
    }
    return el;
  }

  function attachListAttributes() {
    Object.entries(datalists).forEach(([type, listId]) => {
      document.querySelectorAll(`[data-filter-source="${type}"]`).forEach((input) => {
        input.setAttribute('list', listId);
      });
    });
  }

  function populateLists(options) {
    const categories = options?.categories || [];
    const countries = options?.countries || [];
    const cities = options?.cities || [];

    const fill = (listId, values) => {
      const dl = ensureDatalist(listId);
      dl.innerHTML = '';
      values.forEach((val) => {
        const opt = document.createElement('option');
        opt.value = val;
        dl.appendChild(opt);
      });
    };

    fill(datalists.category, categories);
    fill(datalists.country, countries);
    fill(datalists.city, cities);
  }

  function readCache() {
    try {
      const raw = localStorage.getItem(CACHE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (parsed?.expiresAt && parsed.expiresAt > Date.now() && parsed.data) {
        return parsed.data;
      }
    } catch (e) {
      console.warn('Filter options cache parse failed', e);
    }
    return null;
  }

  function writeCache(data) {
    try {
      localStorage.setItem(
        CACHE_KEY,
        JSON.stringify({ expiresAt: Date.now() + TTL_MS, data }),
      );
    } catch (e) {
      console.warn('Filter options cache write failed', e);
    }
  }

  async function fetchOptions() {
    const cached = readCache();
    if (cached) {
      return cached;
    }
    const res = await fetch(ENDPOINT, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
      throw new Error(`Filter options request failed (${res.status})`);
    }
    const data = await res.json();
    writeCache(data);
    return data;
  }

  async function init() {
    const needsOptions = document.querySelector('[data-filter-source]');
    if (!needsOptions) return;
    attachListAttributes();
    try {
      const options = await fetchOptions();
      populateLists(options);
    } catch (e) {
      console.error('Failed to load filter options', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
