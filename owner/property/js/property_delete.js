document.addEventListener('DOMContentLoaded', () => {
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

  if (!form) return;

  let submitting = false;
  form.addEventListener('submit', (e) => {
    clearAlert();
    const errors = [];

    const pid = form.querySelector('input[name="property_id"]');
    if (!pid || !pid.value || isNaN(parseInt(pid.value, 10)) || parseInt(pid.value, 10) <= 0) {
      errors.push('Invalid property.');
    }
    const csrf = form.querySelector('input[name="csrf_token"]');
    if (!csrf || !csrf.value) {
      errors.push('Missing security token. Please refresh the page.');
    }

    if (errors.length) {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add('was-validated');
      showAlert('danger', `<ul class="mb-0">${errors.map(x=>`<li>${x}</li>`).join('')}</ul>`);
      return;
    }

    if (submitting) {
      e.preventDefault();
      return;
    }

    const confirmed = window.confirm('Delete this property and all its images?');
    if (!confirmed) {
      e.preventDefault();
      return;
    }
    submitting = true;
  });
});

