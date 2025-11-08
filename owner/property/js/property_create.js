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

  const baseUrl = window.location.pathname; 
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
    .catch(()=>{ fillSelect(provSel, [], 'Select province'); });

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

  // Client-side validation and Bootstrap alert summary
  const form = document.querySelector('form.needs-validation');
  const alertHost = document.getElementById('formAlert');
  const showAlert = (type, html) => {
    if (!alertHost) return;
    alertHost.innerHTML = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${html}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>`;
  };

  const clearAlert = () => { if (alertHost) alertHost.innerHTML = ''; };

  if (form) {
    form.addEventListener('submit', (e) => {
      clearAlert();
      const errors = [];

      // Required text fields
      const title = form.querySelector('input[name="title"]');
      if (title && (!title.value.trim() || title.value.length > 255)) {
        errors.push('Title is required and must be 255 characters or less.');
      }

      // Location selects
      const province = form.querySelector('select[name="province_id"]');
      const district = form.querySelector('select[name="district_id"]');
      const city = form.querySelector('select[name="city_id"]');
      if (!province?.value) errors.push('Please select a province.');
      if (!district?.value) errors.push('Please select a district.');
      if (!city?.value) errors.push('Please select a city.');

      // Postal code
      const postal = form.querySelector('input[name="postal_code"]');
      if (postal) {
        const v = postal.value.trim();
        const re = /^[A-Za-z0-9\-\s]{1,10}$/;
        if (!v) errors.push('Postal code is required.');
        else if (!re.test(v)) errors.push('Postal code must be up to 10 letters/numbers.');
      }

      // Price
      const price = form.querySelector('input[name="price_per_month"]');
      if (price) {
        const val = parseFloat(price.value);
        if (isNaN(val) || val < 0) errors.push('Price per month must be a non-negative number.');
      }

      // Integers non-negative
      const intFields = ['bedrooms','bathrooms','living_rooms'];
      intFields.forEach(name => {
        const el = form.querySelector(`input[name="${name}"]`);
        if (el && (parseInt(el.value || '0', 10) < 0)) {
          errors.push(`${name.replace('_',' ')} must be non-negative.`);
        }
      });

      // sqft optional non-negative
      const sqft = form.querySelector('input[name="sqft"]');
      if (sqft && sqft.value !== '') {
        const v = parseFloat(sqft.value);
        if (isNaN(v) || v < 0) errors.push('Area (sqft) must be non-negative.');
      }

      // File validations (<=5MB, image/*)
      const checkFile = (file) => file && file.size <= 5 * 1024 * 1024 && file.type.startsWith('image/');
      const img = form.querySelector('input[name="image"]');
      if (img && img.files && img.files[0]) {
        if (!checkFile(img.files[0])) errors.push('Primary image must be an image file up to 5MB.');
      }
      const gallery = form.querySelector('input[name="gallery_images[]"]');
      if (gallery && gallery.files) {
        for (let i = 0; i < gallery.files.length; i++) {
          if (!checkFile(gallery.files[i])) { errors.push('Each gallery image must be an image file up to 5MB.'); break; }
        }
      }

      // Use Bootstrap validation UI
      if (!form.checkValidity() || errors.length > 0) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        if (errors.length) {
          showAlert('danger', `<div class="fw-bold mb-1">Please fix the following:</div><ul class="mb-0">${errors.map(x=>`<li>${x}</li>`).join('')}</ul>`);
        }
      }
    }, false);
  }

});
