function fillSelect(select, items, placeholder, selectedValue) {
  if (!select) return;
  select.innerHTML = '';
  const ph = document.createElement('option');
  ph.value = '';
  ph.textContent = placeholder;
  ph.disabled = true; ph.selected = !selectedValue;
  select.appendChild(ph);
  (items || []).forEach(item => {
    const value = item.value ?? item.id ?? '';
    const label = item.label ?? item.name ?? String(value);
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    if (String(value) === String(selectedValue)) opt.selected = true;
    select.appendChild(opt);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const provSel = document.getElementById('province');
  const distSel = document.getElementById('district');
  const citySel = document.getElementById('city');
  if (!provSel || !distSel || !citySel) return;

  const baseUrl = 'room_management.php';
  const current = {
    province_id: provSel.getAttribute('data-current') || '',
    district_id: distSel.getAttribute('data-current') || '',
    city_id: citySel.getAttribute('data-current') || ''
  };

  fetch(baseUrl + '?geo=provinces')
    .then(r=>r.json())
    .then(list=>{
      fillSelect(provSel, list.map(x=>({value:x.province_id,label:x.name})), 'Select province', current.province_id);
      if (current.province_id) {
        return fetch(baseUrl + '?geo=districts&province_id=' + encodeURIComponent(current.province_id))
          .then(r=>r.json())
          .then(list=>{
            fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district', current.district_id);
            if (current.district_id) {
              return fetch(baseUrl + '?geo=cities&district_id=' + encodeURIComponent(current.district_id))
                .then(r=>r.json())
                .then(list=>fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city', current.city_id));
            }
          });
      }
    })
    .catch(()=>{});

  provSel.addEventListener('change', ()=>{
    const pid = encodeURIComponent(provSel.value||'');
    fetch(baseUrl + '?geo=districts&province_id=' + pid)
      .then(r=>r.json())
      .then(list=>{ fillSelect(distSel, list.map(x=>({value:x.district_id,label:x.name})), 'Select district'); fillSelect(citySel, [], 'Select city'); })
      .catch(()=>{ fillSelect(distSel, [], 'Select district'); fillSelect(citySel, [], 'Select city'); });
  });

  distSel.addEventListener('change', ()=>{
    const did = encodeURIComponent(distSel.value||'');
    fetch(baseUrl + '?geo=cities&district_id=' + did)
      .then(r=>r.json())
      .then(list=>fillSelect(citySel, list.map(x=>({value:x.city_id,label:x.name})), 'Select city'))
      .catch(()=>fillSelect(citySel, [], 'Select city'));
  });
});

