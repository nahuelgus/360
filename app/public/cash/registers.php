<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; $branch_id=$_SESSION['branch_id']??null; if(!$branch_id){ echo 'Seleccioná una sucursal.'; exit; }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administrar cajas</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body><?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Nueva / Editar caja</h3><form method="post" action="<?= $BASE ?>/public/cash/registers_post.php">
    <input type="hidden" name="id"><input class="input" name="name" placeholder="Nombre de caja" required>
    <div class="sticky-actions"><button class="btn primary">Guardar</button></div></form></div>
  <div class="card"><h3>Listado</h3><table class="table"><thead><tr><th>ID</th><th>Nombre</th></tr></thead><tbody>
    <?php foreach(DB::all("SELECT id,name FROM cash_registers WHERE branch_id=? ORDER BY id DESC",[$branch_id]) as $r): ?>
      <tr><td><?= $r['id'] ?></td><td><?= htmlspecialchars($r['name']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div></body></html>