<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart_data'] ?? '[]', true);
    if (is_array($cart) && count($cart) > 0) {
        $total = 0;
        $productNames = [];
        $stock = store_read('stock');
        
        $priceMap = [];
        $nameMap = [];
        foreach ($stock as $s) {
            $priceMap[(int)$s['id']] = (float)$s['price'];
            $nameMap[(int)$s['id']] = $s['name'];
        }

        foreach ($cart as $item) {
            $id = (int)($item['id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            if ($id > 0 && $qty > 0 && isset($priceMap[$id])) {
                $subtotal = $priceMap[$id] * $qty;
                $total += $subtotal;
                $productNames[] = $qty . 'x ' . $nameMap[$id];
                
                stock_consume($id, $qty, 'out_venda', (int)$user['id'], 'Venda Frente de Caixa (PDV)', false);
            }
        }
        
        if ($total > 0) {
            save_cash_entry([
                'type' => 'entrada',
                'category' => 'pdv',
                'description' => 'Venda PDV: ' . implode(', ', $productNames),
                'amount' => $total,
                'created_by' => (int)$user['id'],
            ]);
            flash('success', 'Venda finalizada com sucesso!');
        } else {
            flash('warning', 'Carrinho vazio ou itens inválidos.');
        }
    } else {
        flash('danger', 'Nenhum item selecionado.');
    }
    
    redirect(url('dono/pdv.php'));
}

$items = store_read('stock');
$produtos = array_filter($items, fn($i) => (int)$i['qty'] > 0 && (float)$i['price'] > 0);
usort($produtos, fn($a, $b) => strcmp($a['name'], $b['name']));

admin_layout_start('Frente de Caixa (PDV)', 'dono', 'pdv');
?>
<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="stock-panel">
      <div class="stock-panel-head">
        <div>
          <strong>Produtos disponíveis para venda</strong>
          <span> · <?= count($produtos) ?> item(ns)</span>
        </div>
      </div>
      <?php if (!$produtos): ?>
        <div class="stock-empty">
          <p>Nenhum produto em estoque disponível para venda.</p>
          <a href="<?= e(url('dono/estoque.php')) ?>" class="btn btn-accent">Ir para Estoque</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table stock-table align-middle mb-0">
            <thead>
              <tr>
                <th class="col-prod">Produto</th>
                <th class="col-num">Disponível</th>
                <th class="col-money">Preço</th>
                <th class="col-actions text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($produtos as $p): ?>
              <tr>
                <td>
                  <div class="stock-prod">
                    <span class="stock-prod-mark"><?= e(strtoupper(str_cut($p['name'], 0, 1))) ?></span>
                    <div>
                      <strong><?= e($p['name']) ?></strong>
                      <div class="stock-sku"><?= e($p['sku'] ?: '—') ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="stock-qty"><?= (int)$p['qty'] ?></span></td>
                <td><?= e(money((float)$p['price'])) ?></td>
                <td>
                  <div class="stock-actions">
                    <button type="button" class="btn btn-sm btn-accent" onclick="addToCart(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>', <?= (float)$p['price'] ?>, <?= (int)$p['qty'] ?>)">+ Adicionar</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="stock-panel sticky-top" style="top: 2rem;">
      <div class="stock-panel-head">
        <div>
          <strong>Carrinho</strong>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" onclick="clearCart()" id="btn-clear" style="display:none;">Limpar</button>
      </div>
      <div class="p-3">
        <ul id="cart-list" class="list-unstyled mb-3 vstack gap-2">
          <li class="text-secondary text-center py-4" id="empty-cart-msg">Carrinho vazio</li>
        </ul>
        <hr style="opacity: 0.1;">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <span class="fw-bold">Total</span>
          <span class="fw-bold fs-4 text-success" id="cart-total">R$ 0,00</span>
        </div>
        
        <form method="post" id="checkout-form">
          <input type="hidden" name="cart_data" id="cart-data-input" value="[]">
          <button type="submit" class="btn btn-accent w-100 py-3 fs-6" id="btn-checkout" disabled>Finalizar Venda</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
let cart = [];

function formatMoney(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function updateCartUI() {
    const list = document.getElementById('cart-list');
    const totalEl = document.getElementById('cart-total');
    const btnCheckout = document.getElementById('btn-checkout');
    const btnClear = document.getElementById('btn-clear');
    const inputData = document.getElementById('cart-data-input');
    
    list.innerHTML = '';
    let total = 0;
    
    if (cart.length === 0) {
        list.innerHTML = '<li class="text-secondary text-center py-4">Carrinho vazio</li>';
        btnCheckout.disabled = true;
        btnClear.style.display = 'none';
    } else {
        btnCheckout.disabled = false;
        btnClear.style.display = 'block';
        cart.forEach((item, index) => {
            const subtotal = item.qty * item.price;
            total += subtotal;
            
            const li = document.createElement('li');
            li.className = 'd-flex justify-content-between align-items-center p-2 rounded';
            li.style.background = 'rgba(255,255,255,0.02)';
            li.style.border = '1px solid rgba(255,255,255,0.05)';
            
            li.innerHTML = `
                <div class="d-flex flex-column" style="flex:1; min-width:0;">
                    <span class="fw-bold text-truncate" style="font-size:0.9rem;">${item.name}</span>
                    <span class="text-secondary small" style="font-size:0.8rem;">${item.qty}x ${formatMoney(item.price)}</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold" style="font-size:0.9rem;">${formatMoney(subtotal)}</span>
                    <button type="button" class="btn btn-sm btn-ghost p-1 text-danger border-0" onclick="removeFromCart(${index})" title="Remover">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>
                    </button>
                </div>
            `;
            list.appendChild(li);
        });
    }
    
    totalEl.innerText = formatMoney(total);
    inputData.value = JSON.stringify(cart);
}

function addToCart(id, name, price, maxQty) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty < maxQty) {
            existing.qty++;
        } else {
            alert('Quantidade máxima em estoque atingida para este produto.');
        }
    } else {
        if (maxQty > 0) {
            cart.push({ id, name, price, qty: 1, maxQty });
        }
    }
    updateCartUI();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartUI();
}

function clearCart() {
    cart = [];
    updateCartUI();
}

document.getElementById('checkout-form').addEventListener('submit', (e) => {
    if (cart.length === 0) {
        e.preventDefault();
        alert('Adicione produtos ao carrinho antes de finalizar.');
    }
});
</script>
<?php admin_layout_end(); ?>
