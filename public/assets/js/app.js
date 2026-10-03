/* CampusIQ shared browser helpers: config, fetch with CSRF, toasts, small UI behaviours. */
(() => {
  'use strict';

  const configEl = document.getElementById('app-config');
  const config = configEl ? JSON.parse(configEl.textContent) : {};

  const ICONS = {
    check: '<path d="M5 10.5 8.5 14 15 6.5"/>',
    close: '<path d="M5 5l10 10M15 5 5 15"/>',
    alert: '<circle cx="10" cy="10" r="7"/><path d="M10 6.5v4M10 13.5v.01"/>',
    mail: '<rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5 10 11l7-5.5"/>',
  };

  function icon(name, cls = 'size-4', stroke = 1.6) {
    return `<svg class="${cls}" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ''}</svg>`;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function url(path) {
    return (config.baseUrl || '') + path;
  }

  /** JSON fetch with the CSRF header. Throws Error(message) on failure. */
  async function api(path, { method = 'GET', body } = {}) {
    let response;
    try {
      response = await fetch(url(path), {
        method,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': config.csrf || '',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch (e) {
      throw new Error('Could not reach the server. Check your connection and try again.');
    }
    let data = {};
    try {
      data = await response.json();
    } catch (e) {
      data = {};
    }
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Request failed (${response.status}).`);
    }
    return data;
  }

  const toastHost = () => document.getElementById('toasts');

  function dismiss(el) {
    if (!el || !el.isConnected) return;
    el.style.transition = 'opacity .2s, transform .2s';
    el.style.opacity = '0';
    el.style.transform = 'translateY(8px)';
    setTimeout(() => el.remove(), 200);
  }

  /** Small dark toast, like "Record saved". */
  function toast(message, type = 'success', timeout = 5000) {
    const host = toastHost();
    if (!host) return null;
    const dot = { success: 'bg-mail', error: 'bg-pdf', info: 'bg-primary' }[type] || 'bg-mail';
    const glyph = { success: 'check', error: 'close', info: 'alert' }[type] || 'check';
    const el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.className = 'toast-in flex items-center gap-3 rounded-xl bg-side px-4 py-3.5 text-white shadow-[0_12px_30px_rgba(20,22,40,.25)]';
    el.innerHTML = `<span class="flex size-[22px] shrink-0 items-center justify-center rounded-full ${dot}">${icon(glyph, 'size-3 text-white', 2.4)}</span>`
      + `<span class="grow text-[13.5px]">${escapeHtml(message)}</span>`
      + `<button type="button" data-toast-close class="text-[#8f94ad] hover:text-white" aria-label="Dismiss">${icon('close')}</button>`;
    host.appendChild(el);
    if (timeout) setTimeout(() => dismiss(el), timeout);
    return el;
  }

  /** Append a custom element (e.g. the email delivery card) to the toast stack. */
  function card(el, timeout = 0) {
    const host = toastHost();
    if (!host) return null;
    el.classList.add('toast-in');
    host.appendChild(el);
    if (timeout) setTimeout(() => dismiss(el), timeout);
    return el;
  }

  window.CampusIQ = { config, api, url, toast, card, dismiss, icon, escapeHtml };

  // Server-rendered flash toasts close on their own.
  document.querySelectorAll('[data-toast][data-autoclose]').forEach((el) => setTimeout(() => dismiss(el), 5000));

  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) {
      dismiss(close.closest('[role="status"]'));
      return;
    }

    // Landing page mobile menu
    const toggle = event.target.closest('[data-menu-toggle]');
    if (toggle) {
      const menu = document.getElementById(toggle.getAttribute('aria-controls'));
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      menu.hidden = !open;
      return;
    }
    if (event.target.closest('[data-menu-close]')) {
      const btn = document.querySelector('[data-menu-toggle]');
      if (btn) {
        btn.setAttribute('aria-expanded', 'false');
        document.getElementById(btn.getAttribute('aria-controls')).hidden = true;
      }
    }

    // Login demo shortcuts: fill in a seeded account and submit.
    const demo = event.target.closest('[data-demo-login]');
    if (demo) {
      const form = document.querySelector('[data-login-form]');
      form.querySelector('[name="login"]').value = demo.dataset.demoLogin;
      form.querySelector('[name="password"]').value = 'password123';
      form.requestSubmit();
    }
  });

  // Confirm before destructive form posts (data-confirm="message").
  document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
    // Prevent double submits on normal forms.
    if (!event.defaultPrevented && !event.target.hasAttribute('data-no-lock')) {
      const button = event.target.querySelector('button[type="submit"]');
      if (button) setTimeout(() => { button.disabled = true; }, 0);
    }
  });

  // Coming back with the browser's Back button: re-enable buttons locked above.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      document.querySelectorAll('button[type="submit"][disabled]').forEach((b) => { b.disabled = false; });
    }
  });

  // Record form: show the fields for the chosen type (grade / attendance / library).
  const recordForm = document.querySelector('[data-record-form]');
  if (recordForm) {
    const typeSelect = recordForm.querySelector('[data-record-type]');
    const applyType = () => {
      const type = typeSelect.value;
      recordForm.querySelectorAll('[data-show-for]').forEach((el) => {
        el.hidden = !el.dataset.showFor.split(' ').includes(type);
      });
      recordForm.querySelectorAll(`[data-placeholder-${type}]`).forEach((el) => {
        el.placeholder = el.getAttribute(`data-placeholder-${type}`);
      });
      recordForm.dispatchEvent(new CustomEvent('record-form:change'));
    };
    // Show the "Saving emails ... automatically" note only when this type/value fires a trigger.
    const autoNote = recordForm.querySelector('[data-auto-email]');
    if (autoNote) {
      const fires = JSON.parse(autoNote.dataset.autoEmail);
      recordForm.addEventListener('record-form:change', () => {
        const type = typeSelect.value;
        const value = type === 'attendance' ? recordForm.querySelector('[name="value_attendance"]').value
          : type === 'library' ? recordForm.querySelector('[name="value_library"]').value : '';
        autoNote.hidden = !(type === 'grade' ? fires.grade : fires[`${type}:${value}`]);
      });
    }
    typeSelect.addEventListener('change', applyType);
    recordForm.addEventListener('change', (event) => {
      if (event.target !== typeSelect) recordForm.dispatchEvent(new CustomEvent('record-form:change'));
    });
    applyType();
  }

  // Sign up: the email hint follows the chosen role.
  const signup = document.querySelector('[data-signup-form]');
  if (signup) {
    const hint = signup.querySelector('[data-hint="email"]');
    signup.addEventListener('change', (event) => {
      if (event.target.name === 'role' && hint) {
        hint.textContent = event.target.value === 'parent'
          ? 'The guardian email the school has for this student.'
          : 'The student email the school has for you.';
      }
    });
  }
})();
