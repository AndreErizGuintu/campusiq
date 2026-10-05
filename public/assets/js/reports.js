/*
 * API 3: PDF Reports. POSTs to /api/reports; the server builds the HTML, calls PDFShift (key stays server side)
 * and stores the file. This script shows the "Generating…" dialog and the result
 * (a card on the staff page, a dialog for students and parents).
 */
(() => {
  'use strict';

  const { api, toast, escapeHtml, icon } = window.CampusIQ;
  const modal = document.querySelector('[data-report-modal]');
  if (!modal) return;

  const progress = modal.querySelector('[data-report-progress]');
  const progressHtml = progress.innerHTML;
  const variantPortal = !document.querySelector('[data-report-form]');
  let controller = null;
  let timers = [];
  let lastFocus = null;
  let reloadOnClose = false; // portal: the reports list changed

  const STEP_ORDER = ['records', 'build', 'convert', 'ready'];
  const DOT = {
    active: '<span class="spin"></span>',
    done: `<span class="flex size-[22px] items-center justify-center rounded-full bg-mail text-white">${icon('check', 'size-3', 2.6)}</span>`,
    fail: `<span class="flex size-[22px] items-center justify-center rounded-full bg-pdf text-white">${icon('close', 'size-3', 2.6)}</span>`,
  };

  function setStep(key, state, text) {
    const li = progress.querySelector(`[data-report-step="${key}"]`);
    if (!li) return;
    const dot = li.querySelector('[data-dot]');
    li.className = `flex items-center gap-3 text-[13.5px] ${state === 'active' ? 'font-medium text-primary' : state === 'fail' ? 'text-pdf' : state === 'done' ? 'text-ink' : 'text-faint'}`;
    dot.className = `flex size-[22px] shrink-0 items-center justify-center rounded-full${state === 'todo' ? ' border-[1.5px] border-[#d3d7e2]' : ''}`;
    dot.innerHTML = DOT[state] || '';
    if (text) li.querySelector('[data-text]').textContent = text;
  }

  function setBar(percent, stepIndex) {
    progress.querySelector('[data-report-bar]').style.width = `${percent}%`;
    progress.querySelector('[data-report-percent]').textContent = `${percent}%`;
    progress.querySelector('[data-report-step-label]').textContent = `Step ${stepIndex} of 4`;
  }

  const dialog = modal.querySelector('[role="dialog"]');

  function open(studentLabel) {
    progress.innerHTML = progressHtml;
    if (variantPortal) dialog.classList.replace('max-w-[640px]', 'max-w-[480px]');
    progress.querySelector('[data-report-cancel]').addEventListener('click', close);
    if (!variantPortal) {
      progress.querySelector('[data-report-subtitle]').textContent = studentLabel || '';
      const previewStudent = modal.querySelector('[data-preview-student]');
      if (previewStudent) previewStudent.textContent = studentLabel || '';
    }
    lastFocus = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    progress.querySelector('[data-report-cancel]').focus();

    setBar(8, 1);
    timers = [
      setTimeout(() => { setStep('records', 'done'); setStep('build', 'active'); setBar(30, 2); }, 450),
      setTimeout(() => { setStep('build', 'done'); setStep('convert', 'active'); setBar(55, 3); }, 950),
      setTimeout(() => setBar(72, 3), 1800),
      setTimeout(() => setBar(86, 3), 3500),
    ];
  }

  function close() {
    if (controller) controller.abort();
    controller = null;
    timers.forEach(clearTimeout);
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
    if (reloadOnClose) window.location.reload();
  }

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });

  function fail(message) {
    timers.forEach(clearTimeout);
    STEP_ORDER.forEach((key) => {
      const li = progress.querySelector(`[data-report-step="${key}"]`);
      if (li && li.querySelector('.spin')) setStep(key, 'fail');
    });
    const box = progress.querySelector('[data-report-error]');
    box.textContent = message;
    box.classList.remove('hidden');
    progress.querySelector('[data-report-cancel]').textContent = 'Close';
  }

  /** POST /api/reports with the abort-able fetch (Cancel stops waiting). */
  async function generate(body, studentLabel) {
    open(studentLabel);
    controller = new AbortController();
    const started = performance.now();
    try {
      const response = await fetch(`${window.CampusIQ.config.baseUrl}/api/reports`, {
        method: 'POST',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': window.CampusIQ.config.csrf },
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.ok) throw new Error(data.error || `Request failed (${response.status}).`);

      // Let the first two steps finish visibly even when the server was quick.
      const wait = Math.max(0, 1000 - (performance.now() - started));
      await new Promise((resolve) => setTimeout(resolve, wait));
      timers.forEach(clearTimeout);
      setStep('records', 'done', `Pulled ${data.report.record_count} records`);
      setStep('build', 'done');
      setStep('convert', 'done', data.report.mode === 'live' ? 'Converted to PDF' : 'Saved the print-ready page (demo)');
      setStep('ready', 'done', 'Ready to download');
      setBar(100, 4);
      controller = null;
      return data;
    } catch (error) {
      if (error.name === 'AbortError') return null;
      fail(error.message);
      controller = null;
      return null;
    }
  }

  // ---------------------------------------------------------------- staff page
  const form = document.querySelector('[data-report-form]');
  if (form) {
    const studentSelect = form.querySelector('[name="student_id"]');
    const errorBox = form.querySelector('[data-form-error]');

    form.addEventListener('change', (event) => {
      if (event.target.name === 'report_type') {
        const includes = event.target.dataset.includes.split(',');
        form.querySelectorAll('[name="sections[]"]').forEach((box) => {
          if (box.value !== 'notes') box.checked = includes.includes(box.value);
        });
      }
      if (event.target === studentSelect) {
        // Show the selected student's latest report.
        window.location.href = `${window.CampusIQ.config.baseUrl}/reports?student=${studentSelect.value}`;
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const sections = [...form.querySelectorAll('[name="sections[]"]:checked')].map((b) => b.value);
      if (!sections.some((s) => s !== 'notes')) {
        errorBox.textContent = 'Include at least grades, attendance or library.';
        errorBox.classList.remove('hidden');
        return;
      }
      errorBox.classList.add('hidden');
      const option = studentSelect.selectedOptions[0];
      const data = await generate({
        student_id: studentSelect.value,
        report_type: form.querySelector('[name="report_type"]:checked').value,
        date_from: form.querySelector('[name="date_from"]').value,
        date_to: form.querySelector('[name="date_to"]').value,
        sections,
      }, `${option.dataset.name} · ${option.dataset.meta}`);
      if (!data) return;

      setTimeout(() => {
        close();
        document.querySelector('[data-report-slot]').innerHTML = data.result_html;
        const list = document.querySelector('[data-report-list]');
        list.insertAdjacentHTML('afterbegin', data.row_html);
        document.querySelector('[data-report-empty]')?.remove();
        const week = document.querySelector('[data-report-week]');
        if (week) week.textContent = Number(week.textContent) + 1;
        toast(data.report.mode === 'live' ? 'PDF ready' : 'Report ready (demo: print-ready page)');
        document.querySelector('[data-report-result]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 600);
    });
  }

  // ---------------------------------------------------------------- student / parent portal
  document.querySelectorAll('[data-portal-report]').forEach((button) => {
    button.addEventListener('click', async () => {
      const data = await generate({});
      if (!data) return;
      const r = data.report;
      const live = r.mode === 'live';
      setTimeout(() => {
        dialog.classList.replace('max-w-[480px]', 'max-w-[640px]');
        reloadOnClose = true;
        progress.innerHTML = `
          <div class="flex flex-col gap-6 sm:flex-row">
            <div class="relative mx-auto h-[297px] w-[210px] shrink-0 overflow-hidden rounded border border-line bg-white shadow-[0_6px_18px_rgba(20,22,40,.10)] sm:mx-0">
              <iframe src="${escapeHtml(r.preview_url)}" title="Preview of your report" tabindex="-1"
                class="pointer-events-none absolute top-0 left-0 h-[1123px] w-[794px] origin-top-left scale-[.2645] border-0"></iframe>
            </div>
            <div class="flex grow flex-col gap-[18px]">
              <span class="flex size-11 items-center justify-center rounded-full bg-mail/[.12] text-mail">${icon('check', 'size-5', 2.2)}</span>
              <div>
                <h2 id="report-modal-title" class="text-lg font-semibold">Your ${live ? 'PDF' : 'report'} is ready</h2>
                <p class="mt-1.5 text-[13px] leading-normal text-muted">Made just now from your current records, so it matches what's on screen.${live ? '' : ' Demo mode: it opens as a print-ready page you can save as a PDF.'}</p>
              </div>
              <div class="flex items-center gap-3 rounded-[10px] border border-line px-3.5 py-3">
                <span class="flex h-10 w-[34px] shrink-0 items-end justify-center rounded bg-pdf pb-[5px] text-[9px] font-semibold text-white">${live ? 'PDF' : 'HTML'}</span>
                <div class="min-w-0"><div class="truncate text-[13px] font-medium">${escapeHtml(r.file_name)}</div>
                <div class="text-xs text-muted">${r.pages ? `${r.pages} page${r.pages === 1 ? '' : 's'} · ` : ''}${escapeHtml(r.size)}</div></div>
              </div>
              <div class="flex gap-2.5">
                <a href="${escapeHtml(r.download_url)}" class="btn btn-primary h-[42px] px-5" ${live ? '' : 'target="_blank" rel="noopener"'}>${icon('download', 'size-4', 1.7)}Download</a>
                <button type="button" class="btn btn-ghost h-[42px] px-[18px] font-normal text-body" data-report-close>Close</button>
              </div>
            </div>
          </div>`;
        progress.querySelector('[data-report-close]').addEventListener('click', close);
        progress.querySelector('a').focus();
      }, 500);
    });
  });
})();
