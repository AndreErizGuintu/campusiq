/* API 1: AI Assistant chat. Sends the question to POST /api/ai/ask; the Gemini key never leaves the server. */
(() => {
  'use strict';

  const form = document.querySelector('[data-ai-form]');
  if (!form) return;

  const { api, escapeHtml, icon } = window.CampusIQ;
  const thread = document.querySelector('[data-ai-thread]');
  const input = form.querySelector('[name="question"]');
  const studentInput = form.querySelector('[name="student_id"]');
  const button = form.querySelector('button[type="submit"]');
  let busy = false;

  const scrollToEnd = () => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });

  function append(html) {
    const wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    const el = wrap.firstElementChild;
    thread.appendChild(el);
    return el;
  }

  function bubbleFor(question) {
    return `<div class="max-w-[85%] self-end rounded-[14px_14px_4px_14px] bg-primary px-4 py-3 text-sm leading-normal text-white md:max-w-[520px]">${escapeHtml(question)}</div>`;
  }

  function pendingBubble() {
    return `<div class="flex max-w-[720px] items-start gap-3" role="status">
      <span class="flex size-8 shrink-0 items-center justify-center rounded-[9px] bg-primary/10 text-primary">${icon('alert', 'size-4 opacity-0')}</span>
      <div class="flex items-center gap-2 rounded-[4px_14px_14px_14px] border border-line bg-white px-4 py-3.5 text-[13px] text-muted">
        <span class="typing" aria-hidden="true"><span></span><span></span><span></span></span>Reading the records…
      </div></div>`;
  }

  function errorBubble(message) {
    return `<div class="flex max-w-[720px] items-start gap-3" role="alert">
      <span class="flex size-8 shrink-0 items-center justify-center rounded-[9px] bg-pdf/10 text-pdf">${icon('alert', 'size-4')}</span>
      <div class="rounded-[4px_14px_14px_14px] border border-pdf/25 bg-pdf/[.04] px-4 py-3.5 text-sm text-pdf">${escapeHtml(message)}</div></div>`;
  }

  async function ask(question) {
    question = question.trim();
    if (busy || question.length < 3) {
      if (question.length < 3) input.focus();
      return;
    }
    busy = true;
    button.disabled = true;
    document.querySelector('[data-ai-empty]')?.remove();

    append(bubbleFor(question));
    const pending = append(pendingBubble());
    input.value = '';
    scrollToEnd();

    try {
      const data = await api('/api/ai/ask', {
        method: 'POST',
        body: { question, student_id: studentInput.value || null },
      });
      pending.remove();
      append(data.html);
    } catch (error) {
      pending.remove();
      append(errorBubble(error.message));
      input.value = question;
    } finally {
      busy = false;
      button.disabled = false;
      scrollToEnd();
      input.focus();
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    ask(input.value);
  });

  document.querySelector('[data-ai-suggestions]')?.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-suggestion]');
    if (chip) ask(chip.textContent);
  });

  // Coming from a student's "Summarize" button: ask straight away.
  if (input.value && studentInput.value) {
    ask(input.value);
  } else if (thread.querySelector('[data-ai-answer]')) {
    window.scrollTo(0, document.body.scrollHeight);
  }
})();
