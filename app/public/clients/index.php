<?php require_once __DIR__.'/../../lib/db.php'; // sin login
$BASE='/360/app'; $dni=trim($_GET['dni']??''); $msg='';
$c=null; $points=0;
if($dni!==''){
  $c=DB::one("SELECT * FROM customers WHERE dni=? LIMIT 1",[$dni]);
  if($c){
    $r=DB::one("SELECT COALESCE(SUM(points),0) AS p FROM loyalty_points_ledger WHERE customer_id=?",[$c['id']]);
    $points=intval($r['p']);
  }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clientes – Natural Dietéticas</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
</head><body>
<div class="container">
  <div class="card"><h3>Consulta / Registro de cliente</h3>
    <form method="get">
      <input class="input" name="dni" placeholder="DNI" value="<?= htmlspecialchars($dni) ?>">
      <button class="btn">Buscar</button>
    </form>
  </div>

  <?php if($dni && $c): ?>
    <div class="card"><h3>¡Hola, <?= htmlspecialchars($c['name']) ?>!</h3>
      <div>Puntos acumulados: <b><?= $points ?></b></div>
    </div>
  <?php elseif($dni): ?>
    <div class="card"><h3>Registro</h3>
      <form method="post" action="<?= $BASE ?>/api/customers/register.php">
        <div class="row">
          <input class="input col-6" name="dni" value="<?= htmlspecialchars($dni) ?>" placeholder="DNI" required>
          <input class="input col-6" name="name" placeholder="Nombre y apellido" required>
          <input class="input col-6" name="email" placeholder="Email">
          <input class="input col-6" name="phone" placeholder="Teléfono">
          <input class="input col-6" name="address" placeholder="Dirección">
          <input class="input col-6" name="birthdate" type="date" placeholder="Fecha de nacimiento">
          <div class="col-12">Preferencias: <label><input type="checkbox" name="diet_flags[]" value="celiaquia"> Celiaquía</label>
          <label><input type="checkbox" name="diet_flags[]" value="diabetes"> Diabetes</label>
          <label><input type="checkbox" name="diet_flags[]" value="keto"> Keto</label>
          <label><input type="checkbox" name="diet_flags[]" value="otras"> Otras</label></div>
          <label class="col-12"><input type="checkbox" name="terms" required> Acepto términos y condiciones</label>
        </div>
        <div class="sticky-actions"><button class="btn primary">Registrarme</button></div>
      </form>
    </div>
  <?php endif; ?>
</div>
</body></html>
