<?php
/** Fragmento da agenda do barbeiro (poll). */
?>
<div class="bb-kpis">
  <div class="bb-kpi"><span>Na fila</span><strong><?= count($pending) ?></strong></div>
  <div class="bb-kpi"><span>Finalizados</span><strong><?= count($done) ?></strong></div>
</div>

<?php if (!$rows): ?>
  <div class="bb-empty">Nenhum cliente neste dia.</div>
<?php else: ?>
  <div class="bb-list">
    <?php foreach ($pending as $a):
      $st = (string)($a['status'] ?? 'agendado');
      $prods = appointment_products($a);
      $basePrice = (float)$a['price'] - appointment_products_total($a);
      if ($basePrice < 0) {
          $basePrice = (float)$a['price'];
      }
    ?>
      <article class="bb-card">
        <div class="bb-card-top">
          <div class="bb-time"><?= e($a['time']) ?></div>
          <span class="bb-status bb-status--<?= e($st) ?>"><?= e(status_label($st)) ?></span>
        </div>
        <div class="bb-card-name"><?= e($a['client_name']) ?></div>
        <div class="bb-card-meta"><?= e($a['service_name']) ?> · <?= e(money($basePrice)) ?></div>
        <?php if ($prods): ?>
          <div class="bb-card-prods">
            <?php foreach ($prods as $pr): ?>
              <span>+ <?= e($pr['name'] ?? 'Produto') ?> x<?= (int)($pr['qty'] ?? 0) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="bb-actions">
          <?php if ($st !== 'confirmado'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="confirmado"><button class="bb-btn bb-btn--ghost" type="submit">Confirmou</button></form>
          <?php endif; ?>
          <button
            type="button"
            class="bb-btn bb-btn--ok"
            data-bs-toggle="offcanvas"
            data-bs-target="#finishSheet"
            data-id="<?= (int)$a['id'] ?>"
            data-client="<?= e($a['client_name']) ?>"
            data-service="<?= e($a['service_name']) ?>"
            data-price="<?= e((string)$basePrice) ?>"
          >Concluir</button>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="faltou"><button class="bb-btn bb-btn--warn" type="submit">Faltou</button></form>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if ($done): ?>
      <h2 class="bb-sec">Já finalizados</h2>
      <?php foreach ($done as $a):
        $prods = appointment_products($a);
      ?>
        <article class="bb-card bb-card--muted">
          <div class="bb-card-top">
            <div class="bb-time"><?= e($a['time']) ?></div>
            <span class="bb-status bb-status--<?= e((string)$a['status']) ?>"><?= e(status_label((string)$a['status'])) ?></span>
          </div>
          <div class="bb-card-name"><?= e($a['client_name']) ?></div>
          <div class="bb-card-meta"><?= e($a['service_name']) ?> · <?= e(money((float)$a['price'])) ?></div>
          <?php if ($prods): ?>
            <div class="bb-card-prods">
              <?php foreach ($prods as $pr): ?>
                <span>+ <?= e($pr['name'] ?? 'Produto') ?> x<?= (int)($pr['qty'] ?? 0) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
