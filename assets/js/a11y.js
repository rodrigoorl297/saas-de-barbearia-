(() => {
  'use strict';

  let sequence = 0;
  const nextId = (prefix) => `${prefix}-${Date.now().toString(36)}-${++sequence}`;
  const controlsSelector = 'input:not([type="hidden"]), select, textarea';

  function ensureMainTarget() {
    if (document.getElementById('main-content')) return;
    const target = document.querySelector('main, .login-stage, .login-wrap, .app-container');
    if (!target) return;
    target.id = 'main-content';
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
  }

  function hasAccessibleLabel(control) {
    if (control.hasAttribute('aria-label') || control.hasAttribute('aria-labelledby')) return true;
    if (control.closest('label')) return true;
    const id = control.id;
    return Boolean(id && Array.from(document.querySelectorAll('label[for]')).some((label) => label.htmlFor === id));
  }

  function nearestTextLabel(control) {
    const previous = control.previousElementSibling;
    if (previous?.matches('label, .form-label, .modal-label')) return previous;
    const parent = control.parentElement;
    if (!parent) return null;
    return Array.from(parent.children).find((item) => {
      if (!item.matches?.('label, .form-label, .modal-label')) return false;
      return Boolean(item.textContent.trim()) && item.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_FOLLOWING;
    }) || null;
  }

  function labelControls(root = document) {
    const commonNames = {
      barber_id: 'Profissional',
      birth_date: 'Data de nascimento',
      client_name: 'Nome do cliente',
      client_phone: 'Telefone do cliente',
      move_qty: 'Quantidade movimentada',
      move_type: 'Tipo de movimentação',
      password: 'Senha',
      points: 'Quantidade de pontos',
      reward_id: 'Recompensa',
      status: 'Status do atendimento',
      time: 'Horário'
    };
    root.querySelectorAll(controlsSelector).forEach((control) => {
      if (hasAccessibleLabel(control)) return;
      const label = nearestTextLabel(control);
      if (label?.tagName === 'LABEL') {
        if (!control.id) control.id = nextId('field');
        label.htmlFor = control.id;
        return;
      }
      const fallback = control.getAttribute('placeholder') || control.getAttribute('title') || commonNames[control.name] || control.name?.replace(/_/g, ' ');
      if (fallback) control.setAttribute('aria-label', fallback.replace(/[:.]+$/, ''));
    });
  }

  function describeControls(root = document) {
    root.querySelectorAll(controlsSelector).forEach((control) => {
      const sibling = control.nextElementSibling;
      const help = sibling?.matches('.form-text, .form-help, small')
        ? sibling
        : control.parentElement?.querySelector(':scope > .form-text, :scope > .form-help');
      if (!help || !help.textContent.trim()) return;
      if (!help.id) help.id = nextId('field-help');
      const ids = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      ids.add(help.id);
      control.setAttribute('aria-describedby', Array.from(ids).join(' '));
    });
  }

  function validationMessage(control) {
    const validity = control.validity;
    if (validity.valueMissing) {
      return control.matches('[type="radio"], [type="checkbox"]') ? 'Selecione uma opção.' : 'Preencha este campo.';
    }
    if (validity.typeMismatch) return 'Informe um valor válido.';
    if (validity.patternMismatch) return 'Use o formato solicitado para este campo.';
    if (validity.tooShort) return `Use pelo menos ${control.minLength} caracteres.`;
    if (validity.tooLong) return `Use no máximo ${control.maxLength} caracteres.`;
    if (validity.rangeUnderflow) return `O valor mínimo é ${control.min}.`;
    if (validity.rangeOverflow) return `O valor máximo é ${control.max}.`;
    if (validity.stepMismatch) return 'Informe um valor permitido.';
    return control.validationMessage || 'Revise este campo.';
  }

  function relatedControls(control) {
    if (!control.form || !control.name || !control.matches('[type="radio"], [type="checkbox"]')) return [control];
    return Array.from(control.form.elements).filter((item) => item.name === control.name);
  }

  function removeFieldError(control) {
    const group = relatedControls(control);
    const errorId = group.map((item) => item.dataset.validationErrorId).find(Boolean);
    group.forEach((item) => {
      item.removeAttribute('aria-invalid');
      delete item.dataset.validationErrorId;
      if (errorId) {
        const ids = (item.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== errorId);
        if (ids.length) item.setAttribute('aria-describedby', ids.join(' '));
        else item.removeAttribute('aria-describedby');
      }
    });
    if (errorId) document.getElementById(errorId)?.remove();
  }

  function showFieldError(control) {
    removeFieldError(control);
    const group = relatedControls(control);
    const error = document.createElement('p');
    error.id = nextId('field-error');
    error.className = 'field-error';
    error.setAttribute('role', 'alert');
    error.textContent = validationMessage(control);

    group.forEach((item) => {
      item.setAttribute('aria-invalid', 'true');
      item.dataset.validationErrorId = error.id;
      const ids = new Set((item.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      ids.add(error.id);
      item.setAttribute('aria-describedby', Array.from(ids).join(' '));
    });

    const host = control.matches('[type="radio"], [type="checkbox"]')
      ? control.closest('.booking-slots, .plan-extras-grid, .form-check, fieldset') || control.closest('label') || control
      : control.closest('.input-group, .senha-wrap, .mp-field') || control;
    host.insertAdjacentElement('afterend', error);
  }

  function enhanceDialogs(root = document) {
    root.querySelectorAll('dialog, .modal, .offcanvas').forEach((dialog) => {
      if (!dialog.hasAttribute('aria-labelledby') && !dialog.hasAttribute('aria-label')) {
        const heading = dialog.querySelector('h1, h2, h3, .modal-title, .bb-sheet-title');
        if (heading) {
          if (!heading.id) heading.id = nextId('dialog-title');
          dialog.setAttribute('aria-labelledby', heading.id);
        }
      }
      if (dialog.tagName === 'DIALOG') dialog.setAttribute('aria-modal', 'true');
    });
  }

  function enhanceDisabledLinks(root = document) {
    root.querySelectorAll('a.disabled, a[aria-disabled="true"]').forEach((link) => {
      link.setAttribute('aria-disabled', 'true');
      link.setAttribute('tabindex', '-1');
    });
  }

  function containCustomDialogFocus() {
    const dialog = document.querySelector('[role="dialog"][aria-modal="true"]:not(dialog):not(.modal):not(.offcanvas)');
    if (!dialog) return;
    const focusables = () => Array.from(dialog.querySelectorAll('a[href], button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'));
    const initial = dialog.querySelector('[autofocus], input:not([type="hidden"]):not(:disabled), button:not(:disabled), a[href], [tabindex]:not([tabindex="-1"])');
    requestAnimationFrame(() => initial?.focus({ preventScroll: true }));
    dialog.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      const items = focusables();
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }

  function init() {
    ensureMainTarget();
    labelControls();
    describeControls();
    enhanceDialogs();
    enhanceDisabledLinks();
    containCustomDialogFocus();
  }

  document.addEventListener('invalid', (event) => {
    const control = event.target;
    if (control.matches?.(controlsSelector)) showFieldError(control);
  }, true);

  ['input', 'change'].forEach((type) => {
    document.addEventListener(type, (event) => {
      const control = event.target;
      if (control.matches?.(controlsSelector) && control.validity.valid) removeFieldError(control);
    });
  });

  document.addEventListener('click', (event) => {
    const disabledLink = event.target.closest?.('a[aria-disabled="true"], a.disabled');
    if (disabledLink) event.preventDefault();

    const eyeButton = event.target.closest?.('.eye-btn');
    if (eyeButton) {
      requestAnimationFrame(() => {
        const input = eyeButton.closest('.senha-wrap')?.querySelector('input');
        const visible = input?.type === 'text';
        eyeButton.setAttribute('aria-pressed', visible ? 'true' : 'false');
        eyeButton.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
      });
    }
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
