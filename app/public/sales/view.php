<?php
// /360/app/public/sales/view.php
require_once __DIR__.'/../../api/auth/require_login.php';
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];

$id = intval($_GET['id'] ?? 0);
$sale = DB::one("
  SELECT s.*,
         b.name  AS branch,
         so.name AS society,
         u.name  AS uname,
         c.dni   AS cdni,
         c.name  AS cname,
         c.email AS cemail
    FROM sales s
LEFT JOIN branches   b  ON b.id  = s.branch_id
LEFT JOIN societies  so ON so.id = s.society_id
LEFT JOIN users      u  ON u.id  = s.user_id
LEFT JOIN customers  c  ON c.id  = s.customer_id
   WHERE s.id = ?", [$id]);

if (!$sale) { echo 'Venta no encontrada'; exit; }

$items = DB::all("
  SELECT si.qty, si.unit_price, si.discount_pct, p.name
    FROM sale_items si
LEFT JOIN products p ON p.id = si.product_id
   WHERE si.sale_id = ?
", [$id]);

$pays = DB::all("
  SELECT sp.amount, sp.pos_info, pm.name, pm.is_voucher
    FROM sale_payments sp
LEFT JOIN payment_methods pm ON pm.id = sp.payment_method_id
   WHERE sp.sale_id = ?
", [$id]);

function money($n){ return number_format((float)$n, 2, ',', '.'); }
?>
<!doctype html><html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Venta #<?= $sale['id'] ?> – Ticket X</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/print-90mm.css">

<style>
  .wrap{max-width:1000px;margin:16px auto;padding:0 16px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .muted{color:#667}
  .k{color:#234}
  .total{font-weight:800;font-size:1.25rem}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .badge{display:inline-block;padding:.18rem .5rem;border-radius:999px;border:1px solid #e6eaee;background:#f6f9fb;font-size:.85rem}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }
  @media print {.no-print{display:none}}
</style>
</head>
<body>
<?php require __DIR__.'/../../partials/navbar.php'; ?>

<div class="wrap">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
      <div>
        <h2 style="margin:.2rem 0">Ticket X – Venta #<?= $sale['id'] ?></h2>
        <div class="muted">
          <span class="badge"><?= htmlspecialchars($sale['society']) ?></span>
          <span class="badge"><?= htmlspecialchars($sale['branch']) ?></span>
          <span class="badge"><?= htmlspecialchars($sale['doc_type']) ?></span>
        </div>
      </div>
      <div class="no-print actions">
        <button class="btn" onclick="window.print()">Imprimir</button>
        <button class="btn" onclick="sendMail()">Enviar por mail</button>
      </div>
    </div>

    <div class="grid" style="margin-top:10px">
      <div>
        <div class="muted">Fecha</div>
        <div class="k"><?= htmlspecialchars($sale['created_at']) ?></div>
      </div>
      <div>
        <div class="muted">Vendedor/a</div>
        <div class="k"><?= htmlspecialchars($sale['uname'] ?? '—') ?></div>
      </div>
      <div>
        <div class="muted">Cliente</div>
        <div class="k">
          <?php if($sale['customer_id']): ?>
            <?= htmlspecialchars($sale['cname']) ?> (DNI <?= htmlspecialchars($sale['cdni']) ?>)
            <?= $sale['cemail'] ? ' · '.htmlspecialchars($sale['cemail']) : '' ?>
          <?php else: ?>
            — 
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="muted">ARCA</div>
        <div class="k"><?= htmlspecialchars($sale['arca_status'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Ítems</h3>
    <table class="table">
      <thead>
        <tr><th>Producto</th><th style="width:110px">Cant</th><th style="width:140px">P. Unit</th><th style="width:100px">% Desc</th><th style="width:140px">Total</th></tr>
      </thead>
      <tbody>
      <?php
        $t = 0;
        foreach ($items as $it):
          $line = $it['qty'] * $it['unit_price'] * (1 - (floatval($it['discount_pct'] ?? 0)/100));
          $t += $line;
      ?>
        <tr>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><?= $it['qty'] ?></td>
          <td>$<?= money($it['unit_price']) ?></td>
          <td><?= $it['discount_pct'] ?? 0 ?></td>
          <td>$<?= money($line) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="total" style="text-align:right">Total</td>
          <td class="total">$<?= money($sale['total']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Pagos</h3>
    <table class="table">
      <thead><tr><th>Medio</th><th>Detalle</th><th style="width:160px">Monto</th></tr></thead>
      <tbody>
        <?php foreach($pays as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['name']) ?> <?= !empty($p['is_voucher']) ? ' <span class="badge">Voucher</span>' : '' ?></td>
            <td><?= $p['pos_info'] ? htmlspecialchars($p['pos_info']) : '—' ?></td>
            <td>$<?= money($p['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
async function sendMail(){
  const defEmail = <?= json_encode($sale['cemail'] ?? '') ?>;
  const email = prompt('Enviar a (email):', defEmail || '');
  if(!email) return;
  const j = await (await fetch('<?= $BASE ?>/api/sales/email_ticketx.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({sale_id: <?= $sale['id'] ?>, to: email})
  })).json();
  alert(j.ok ? 'Enviado' : ('Error: ' + (j.error || '')));
}
</script>
</body>
</html>