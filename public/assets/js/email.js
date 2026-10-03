/*
 * API 2: Email Alerts through the EmailJS browser SDK.
 * - Auto send: after a record is saved, the next page load carries config.pendingEmail; it is sent here,
 *   then the result is POSTed to /api/emails/logs.
 * - Email Alerts page: compose, send, retry failed, toggle automatic triggers.
 * Demo mode (no EmailJS keys): nothing is sent, the email is logged with status "demo".
 */
(() => {
  'use strict';

  const { config, api, card, toast, dismiss, escapeHtml, icon } = window.CampusIQ;
  const ej = config.emailjs || { demo: true };
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  let sdkReady = false;

  function initSdk() {
    if (!sdkReady && !ej.demo && window.emailjs) {
      window.emailjs.init({ publicKey: ej.publicKey });
      sdkReady = true;
    }
    return sdkReady;
  }

  /** Send one email per recipient (EmailJS allows 1 request per second). */
  async function deliver(email, onStep) {
    const started = performance.now();
    const results = [];
    for (let i = 0; i < email.recipients.length; i += 1) {
      const to = email.recipients[i];
      if (i > 0) await sleep(1100);
      if (ej.demo) {
        await sleep(450);
        results.push({ email: to.email, ok: true });
        onStep(to, 'demo');
        continue;
      }
      try {
        if (!initSdk()) throw new Error('The EmailJS script did not load. Check the internet connection.');
        await window.emailjs.send(ej.serviceId, ej.templateId, {
          to_email: to.email,
          to_name: to.name,
          recipient_role: to.role,
          student_name: email.student_name,
          subject: email.subject,
          message: email.message,
          from_name: 'CampusIQ',
        });
        results.push({ email: to.email, ok: true });
        onStep(to, 'ok');
      } catch (error) {
        const message = (error && (error.text || error.message)) || 'EmailJS error';
        results.push({ email: to.email, ok: false, error: message });
        onStep(to, 'fail', message);
      }
    }
    const failed = results.filter((r) => !r.ok);
    return {
      status: ej.demo ? 'demo' : (failed.length ? 'failed' : 'sent'),
      error: failed.length ? failed.map((f) => `${f.email}: ${f.error}`).join('; ').slice(0, 250) : null,
      delivered: results.filter((r) => r.ok).length,
      seconds: (performance.now() - started) / 1000,
    };
  }

  function logResult(email, outcome) {
    return api('/api/emails/logs', {
      method: 'POST',
      body: {
        log_id: email.log_id || null,
        student_id: email.student_id,
        record_id: email.record_id || null,
        trigger_key: email.trigger_key,
        recipients: email.recipients.map((r) => r.email),
        subject: email.subject,
        message: email.message,
        status: outcome.status,
        error: outcome.error,
      },
    });
  }

  const dot = (state) => {
    if (state === 'done') return `<span class="flex size-[18px] shrink-0 items-center justify-center rounded-full bg-mail text-white">${icon('check', 'size-2.5', 3)}</span>`;
    if (state === 'fail') return `<span class="flex size-[18px] shrink-0 items-center justify-center rounded-full bg-pdf text-white">${icon('close', 'size-2.5', 3)}</span>`;
    if (state === 'skip') return '<span class="flex size-[18px] shrink-0 items-center justify-center rounded-full border-[1.5px] border-[#d3d7e2]"></span>';
    return '<span class="flex size-[18px] shrink-0 items-center justify-center"><span class="spin size-3.5"></span></span>';
  };

  /** The white delivery card from design-ref 08, updated live as each recipient is sent. */
  function deliveryCard(email, { auto = false } = {}) {
    const el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.className = 'flex flex-col gap-3 rounded-xl border border-line bg-white px-[18px] py-4 text-ink shadow-[0_16px_40px_rgba(20,22,40,.16)]';
    const count = email.recipients.length;
    el.innerHTML = `
      <div class="flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-mail/[.12] text-mail" data-card-icon>${icon('mail', 'size-[18px]', 1.8)}</span>
        <div class="min-w-0 grow">
          <div class="text-sm font-semibold" data-card-title>Sending email to ${count} ${count === 1 ? 'person' : 'people'}…</div>
          <div class="mt-0.5 truncate text-[12.5px] text-muted">"${escapeHtml(email.subject)}"</div>
        </div>
        <button type="button" data-toast-close class="text-faint hover:text-ink" aria-label="Dismiss">${icon('close')}</button>
      </div>
      <ol class="flex flex-col gap-2 rounded-lg bg-[#f7f8fb] px-3 py-2.5 text-[12.5px] text-body">
        <li class="flex items-center gap-2.5">${dot('done')}<span>${auto ? 'Saved to the database' : 'Template filled with record data'}</span></li>
        <li class="flex items-center gap-2.5" data-step-handoff>${dot(ej.demo ? 'skip' : 'done')}<span>${ej.demo ? 'Demo mode: EmailJS skipped (no keys in .env)' : 'Email handed to EmailJS'}</span></li>
        ${email.recipients.map((r, i) => `<li class="flex items-center gap-2.5" data-step="${i}">${dot('wait')}<span class="min-w-0 wrap-anywhere">Sending to ${escapeHtml(r.email)}</span></li>`).join('')}
      </ol>
      <div class="flex items-center justify-between gap-3">
        <div class="text-[11.5px] text-faint" data-card-foot>${auto ? 'No one had to press send' : 'Sent from your browser'}</div>
        <a href="${escapeHtml(config.baseUrl)}/emails" class="text-[12.5px] font-medium text-primary hover:underline">View in log</a>
      </div>`;
    card(el);

    return {
      el,
      step(recipient, state, error) {
        const index = email.recipients.indexOf(recipient);
        const li = el.querySelector(`[data-step="${index}"]`);
        if (!li) return;
        const text = state === 'ok' ? `Delivered to ${recipient.email}`
          : state === 'demo' ? `Demo: logged for ${recipient.email}, not sent`
            : `Failed for ${recipient.email}: ${error}`;
        li.innerHTML = `${dot(state === 'fail' ? 'fail' : (state === 'demo' ? 'skip' : 'done'))}<span class="min-w-0 wrap-anywhere">${escapeHtml(text)}</span>`;
      },
      finish(outcome) {
        const title = el.querySelector('[data-card-title]');
        const iconBox = el.querySelector('[data-card-icon]');
        const n = outcome.delivered;
        if (outcome.status === 'sent') {
          title.textContent = `Email sent to ${n} ${n === 1 ? 'person' : 'people'}`;
          iconBox.innerHTML = icon('check', 'size-[18px]', 2.2);
        } else if (outcome.status === 'demo') {
          title.textContent = `Email logged for ${n} ${n === 1 ? 'person' : 'people'} (demo mode)`;
          iconBox.className = 'flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#b45309]/10 text-lib';
        } else {
          title.textContent = 'Email failed';
          iconBox.className = 'flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-pdf/10 text-pdf';
          iconBox.innerHTML = icon('alert', 'size-[18px]', 1.8);
        }
        const foot = el.querySelector('[data-card-foot]');
        foot.textContent = `Took ${outcome.seconds.toFixed(1)} s${auto ? ' · no one had to press send' : ''}`;
        setTimeout(() => dismiss(el), outcome.status === 'failed' ? 15000 : 9000);
      },
    };
  }

  /** Send + log + show progress. Returns the /api/emails/logs response (or null if logging failed). */
  async function send(email, options = {}) {
    const ui = deliveryCard(email, options);
    const outcome = await deliver(email, (r, state, error) => ui.step(r, state, error));
    ui.finish(outcome);
    try {
      return await logResult(email, outcome);
    } catch (error) {
      toast(`The email went out but could not be logged: ${error.message}`, 'error', 8000);
      return null;
    }
  }

  window.CampusIQ.sendEmail = send;

  // ---------------------------------------------------------------- auto send after a record save
  if (config.pendingEmail) {
    send(config.pendingEmail, { auto: true });
  }

  // ---------------------------------------------------------------- Email Alerts page
  const form = document.querySelector('[data-email-form]');
  if (form) {
    const studentSelect = form.querySelector('[name="student_id"]');
    const templateSelect = form.querySelector('[name="template"]');
    const subject = form.querySelector('[name="subject"]');
    const message = form.querySelector('[name="message"]');
    const chips = form.querySelector('[data-recipients]');
    const errorBox = form.querySelector('[data-compose-error]');
    const sendButton = form.querySelector('[data-send-button]');
    const hint = form.querySelector('[data-message-hint]');
    const list = document.querySelector('[data-email-list]');
    let current = null;
    let request = 0;

    const renderChips = () => {
      chips.innerHTML = current.recipients.map((r, i) => `
        <span class="inline-flex h-8 items-center gap-[7px] rounded-full border border-mail/30 bg-mail/[.08] pr-1.5 pl-3 text-[12.5px] text-mail-dark">
          ${icon('mail', 'size-3.5')}${escapeHtml(r.email)}${r.role === 'guardian' ? ' · guardian' : ''}
          ${current.recipients.length > 1 ? `<button type="button" class="flex size-5 items-center justify-center rounded-full hover:bg-mail/15" data-remove-recipient="${i}" aria-label="Remove ${escapeHtml(r.email)}">${icon('close', 'size-3')}</button>` : '<span class="w-1.5"></span>'}
        </span>`).join('');
    };

    const showError = (text) => {
      errorBox.textContent = text;
      errorBox.classList.toggle('hidden', !text);
    };

    async function compose() {
      const mine = ++request;
      sendButton.disabled = true;
      showError('');
      chips.innerHTML = '<span class="text-[12.5px] text-faint">Loading…</span>';
      const params = new URLSearchParams({
        student_id: studentSelect.value,
        template: templateSelect.value,
        to: form.dataset.to || 'both',
        report: form.dataset.report || '',
      });
      try {
        const data = await api(`/api/emails/compose?${params}`);
        if (mine !== request) return;
        current = data.email;
        renderChips();
        subject.value = current.subject;
        message.value = current.message;
        const custom = current.trigger_key === 'custom';
        message.readOnly = !custom;
        hint.textContent = custom ? 'Write your message. It is sent as plain text.' : 'Built from the student\'s latest matching record. Choose "Custom message" to write your own.';
        sendButton.disabled = false;
        if (custom) message.focus();
      } catch (error) {
        if (mine !== request) return;
        current = null;
        chips.innerHTML = '<span class="text-[12.5px] text-faint">No recipients</span>';
        subject.value = '';
        message.value = '';
        showError(error.message);
      }
    }

    studentSelect.addEventListener('change', () => { form.dataset.report = ''; compose(); });
    templateSelect.addEventListener('change', () => { form.dataset.to = 'both'; compose(); });
    chips.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-remove-recipient]');
      if (remove && current) {
        current.recipients.splice(Number(remove.dataset.removeRecipient), 1);
        renderChips();
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!current) return;
      const email = { ...current, subject: subject.value.trim(), message: message.value.trim() };
      if (!email.subject || email.message.length < 5) {
        showError('Add a subject and a message first.');
        return;
      }
      showError('');
      sendButton.disabled = true;
      const original = sendButton.innerHTML;
      sendButton.innerHTML = '<span class="spin spin-light"></span>Sending…';
      const logged = await send(email);
      sendButton.innerHTML = original;
      sendButton.disabled = false;
      if (logged) addToLog(logged);
    });

    compose();

    function addToLog(logged, replaceId) {
      const wrap = document.createElement('div');
      wrap.innerHTML = logged.row_html.trim();
      const row = wrap.firstElementChild;
      const existing = replaceId ? list.querySelector(`[data-email-row="${replaceId}"]`) : null;
      if (existing) existing.replaceWith(row);
      else list.prepend(row);
      document.querySelector('[data-email-empty]')?.remove();
      const last = document.querySelector('[data-email-last]');
      if (last) {
        wrap.innerHTML = logged.last_html.trim();
        last.replaceWith(wrap.firstElementChild);
      }
      const week = document.querySelector('[data-week-count]');
      if (week) week.textContent = logged.week_count;
    }

    // Retry a failed email from the log.
    document.addEventListener('click', async (event) => {
      const retry = event.target.closest('[data-retry]');
      if (!retry) return;
      retry.disabled = true;
      retry.textContent = 'Retrying…';
      try {
        const data = await api(`/api/emails/logs/${retry.dataset.retry}`);
        const logged = await send(data.email);
        if (logged) addToLog(logged, retry.dataset.retry);
      } catch (error) {
        toast(error.message, 'error');
        retry.disabled = false;
        retry.textContent = 'Retry';
      }
    });

    // Automatic trigger switches save straight away.
    document.querySelector('[data-triggers]')?.addEventListener('change', async (event) => {
      const input = event.target;
      if (!input.matches('input[type="checkbox"]')) return;
      try {
        await api('/api/emails/triggers', { method: 'POST', body: { key: input.name, enabled: input.checked } });
        toast(`${input.parentElement.textContent.trim()}: automatic email ${input.checked ? 'on' : 'off'}`);
      } catch (error) {
        input.checked = !input.checked;
        toast(error.message, 'error');
      }
    });
  }
})();
