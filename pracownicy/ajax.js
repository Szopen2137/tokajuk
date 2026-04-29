// === UTILITY FUNCTIONS ===
function showLoader() {
  const loader = document.getElementById('ajax-loader');
  if (loader) loader.style.display = 'block';
}

function hideLoader() {
  const loader = document.getElementById('ajax-loader');
  if (loader) loader.style.display = 'none';
}

function showDataLoader(container) {
  const loader = document.createElement('tr');
  loader.innerHTML = `<td colspan="100%"><div class="d-flex justify-content-center w-100 py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div></td>`;
  container.appendChild(loader);
  return loader;
}

function injectStartupStyles() {
  if (document.getElementById('startup-loader-styles')) return;
  const style = document.createElement('style');
  style.id = 'startup-loader-styles';
  style.textContent = `/* Data loader styles handled by showDataLoader() */`;
  document.head.appendChild(style);
}

function isAjaxForm(form) {
  return form.classList.contains('ajax-form') || form.dataset.ajax === 'true';
}

function emitEvent(target, name, detail) {
  target.dispatchEvent(new CustomEvent(name, { detail: detail, bubbles: true }));
}

// === DATA LOADING FUNCTIONS ===
function loadWorkers() {
  const container = document.getElementById('workersData');
  if (!container) return;
  container.innerHTML = '';
  showDataLoader(container);
  fetch('getWorkers.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.text())
  .then(html => { container.innerHTML = html; })
  .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd ładowania danych</td></tr>'; });
}

function loadEtaty() {
  const container = document.getElementById('etatyData');
  if (!container) return;
  container.innerHTML = '';
  showDataLoader(container);
  fetch('getEtaty.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.text())
  .then(html => { container.innerHTML = html; })
  .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd ładowania danych</td></tr>'; });
}

function loadZespoly() {
  const container = document.getElementById('zespolyData');
  if (!container) return;
  container.innerHTML = '';
  showDataLoader(container);
  fetch('getZespoly.php', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.text())
  .then(html => { container.innerHTML = html; })
  .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd ładowania danych</td></tr>'; });
}

// === SEARCH FUNCTIONS ===
function initSearchWorkers() {
  const form = document.querySelector('#szukajka');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const container = document.getElementById('workersData');
    container.innerHTML = '';
    showDataLoader(container);
    const fd = new FormData(form);
    fetch('getWorkersSzukajka.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => { container.innerHTML = html; })
    .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd wyszukiwania</td></tr>'; });
  });
}

function initSearchEtaty() {
  const form = document.querySelector('#szukajka-etat');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const container = document.getElementById('etatyData');
    container.innerHTML = '';
    showDataLoader(container);
    const fd = new FormData(form);
    fetch('getEtatySzukajka.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => { container.innerHTML = html; })
    .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd wyszukiwania</td></tr>'; });
  });
}

function initSearchZespoly() {
  const form = document.querySelector('#szukajka-zesp');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const container = document.getElementById('zespolyData');
    container.innerHTML = '';
    showDataLoader(container);
    const fd = new FormData(form);
    fetch('getZespolySzukajka.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => { container.innerHTML = html; })
    .catch(() => { container.innerHTML = '<tr><td colspan="100%" class="text-center text-danger">Błąd wyszukiwania</td></tr>'; });
  });
}

// === FORM SUBMISSION ===
async function handleAjaxSubmit(form) {
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
      emitEvent(form, 'ajax:success', data);
      if (data.action === 'delete') {
        const row = form.closest('tr');
        if (row) row.remove();
        return;
      }
      if (form.dataset.refreshOnSuccess === 'true') {
        window.setTimeout(function () { window.location.reload(); }, 1000);
        return;
      }
      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }
    } else {
      emitEvent(form, 'ajax:error', data || { error: 'ajax-failed' });
    }
  } catch (err) {
    emitEvent(form, 'ajax:error', { error: 'network' });
  } finally {
    hideLoader();
  }
}

// === EVENT LISTENERS ===
document.addEventListener('DOMContentLoaded', function () {
  injectStartupStyles();
  if (document.getElementById('workersData')) {
    loadWorkers();
    initSearchWorkers();
  }
  if (document.getElementById('etatyData')) {
    loadEtaty();
    initSearchEtaty();
  }
  if (document.getElementById('zespolyData')) {
    loadZespoly();
    initSearchZespoly();
  }
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!isAjaxForm(form)) return;
    e.preventDefault();
    handleAjaxSubmit(form);
  });
});

document.addEventListener('ajax:success', function (e) {
  const data = e.detail || {};
  const form = e.target;
  if (data.action === 'delete') return;
  if (form && form.classList && form.classList.contains('ajax-form')) {
    const feedback = form.parentElement ? form.parentElement.querySelector('#ajax-feedback') : null;
    if (feedback && data.message) {
      feedback.innerHTML = '<div class="alert alert-success" role="alert">' + data.message + '</div>';
      return;
    }
  }
  if (data.message) alert(data.message);
});

document.addEventListener('ajax:error', function (e) {
  const d = e.detail || {};
  const form = e.target;
  if (form && form.classList && form.classList.contains('ajax-form')) {
    const feedback = form.parentElement ? form.parentElement.querySelector('#ajax-feedback') : null;
    if (feedback) {
      feedback.innerHTML = '<div class="alert alert-danger">' + (d.error || 'Błąd serwera') + '</div>';
      return;
    }
  }
  alert(d.error || 'Wystąpił błąd (AJAX).');
});
