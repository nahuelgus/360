<?php
require_once __DIR__.'/../../api/auth/require_login.php';
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];

$branch_id = $_SESSION['branch_id'] ?? null;
if(!$branch_id){ echo 'Seleccioná una sucursal en el navbar.'; exit; }

$registers   = DB::all("SELECT id,name FROM cash_registers WHERE branch_id=? ORDER BY name",[$branch_id]);
$register_id = $_SESSION['register_id'] ?? null;
$shift = null;
if ($register_id) {
  $shift = DB::one("SELECT * FROM cash_shifts WHERE register_id=? AND status='open' ORDER BY id DESC LIMIT 1",[$register_id]);
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Caja</title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Cajas de la sucursal</h3>
    <form method="post" action="<?= $BASE ?>/public/cash/select_register.php">
      <select class="input" name="register_id" onchange="this.form.submit()">
        <option value="">— Caja —</option>
        <?php foreach($registers as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ($register_id==$r['id']?'selected':'') ?>><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn" href="<?= $BASE ?>/public/cash/registers.php">Administrar cajas</a>
    </form>
  </div>

  <div class="card"><h3>Turno</h3>
    <?php if(!$register_id): ?>
      <div class="small">Elegí una caja.</div>
    <?php elseif(!$shift): ?>
      <form method="post" action="<?= $BASE ?>/public/cash/open_shift.php">
        <input type="hidden" name="register_id" value="<?= $register_id ?>">
        <input class="input" name="opening_amount" placeholder="Dinero inicial en caja" inputmode="decimal" required>
        <button class="btn primary" style="margin-top:8px">Abrir turno</button>
      </form>
    <?php else: ?>
      <div>Turno abierto desde: <b><?= htmlspecialchars($shift['opened_at']) ?></b> – Monto inicial: <b>$<?= number_format($shift['opening_amount'],2,',','.') ?></b></div>
      <div style="margin-top:8px">
        <form style="display:inline" method="post" action="<?= $BASE ?>/public/cash/movement_post.php">
          <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
          <select class="input" name="kind" required>
            <option value="income">Ingreso</option>
            <option value="expense">Egreso</option>
          </select>
          <input class="input" name="amount" placeholder="Monto" inputmode="decimal" required>
          <input class="input" name="reason" placeholder="Motivo" required>
          <button class="btn">Registrar</button>
        </form>
        <form style="display:inline" method="post" action="<?= $BASE ?>/public/cash/close_shift.php">
          <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
          <input class="input" name="keep_in_drawer_amount" placeholder="Dejar en caja" inputmode="decimal" required>
          <button class="btn accent">Cerrar turno</button>
        </form>
      </div>
      <div style="margin-top:10px">
        <table class="table"><thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Motivo</th></tr></thead><tbody>
        <?php foreach(DB::all("SELECT created_at,kind,amount,reason FROM cash_movements WHERE shift_id=? ORDER BY id DESC",[$shift['id']]) as $m): ?>
          <tr><td><?= htmlspecialchars($m['created_at']) ?></td><td><?= htmlspecialchars($m['kind']) ?></td><td>$<?= number_format($m['amount'],2,',','.') ?></td><td><?= htmlspecialchars($m['reason']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body></html>