(function () {
  const root = document.querySelector('.conta-page');
  if (!root) return;

  const mpReady = root.dataset.mpReady === '1';
  const publicKey = root.dataset.mpPublic || '';
  const saveUrl = root.dataset.saveCardUrl || '';
  const chargeUrl = root.dataset.chargeUrl || '';

  let selectedPlanId = null;
  let cardForm = null;
  let mp = null;

  function showErr(el, msg) {
    if (!el) return;
    el.style.display = msg ? 'block' : 'none';
    el.textContent = msg || '';
  }

  document.querySelectorAll('.js-assinar').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!mpReady) {
        alert('Pagamentos ainda não configurados. Peça ao dono para inserir as chaves do Mercado Pago.');
        return;
      }
      selectedPlanId = btn.dataset.planId;
      const title = document.getElementById('pay-plan-title');
      const sub = document.getElementById('pay-plan-sub');
      if (title) title.textContent = 'Assinar ' + (btn.dataset.planName || 'plano');
      if (sub) {
        const price = Number(btn.dataset.planPrice || 0).toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL',
        });
        sub.textContent = 'Cobrança real de ' + price + ' no cartão selecionado.';
      }
      document.getElementById('pay-plan-modal')?.showModal();
    });
  });

  const confirmPay = document.getElementById('btn-confirm-pay');
  if (confirmPay) {
    confirmPay.addEventListener('click', async () => {
      const err = document.getElementById('mp-pay-error');
      showErr(err, '');
      const picked = document.querySelector('input[name="pay_card_id"]:checked');
      if (!picked || !selectedPlanId) {
        showErr(err, 'Selecione um cartão.');
        return;
      }
      confirmPay.disabled = true;
      confirmPay.textContent = 'Processando...';
      try {
        const res = await fetch(chargeUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            plan_id: Number(selectedPlanId),
            card_id: Number(picked.value),
          }),
        });
        const data = await res.json();
        if (!data.ok) {
          showErr(err, data.error || 'Falha no pagamento.');
          return;
        }
        window.location.reload();
      } catch (e) {
        showErr(err, 'Erro de conexão ao cobrar.');
      } finally {
        confirmPay.disabled = false;
        confirmPay.textContent = 'Pagar e assinar';
      }
    });
  }

  const openAdd = document.getElementById('btn-open-add-card');
  const modal = document.getElementById('add-card-modal');
  if (openAdd && modal && mpReady && publicKey && window.MercadoPago) {
    openAdd.addEventListener('click', () => {
      modal.showModal();
      initCardForm();
    });
  }

  function initCardForm() {
    if (cardForm || !window.MercadoPago) return;
    mp = new MercadoPago(publicKey, { locale: 'pt-BR' });
    cardForm = mp.cardForm({
      amount: '1.00',
      iframe: true,
      form: {
        id: 'form-checkout',
        cardNumber: {
          id: 'form-checkout__cardNumber',
          placeholder: 'Número do cartão',
        },
        expirationDate: {
          id: 'form-checkout__expirationDate',
          placeholder: 'MM/AA',
        },
        securityCode: {
          id: 'form-checkout__securityCode',
          placeholder: 'CVV',
        },
        cardholderName: {
          id: 'form-checkout__cardholderName',
          placeholder: 'Nome impresso',
        },
        issuer: {
          id: 'form-checkout__issuer',
          placeholder: 'Banco emissor',
        },
        installments: {
          id: 'form-checkout__installments',
          placeholder: 'Parcelas',
        },
        identificationType: {
          id: 'form-checkout__identificationType',
        },
        identificationNumber: {
          id: 'form-checkout__identificationNumber',
          placeholder: 'CPF',
        },
      },
      callbacks: {
        onFormMounted(error) {
          if (error) console.warn('MP form', error);
        },
        onSubmit(event) {
          if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
          }
          const errBox = document.getElementById('mp-card-error');
          const btn = document.getElementById('btn-save-card');
          showErr(errBox, '');
          const data =
            event && event.token
              ? event
              : cardForm.getCardFormData();
          if (!data || !data.token) {
            showErr(errBox, 'Não foi possível tokenizar o cartão. Verifique os dados.');
            return;
          }
          if (btn) {
            btn.disabled = true;
            btn.textContent = 'Salvando...';
          }
          fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              token: data.token,
              holder: data.cardholderName || '',
              email: document.getElementById('form-checkout__email')?.value || '',
              payment_method_id: data.paymentMethodId || '',
            }),
          })
            .then((r) => r.json())
            .then((json) => {
              if (!json.ok) {
                showErr(errBox, json.error || 'Falha ao salvar cartão.');
                return;
              }
              window.location.href = window.location.pathname + '#cartoes';
              window.location.reload();
            })
            .catch(() => showErr(errBox, 'Erro de conexão ao salvar cartão.'))
            .finally(() => {
              if (btn) {
                btn.disabled = false;
                btn.textContent = 'Salvar cartão';
              }
            });
        },
        onError(error) {
          const errBox = document.getElementById('mp-card-error');
          showErr(errBox, (error && error.message) || 'Erro no formulário do cartão.');
        },
      },
    });
  }
})();
