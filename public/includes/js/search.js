(function(){
  const form = document.getElementById('search-form');
  if (!form) return;
  const province = document.getElementById('province_id');
  const district = document.getElementById('district_id');
  const city = document.getElementById('city_id');
  const scopeSel = document.getElementById('scope');
  const qInput = form.querySelector('input[name="q"]');
  const spProvince = document.getElementById('spinner-province');
  const spDistrict = document.getElementById('spinner-district');
  const spCity = document.getElementById('spinner-city');

  function params() {
    const p = new URLSearchParams();
    const q = qInput ? qInput.value.trim() : '';
    if (q) p.set('q', q);
    if (province && province.value) p.set('province_id', province.value);
    if (district && district.value) p.set('district_id', district.value);
    if (city && city.value) p.set('city_id', city.value);
    if (scopeSel && scopeSel.value) p.set('scope', scopeSel.value);
    return p;
  }

  function debounce(fn, wait){ let t; return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), wait); }; }

  function setBusy(sectionId, busy) {
    const el = document.getElementById(sectionId);
    if (!el) return;
    el.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function ensureEmptyState(container) {
    const hasItems = container.querySelector('[data-result-item], .result-item, .card, .list-group-item');
    let empty = container.querySelector('.empty-state');
    if (!hasItems) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'empty-state text-center p-4';
        empty.innerHTML = '<i class="bi bi-search text-muted fs-3 d-block mb-2" aria-hidden="true"></i><div class="text-muted">No results found. Try adjusting your filters or keywords.</div>';
        container.appendChild(empty);
      }
      empty.classList.remove('d-none');
    } else if (empty) {
      empty.classList.add('d-none');
    }
  }

  async function replaceSection(sectionId, url) {
    const target = document.getElementById(sectionId);
    if (!target) return;
    setBusy(sectionId, true);
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const html = await res.text();
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const incoming = temp.querySelector('#' + sectionId) || temp.firstElementChild || temp;
    target.replaceWith(incoming);
    ensureEmptyState(incoming);
    setBusy(sectionId, false);
  }

  function currentScope() {
    const v = (scopeSel && scopeSel.value) ? scopeSel.value : 'all';
    return v === 'properties' || v === 'rooms' ? v : 'all';
  }

  async function fetchResults() {
    const p = params();
    const base = new URL(form.action, window.location.origin);
    // property.php URL
    const propUrl = new URL('property.php', base);
    propUrl.search = p.toString();
    // room.php URL
    const roomUrl = new URL('room.php', base);
    roomUrl.search = p.toString();
    const tasks = [];
    // Toggle visibility before fetching based on scope
    const propsSection = document.getElementById('properties-section');
    const roomsSection = document.getElementById('rooms-section');
    const scope = currentScope();
    if (propsSection) propsSection.classList.toggle('d-none', scope === 'rooms');
    if (roomsSection) roomsSection.classList.toggle('d-none', scope === 'properties');
    if (scope !== 'rooms') tasks.push(replaceSection('properties-section', propUrl.toString()));
    if (scope !== 'properties') tasks.push(replaceSection('rooms-section', roomUrl.toString()));
    await Promise.all(tasks);
  }

  async function fetchDistricts() {
    if (!province || !district) return;
    const p = new URLSearchParams({ ajax: '1', action: 'districts', province_id: province.value || '' });
    const url = `${form.action}?${p.toString()}`;
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const placeholder = province.value ? 'District' : 'Select province first';
    district.innerHTML = `<option value="">${placeholder}</option>` + data.map(d => `<option value="${d.district_id}">${d.name}</option>`).join('');
    district.disabled = !province.value;
    district.toggleAttribute('aria-disabled', !province.value);
  }

  async function fetchCities() {
    if (!district || !city) return;
    const p = new URLSearchParams({ ajax: '1', action: 'cities', district_id: district.value || '' });
    const url = `${form.action}?${p.toString()}`;
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const placeholder = district.value ? 'City' : 'Select district first';
    city.innerHTML = `<option value="">${placeholder}</option>` + data.map(c => `<option value="${c.city_id}">${c.name}</option>`).join('');
    city.disabled = !district.value;
    city.toggleAttribute('aria-disabled', !district.value);
  }

  async function withSpinner(spinnerEl, selectEl, loaderFn) {
    if (spinnerEl) spinnerEl.classList.remove('d-none');
    if (selectEl) selectEl.setAttribute('aria-busy', 'true');
    try { await loaderFn(); }
    finally {
      if (spinnerEl) spinnerEl.classList.add('d-none');
      if (selectEl) selectEl.setAttribute('aria-busy', 'false');
    }
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    fetchResults();
  });

  if (qInput) {
    qInput.addEventListener('input', debounce(() => { fetchResults(); }, 300));
  }

  if (province) {
    province.addEventListener('change', async function() {
      if (district) district.value = '';
      if (city) { city.value = ''; city.disabled = true; city.setAttribute('aria-disabled', 'true'); }
      await withSpinner(spDistrict, district, fetchDistricts);
      await fetchResults();
    });
  }
  if (district) {
    district.addEventListener('change', async function() {
      if (city) city.value = '';
      await withSpinner(spCity, city, fetchCities);
      await fetchResults();
    });
  }
  if (city) {
    city.addEventListener('change', function() { fetchResults(); });
  }
  if (scopeSel) scopeSel.addEventListener('change', () => { fetchResults(); });

  // Trigger initial fetch to honor scope selection and refresh sections
  document.addEventListener('DOMContentLoaded', function(){
    if (form) { form.dispatchEvent(new Event('submit', { cancelable: true })); }
  });

  // Enter key submits the form regardless of active field (avoid textarea/button)
  form.addEventListener('keydown', function(e){
    if (e.isComposing || e.key !== 'Enter') return;
    if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.type === 'submit' || e.target.type === 'button')) return;
    e.preventDefault();
    form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
  });
})();
