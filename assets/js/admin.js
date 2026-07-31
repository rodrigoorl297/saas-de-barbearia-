(() => {
  function ensurePreview(input) {
    let wrap = input.closest('.js-image-upload');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'js-image-upload';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
    }

    let box = wrap.querySelector('.js-image-preview-box');
    if (!box) {
      box = document.createElement('div');
      box.className = 'js-image-preview-box image-upload-preview';
      box.hidden = true;
      box.innerHTML = [
        '<img class="js-image-preview" alt="Pré-visualização">',
        '<div class="image-upload-meta">',
        '  <strong class="js-image-name"></strong>',
        '  <span class="js-image-size text-secondary"></span>',
        '</div>',
      ].join('');
      wrap.insertBefore(box, input);
    }
    return box;
  }

  function formatBytes(n) {
    if (!n && n !== 0) return '';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function showPreview(input, file) {
    const box = ensurePreview(input);
    const img = box.querySelector('.js-image-preview');
    const name = box.querySelector('.js-image-name');
    const size = box.querySelector('.js-image-size');
    if (!img || !name) return;

    if (img.dataset.objectUrl) {
      URL.revokeObjectURL(img.dataset.objectUrl);
      delete img.dataset.objectUrl;
    }

    if (!file) {
      box.hidden = true;
      img.removeAttribute('src');
      name.textContent = '';
      if (size) size.textContent = '';
      return;
    }

    const url = URL.createObjectURL(file);
    img.dataset.objectUrl = url;
    img.src = url;
    name.textContent = file.name;
    if (size) size.textContent = formatBytes(file.size);
    box.hidden = false;
  }

  document.addEventListener('change', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    const accept = (input.getAttribute('accept') || '').toLowerCase();
    if (accept && !accept.includes('image')) return;

    const file = input.files && input.files[0] ? input.files[0] : null;
    if (file && !file.type.startsWith('image/')) {
      showPreview(input, null);
      return;
    }
    showPreview(input, file);
  });
})();
