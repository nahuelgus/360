<?php
require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor','vendedor']);
$code = trim($_GET['code'] ?? '');
$r = DB::one("SELECT sr.*, c.name AS cname, c.dni AS cdni
                FROM sale_refunds sr
           LEFT JOIN customers c ON c.id=sr.customer_id
               WHERE sr.code=? AND sr.type='voucher' LIMIT 1", [$code]);
if(!$r){ die('Voucher no encontrado'); }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Voucher</title>
<style>
@media print {.no-print{display:none} body{margin:0}}
body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
.ticket{width:280px;padding:8px}
.h{font-weight:700;text-align:center;margin:6px 0}
.row{display:flex;justify-content:space-between;margin:4px 0}
.small{font-size:12px;color:#444} hr{border:0;border-top:1px dashed #aaa;margin:6px 0}
.code{font-family:ui-monospace,Consolas,monospace;font-size:13px}
</style></head><body>
<div class="ticket">
  <div class="h">Voucher de Devolución</div>
  <div class="small">Fecha: <?= htmlspecialchars($r['created_at']) ?></div>
  <?php if($r['cname']): ?><div class="small">Cliente: <?= htmlspecialchars($r['cname']) ?> (DNI <?= htmlspecialchars($r['cdni']) ?>)</div><?php endif; ?>
  <hr>
  <div class="row"><span>Monto inicial</span><span>$<?= number_format($r['initial_amount'],2,',','.') ?></span></div>
  <div class="row"><span>Disponible</span><span><b>$<?= number_format($r['remaining_amount'],2,',','.') ?></b></span></div>
  <div class="row"><span>Código</span><span class="code"><?= htmlspecialchars($r['code']) ?></span></div>
  <?php if($r['reason']): ?><div class="small">Motivo: <?= htmlspecialchars($r['reason']) ?></div><?php endif; ?>
  <div class="no-print" style="margin-top:10px"><button onclick="window.print()">Imprimir</button></div>
</div>
</body></html>
