(() => {
  const token = window.__CSRF__;
  if (!token) return;

  function inject(form) {
    if (!(form instanceof HTMLFormElement)) return;
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') return;
    let input = form.querySelector('input[name="_csrf"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      form.appendChild(input);
    }
    input.value = token;
  }

  document.querySelectorAll('form').forEach(inject);
  document.addEventListener('submit', (e) => {
    if (e.target instanceof HTMLFormElement) inject(e.target);
  }, true);
})();
