document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.card-servico');
  const avancar = document.getElementById('botao-avancar-wrapper');
  const form = document.getElementById('form-servicos');

  function refresh() {
    let count = 0;
    let duration = 0;
    let total = 0;
    cards.forEach((card) => {
      const input = card.querySelector('.checkbox-circular-input');
      const selected = Boolean(input?.checked);
      card.classList.toggle('is-selected', selected);
      if (selected) {
        count += 1;
        duration += Number(card.dataset.duration || 0);
        total += Number(card.dataset.price || 0);
      }
    });
    if (avancar) avancar.classList.toggle('show', count > 0);
    const context = document.getElementById('cta-service-context');
    const summaryCount = document.getElementById('service-summary-count');
    const summaryDetails = document.getElementById('service-summary-details');
    const serviceWord = count === 1 ? 'serviço' : 'serviços';
    if (context) context.textContent = count ? `${count} ${serviceWord}` : 'Selecione um serviço';
    if (summaryCount) summaryCount.textContent = count ? `${count} ${serviceWord} selecionado${count === 1 ? '' : 's'}` : 'Nenhum serviço selecionado';
    if (summaryDetails) {
      summaryDetails.textContent = count
        ? `${duration} min · ${total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`
        : '';
    }
  }

  cards.forEach((card) => {
    const input = card.querySelector('.checkbox-circular-input');
    if (!input) return;
    input.addEventListener('change', refresh);
  });

  if (avancar && form) {
    avancar.querySelector('.botao-avancar')?.addEventListener('click', () => {
      form.requestSubmit();
    });
  }

  refresh();
});

document.addEventListener('DOMContentLoaded', () => {
  const track = document.getElementById('plan-catalog-track');
  if (!track) return;

  const origCards = Array.from(track.querySelectorAll('.plan-offer-card'));
  if (origCards.length < 2) return;

  // Remove setas e dots (já escondidos via CSS, mas garante no DOM)
  document.querySelector('.plan-nav-prev')?.remove();
  document.querySelector('.plan-nav-next')?.remove();
  const dotsWrap = document.getElementById('plan-dots');
  if (dotsWrap) dotsWrap.innerHTML = '';

  // Clona todos os cards e adiciona antes e depois para loop infinito
  const clonesBefore = origCards.map(c => { const cl = c.cloneNode(true); cl.setAttribute('aria-hidden','true'); return cl; });
  const clonesAfter  = origCards.map(c => { const cl = c.cloneNode(true); cl.setAttribute('aria-hidden','true'); return cl; });

  clonesBefore.reverse().forEach(cl => track.prepend(cl));
  clonesAfter.forEach(cl => track.append(cl));

  const allCards = Array.from(track.querySelectorAll('.plan-offer-card'));
  const total = allCards.length;
  const n = origCards.length;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function cardCenter(card) {
    return card.offsetLeft + card.offsetWidth / 2;
  }

  function scrollToCard(card, behavior = 'smooth') {
    const left = card.offsetLeft - (track.clientWidth - card.offsetWidth) / 2;
    track.scrollTo({ left: Math.max(0, left), behavior: reduceMotion || behavior === 'instant' ? 'auto' : behavior });
  }

  // Começa no primeiro card real (após os clones do início)
  scrollToCard(allCards[n], 'instant');

  function nearestIndex() {
    const mid = track.scrollLeft + track.clientWidth / 2;
    let best = 0, bestDist = Infinity;
    allCards.forEach((card, i) => {
      const d = Math.abs(cardCenter(card) - mid);
      if (d < bestDist) { bestDist = d; best = i; }
    });
    return best;
  }

  function snapToNearest(behavior = 'smooth') {
    const i = nearestIndex();
    scrollToCard(allCards[i], behavior);
  }

  let scrollTimer;
  track.addEventListener('scroll', () => {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      const i = nearestIndex();
      // Chegou nos clones do início → salta para os cards reais do fim
      if (i < n) {
        const target = i + n;
        scrollToCard(allCards[target], 'instant');
      }
      // Chegou nos clones do fim → salta para os cards reais do início
      else if (i >= n * 2) {
        const target = i - n;
        scrollToCard(allCards[target], 'instant');
      }
    }, 80);
  }, { passive: true });

  // Arrastar com mouse/touch
  let dragging = false, moved = false, startX = 0, startLeft = 0;

  track.addEventListener('pointerdown', (e) => {
    if (e.target.closest('button, a, input, select, textarea, label')) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    dragging = true; moved = false;
    startX = e.clientX; startLeft = track.scrollLeft;
    track.classList.add('is-dragging');
    track.setPointerCapture?.(e.pointerId);
  });
  track.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    const dx = e.clientX - startX;
    if (Math.abs(dx) > 4) moved = true;
    track.scrollLeft = startLeft - dx;
  });
  const endDrag = () => {
    if (!dragging) return;
    dragging = false;
    track.classList.remove('is-dragging');
    snapToNearest('smooth');
  };
  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);
  track.addEventListener('click', (e) => {
    if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
  }, true);
});
