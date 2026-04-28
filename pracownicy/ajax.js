// Global AJAX helper for forms with class 'ajax-form' or attribute data-ajax="true"
function showLoader() {
  const l = document.getElementById('ajax-loader');
  if (l) l.style.display = 'block';
}
function hideLoader() {
  const l = document.getElementById('ajax-loader');
  if (l) l.style.display = 'none';
}

document.addEventListener('submit', async function (e) {
  const form = e.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (!form.classList.contains('ajax-form') && form.dataset.ajax !== 'true') return;

  e.preventDefault();
  showLoader();

  const url = form.action || window.location.href;
  const method = (form.method || 'POST').toUpperCase();
  const fd = new FormData(form);

  try {
    const res = await fetch(url, {
      method: method,
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (res.ok && data && data.success) {
      // default success action: if response contains redirect, follow it
      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }
      // dispatch custom event for handling UI updates
      form.dispatchEvent(new CustomEvent('ajax:success', { detail: data }));
    } else {
      form.dispatchEvent(new CustomEvent('ajax:error', { detail: data }));
    }
  } catch (err) {
    form.dispatchEvent(new CustomEvent('ajax:error', { detail: { error: 'network' } }));
  } finally {
    hideLoader();
  }
});

// basic convenience: show alerts on success/error if no handler attached
document.addEventListener('ajax:success', function (e) {
  const data = e.detail;
  if (data && data.message) alert(data.message);
});
document.addEventListener('ajax:error', function (e) {
  const d = e.detail || {};
  alert(d.error || 'Wystąpił błąd (AJAX).');
});

// If a form dispatches ajax:success itself, allow inline handlers.
document.addEventListener('submit', function () {}, true);
