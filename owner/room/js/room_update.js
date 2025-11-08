 (() => {
   const alertHost = document.getElementById('formAlert');
   const form = document.querySelector('form.needs-validation');

   const showAlert = (type, html) => {
     if (!alertHost) return;
     alertHost.innerHTML = `
       <div class="alert alert-${type} alert-dismissible fade show" role="alert">
         ${html}
         <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
       </div>`;
   };
   const clearAlert = () => { if (alertHost) alertHost.innerHTML = ''; };

   // Geo loaders
   const provinceSel = document.getElementById('province');
   const districtSel = document.getElementById('district');
   const citySel = document.getElementById('city');

   const opt = (v, t, selected=false) => {
     const o = document.createElement('option');
     o.value = String(v);
     o.textContent = t;
     if (selected) o.selected = true;
     return o;
   };

   const loadProvinces = async () => {
     if (!provinceSel) return;
     provinceSel.innerHTML = '';
     provinceSel.append(opt('', 'Select province'));
     try {
       const res = await fetch('?geo=provinces');
       const data = await res.json();
       const current = provinceSel.getAttribute('data-current') || '';
       data.forEach(p => provinceSel.append(opt(p.province_id, p.name, String(p.province_id)===current)));
     } catch {}
   };

   const loadDistricts = async (provinceId) => {
     if (!districtSel) return;
     districtSel.innerHTML = '';
     districtSel.append(opt('', 'Select district'));
     citySel && (citySel.innerHTML = '', citySel.append(opt('', 'Select city')));
     if (!provinceId) return;
     try {
       const res = await fetch(`?geo=districts&province_id=${encodeURIComponent(provinceId)}`);
       const data = await res.json();
       const current = districtSel.getAttribute('data-current') || '';
       data.forEach(d => districtSel.append(opt(d.district_id, d.name, String(d.district_id)===current)));
     } catch {}
   };

   const loadCities = async (districtId) => {
     if (!citySel) return;
     citySel.innerHTML = '';
     citySel.append(opt('', 'Select city'));
     if (!districtId) return;
     try {
       const res = await fetch(`?geo=cities&district_id=${encodeURIComponent(districtId)}`);
       const data = await res.json();
       const current = citySel.getAttribute('data-current') || '';
       data.forEach(c => citySel.append(opt(c.city_id, c.name, String(c.city_id)===current)));
     } catch {}
   };

   provinceSel && provinceSel.addEventListener('change', (e) => loadDistricts(e.target.value));
   districtSel && districtSel.addEventListener('change', (e) => loadCities(e.target.value));

   // initial cascade preselect
   loadProvinces().then(() => {
     const pid = provinceSel && provinceSel.getAttribute('data-current');
     if (pid) loadDistricts(pid).then(() => {
       const did = districtSel && districtSel.getAttribute('data-current');
       if (did) loadCities(did);
     });
   });

   if (form) {
     form.addEventListener('submit', (e) => {
       clearAlert();
       const errors = [];

       const title = form.querySelector('input[name="title"]');
       if (title && (!title.value.trim() || title.value.length > 150)) {
         errors.push('Title is required and must be 150 characters or less.');
       }

       const province = form.querySelector('select[name="province_id"]');
       const district = form.querySelector('select[name="district_id"]');
       const city = form.querySelector('select[name="city_id"]');
       if (!province?.value) errors.push('Please select a province.');
       if (!district?.value) errors.push('Please select a district.');
       if (!city?.value) errors.push('Please select a city.');

       const postal = form.querySelector('input[name="postal_code"]');
       if (postal) {
         const v = postal.value.trim();
         const re = /^[A-Za-z0-9\-\s]{1,10}$/;
         if (!v) errors.push('Postal code is required.');
         else if (!re.test(v)) errors.push('Postal code must be up to 10 letters/numbers.');
       }

       const price = form.querySelector('input[name="price_per_day"]');
       if (price) {
         const val = parseFloat(price.value);
         if (isNaN(val) || val <= 0) errors.push('Price per day must be greater than 0.');
       }

       const beds = form.querySelector('input[name="beds"]');
       if (beds) {
         const val = parseInt(beds.value, 10);
         if (isNaN(val) || val < 1) errors.push('Beds must be at least 1.');
       }

       const guests = form.querySelector('input[name="maximum_guests"]');
       if (guests) {
         const val = parseInt(guests.value, 10);
         if (isNaN(val) || val < 1) errors.push('Maximum guests must be at least 1.');
       }

       // images
       const maxSize = 5 * 1024 * 1024;
       const primary = form.querySelector('input[name="image"]');
       if (primary && primary.files && primary.files[0]) {
         const f = primary.files[0];
         if (!f.type.startsWith('image/')) errors.push('Primary image must be an image file.');
         if (f.size > maxSize) errors.push('Primary image must be 5MB or less.');
       }
       const gallery = form.querySelector('input[name="gallery_images[]"]');
       if (gallery && gallery.files && gallery.files.length) {
         for (const f of gallery.files) {
           if (!f.type.startsWith('image/')) { errors.push('All gallery files must be images.'); break; }
           if (f.size > maxSize) { errors.push('Each gallery image must be 5MB or less.'); break; }
         }
       }

       if (errors.length) {
         e.preventDefault();
         const html = `<div class="fw-semibold mb-2">Please fix the following:</div><ul class="mb-0">${errors.map(x=>`<li>${x}</li>`).join('')}</ul>`;
         showAlert('danger', html);
       }
     });
   }
 })();

