<?php
// /360/app/public/sales/view.php
require_once __DIR__.'/../../api/auth/require_login.php';
require_once __DIR__.'/../../lib/db.php'; // <-- ESTA LÍNEA FALTABA, CAUSABA LA PÁGINA EN BLANCO

$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];
$id = intval($_GET['id'] ?? 0);

$sale = DB::one("
  SELECT s.*,
         b.name AS branch_name, b.address AS branch_address,
         so.name AS society_name, so.tax_id AS society_cuit,
         u.name  AS user_name,
         c.dni   AS customer_dni, c.name AS customer_name, c.email AS customer_email
    FROM sales s
LEFT JOIN branches   b  ON b.id  = s.branch_id
LEFT JOIN societies  so ON so.id = s.society_id
LEFT JOIN users      u  ON u.id  = s.user_id
LEFT JOIN customers  c  ON c.id  = s.customer_id
   WHERE s.id = ?", [$id]);

if (!$sale) { die('Venta no encontrada'); }

$items = DB::all("SELECT si.*, p.name FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?", [$id]);
$pays  = DB::all("SELECT sp.*, pm.name AS method_name FROM sale_payments sp JOIN payment_methods pm ON pm.id = sp.payment_method_id WHERE sp.sale_id = ?", [$id]);

function money($n){ return '$' . number_format((float)$n, 2, ',', '.'); }

$isFiscal = ($sale['arca_status'] === 'sent' && !empty($sale['cae']));
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isFiscal ? 'Factura' : 'Ticket' ?> Venta #<?= $sale['id'] ?></title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/print-90mm.css" media="print">
<style>
  .wrap{max-width:800px;margin:16px auto;padding:0 16px}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .fiscal-header{background:#fafafa;border:1px solid #eee;padding:10px;border-radius:8px;display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .fiscal-box{border:1px solid #ccc;padding:8px;border-radius:4px;}
  .total-row{font-weight:bold;font-size:1.1rem;}
  @media print {.no-print{display:none}}
</style>
</head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>

<div class="wrap">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div>
        <h2 style="margin:0;">Venta #<?= $sale['id'] ?></h2>
        <p style="margin:4px 0;">Sucursal: <?= htmlspecialchars($sale['branch_name']) ?></p>
      </div>
      <div class="no-print actions">
        <button class="btn" onclick="window.print()">Imprimir</button>
        <?php if($sale['customer_email']): ?>
          <button class="btn" onclick="sendMail()">Enviar por Mail</button>
        <?php endif; ?>
      </div>
    </div>
    <hr>
    
    <?php if ($isFiscal): ?>
      <div class="fiscal-header">
        <div>
          <h4><?= htmlspecialchars($sale['society_name']) ?></h4>
          <p>CUIT: <?= htmlspecialchars($sale['society_cuit']) ?></p>
          <p><?= htmlspecialchars($sale['branch_address']) ?></p>
        </div>
        <div style="text-align:right;">
          <h3>FACTURA <?= htmlspecialchars($sale['cbte_letter']) ?></h3>
          <p>Punto de Venta: <?= str_pad((string)$sale['pos_number'], 5, '0', STR_PAD_LEFT) ?></p>
          <p>Comprobante Nro: <?= str_pad((string)$sale['cbte_number'], 8, '0', STR_PAD_LEFT) ?></p>
          <p>Fecha: <?= date('d/m/Y', strtotime($sale['created_at'])) ?></p>
        </div>
      </div>
      
      <div class="fiscal-box" style="margin-top:16px;">
        <h4>Cliente</h4>
        <p><b>Nombre:</b> <?= htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final') ?></p>
        <p><b>DNI/CUIT:</b> <?= htmlspecialchars($sale['customer_dni'] ?? 'N/A') ?></p>
      </div>

    <?php else: ?>
      <p><b>Tipo de Comprobante:</b> TICKET X (No fiscal)</p>
      <p><b>Cliente:</b> <?= htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final') ?></p>
    <?php endif; ?>

    <h4 style="margin-top:20px;">Detalle de la venta</h4>
    <table class="table">
      <thead><tr><th>Producto</th><th>Cant.</th><th style="text-align:right;">P. Unit</th><th style="text-align:right;">Total</th></tr></thead>
      <tbody>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['name']) ?></td>
          <td><?= (float)$it['qty'] ?></td>
          <td style="text-align:right;"><?= money($it['unit_price']) ?></td>
          <td style="text-align:right;"><?= money($it['qty'] * $it['unit_price'] * (1-($it['discount_pct']??0)/100)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td colspan="3" style="text-align:right;">TOTAL:</td>
          <td style="text-align:right;"><?= money($sale['total']) ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if ($isFiscal): ?>
      <div class="fiscal-header" style="margin-top:16px;">
          <div>
              <p><b>CAE N°:</b> <?= htmlspecialchars($sale['cae']) ?></p>
              <p><b>Fecha Vto. CAE:</b> <?= date('d/m/Y', strtotime($sale['cae_due'])) ?></p>
          </div>
          <div style="text-align:right;">
              <p>QR AFIP (datos)</p>
          </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
async function sendMail(){
  const defaultEmail = <?= json_encode($sale['customer_email'] ?? '') ?>;
  const email = prompt('Enviar comprobante a:', defaultEmail);
  if (!email) return;

  // Lógica para enviar mail (próximo paso)
  alert('Funcionalidad de envío de mail en desarrollo.');
}
</script>
</body></html>