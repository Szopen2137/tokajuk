function showLoader() {
  const loader = document.getElementById('ajax-loader');
  if (loader) loader.style.display = 'block';
}

function hideLoader() {
  const loader = document.getElementById('ajax-loader');
  if (loader) loader.style.display = 'none';
}

function ensureStartupLoader() {
  if (document.getElementById('startup-loader')) return;

  if (document.body) {
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 180ms ease';
  }

  const loader = document.createElement('div');
  loader.id = 'startup-loader';
  loader.innerHTML = `
    <div class="startup-loader-backdrop"></div>
    <div class="startup-loader-box" role="status" aria-live="polite">
      <div class="spinner-border text-primary" aria-hidden="true"></div>
      <div class="startup-loader-text">Ładowanie strony...</div>
    </div>
  `;
  document.body.appendChild(loader);

  // Spinner will be hidden by hideStartupLoader() when data is loaded
}

function hideStartupLoader() {
  const loader = document.getElementById('startup-loader');
  if (!loader) return;

  loader.classList.add('startup-loader-hide');
  if (document.body) {
    document.body.style.opacity = '1';
  }
  window.setTimeout(function () {
    if (loader.parentElement) {
      loader.remove();
    }
  }, 300);
}

function injectStartupStyles() {
  if (document.getElementById('startup-loader-styles')) return;

  const style = document.createElement('style');
  style.id = 'startup-loader-styles';
  style.textContent = `
    #startup-loader {
      position: fixed;
      inset: 0;
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: all;
    }
    #startup-loader .startup-loader-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(10, 10, 12, 0.92);
      backdrop-filter: blur(8px);
    }
    #startup-loader .startup-loader-box {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 0.9rem;
      padding: 1rem 1.25rem;
      border-radius: 1rem;
      background: rgba(33, 37, 41, 0.94);
      color: #fff;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
    }
    #startup-loader.startup-loader-hide {
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    #startup-loader .startup-loader-text {
      font-size: 1rem;
      letter-spacing: 0.02em;
    }
  `;
  document.head.appendChild(style);
}

function isAjaxForm(form) {
  return form.classList.contains('ajax-form') || form.dataset.ajax === 'true';
}

function emitEvent(target, name, detail) {
  target.dispatchEvent(new CustomEvent(name, { detail: detail, bubbles: true }));
}

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
        if (row) {
          row.remove();
        }
        return;
      }

      if (form.dataset.refreshOnSuccess === 'true') {
        window.setTimeout(function () {
          window.location.reload();
        }, 1000);
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

document.addEventListener('DOMContentLoaded', function () {
  injectStartupStyles();
  ensureStartupLoader();

  // Auto-hide loader when all resources (images, styles, fonts) are loaded
  // Pages can call hideStartupLoader() manually to hide it earlier
  window.addEventListener('load', function () {
    // Small delay to ensure content is actually visible
    window.setTimeout(function () {
      hideStartupLoader();
    }, 100);
  });

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

  if (data.action === 'delete') {
    return;
  }

  if (form && form.classList && form.classList.contains('ajax-form')) {
    const feedback = form.parentElement ? form.parentElement.querySelector('#ajax-feedback') : null;
    if (feedback && data.message) {
      feedback.innerHTML = '<div class="alert alert-success" role="alert">' + data.message + '</div>';
      return;
    }
  }

  if (data.message) {
    alert(data.message);
  }
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
