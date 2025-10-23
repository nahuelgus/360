<?php
require_once __DIR__.'/../../api/auth/require_login.php';
$id = intval($_GET['id'] ?? 0);
$shift = DB::one("SELECT cs.*, r.name AS regname, b.name AS branch, u.name AS uname
                    FROM cash_shifts cs
                    JOIN cash_registers r ON r.id=cs.register_id
                    JOIN branches b ON b.id=r.branch_id
                    JOIN users u ON u.id=cs.user_id_open
                   WHERE cs.id=?",[$id]);
if(!$shift){ die('Comprobante no disponible'); }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Comprobante de cierre</title>
<style>
  @media print { .no-print{display:none} body{margin:0} }
  body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .ticket{width:280px;padding:8px}
  .h{font-weight:700;text-align:center;margin:6px 0}
  .row{display:flex;justify-content:space-between;margin:4px 0}
  .small{font-size:12px;color:#444}
  hr{border:0;border-top:1px dashed #aaa;margin:6px 0}
</style>
</head><body>
<div class="ticket">
  <div class="h">Cierre de Caja</div>
  <div class="small"><?= htmlspecialchars($shift['branch']) ?> – Caja <?= htmlspecialchars($shift['regname']) ?></div>
  <div class="small">Usuario: <?= htmlspecialchars($shift['uname']) ?></div>
  <hr>
  <div class="row"><span>Apertura</span><span>$<?= number_format($shift['opening_amount'],2,',','.') ?></span></div>
  <div class="row"><span>Movimientos netos</span><span>
    $<?= number_format(($shift['closing_amount'] ?? 0) - $shift['opening_amount'],2,',','.') ?></span></div>
  <div class="row"><span>Total en caja</span><span><b>$<?= number_format($shift['closing_amount'],2,',','.') ?></b></span></div>
  <div class="row"><span>Deja en caja</span><span>$<?= number_format($shift['keep_in_drawer_amount'],2,',','.') ?></span></div>
  <div class="row"><span>Entregado</span><span><b>$<?= number_format($shift['delivered_amount'],2,',','.') ?></b></span></div>
  <hr>
  <div class="small">Apertura: <?= htmlspecialchars($shift['opened_at']) ?></div>
  <div class="small">Cierre: <?= htmlspecialchars($shift['closed_at']) ?></div>
  <div style="margin-top:24px;border-top:1px solid #000;height:2px"></div>
  <div class="small" style="margin-top:28px">Firma supervisora: ________________________</div>
  <div class="no-print" style="margin-top:10px"><button onclick="window.print()">Imprimir</button></div>
</div>
</body></html>
