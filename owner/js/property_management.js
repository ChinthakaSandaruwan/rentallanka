function fillSelect(select, items, placeholder) {
  if (!select) return;
  select.innerHTML = '';
  const ph = document.createElement('option');
  ph.value = '';
  ph.textContent = placeholder;
  ph.disabled = true; ph.selected = true;
  select.appendChild(ph);
  (items || []).forEach(item => {
    const isObj = typeof item === 'object' && item !== null;
    const value = isObj ? (item.value ?? item.id ?? '') : item;
    const label = isObj ? (item.label ?? item.name ?? String(value)) : String(item);
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    select.appendChild(opt);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const provSel = document.getElementById('province');
  const distSel = document.getElementById('district');
  const citySel = document.getElementById('city');
  if (!provSel || !distSel || !citySel) return;

  const baseUrl = window.location.pathname;
  fetch(baseUrl + '?geo=provinces')
    .then(r => r.json())
    .then(list => fillSelect(provSel, list.map(x=>({value:x.province_id,label:x.name})), 'Select province'))
    .catch(() => fillSelect(provSel, [], 'Select province'));

  provSel.addEventListener('change', () => {
    const pid = encodeURIComponent(provSel.value || '');
    fetch(baseUrl + '?geo=districts&province_id=' + pid)
      .then(r => r.json())
      .then(list => {
        fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district');
        fillSelect(citySel, [], 'Select city');
      })
      .catch(() => {
        fillSelect(distSel, [], 'Select district');
        fillSelect(citySel, [], 'Select city');
      });
  });

  distSel.addEventListener('change', () => {
    const did = encodeURIComponent(distSel.value || '');
    fetch(baseUrl + '?geo=cities&district_id=' + did)
      .then(r => r.json())
      .then(list => fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city'))
      .catch(() => fillSelect(citySel, [], 'Select city'));
  });
});

