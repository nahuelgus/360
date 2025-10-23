<?php
// /360/app/public/partials/navbar.php
require_once __DIR__.'/../../api/auth/require_login.php';
$BASE = (require __DIR__.'/../../config/.env.php')['app']['base_url'];

$u = $_SESSION['user'];
$branch_id   = $_SESSION['branch_id'] ?? null;
$register_id = $_SESSION['register_id'] ?? null;

$branches = DB::all("SELECT b.id,b.name,s.name AS society_name
                       FROM branches b
                  LEFT JOIN societies s ON s.id=b.society_id
                      WHERE b.is_active=1
                   ORDER BY s.name,b.name");
$registers = $branch_id
  ? DB::all("SELECT id,name FROM cash_registers WHERE branch_id=? ORDER BY name", [$branch_id])
  : [];
?>
<style>
  .nav{position:sticky;top:0;z-index:20;background:#fff;border-bottom:1px solid #e8ecef}
  .nav .wrap{display:flex;align-items:center;gap:10px;max-width:1200px;margin:0 auto;padding:10px 16px}
  .brand{font-weight:800;color:#2D6869;font-size:1.05rem}
  .nav .btn{display:inline-flex;align-items:center;gap:6px}
  .nav .sp{flex:1}
  .nav svg{width:18px;height:18px;vertical-align:-3px}
  .nav .input{height:36px}
  @media (max-width:980px){ .nav .wrap{flex-wrap:wrap} }
</style>

<div class="nav">
  <div class="wrap">
    <a class="brand" href="<?= $BASE ?>/public/index.php">Natural Dieteticas</a>

    <a class="btn" href="<?= $BASE ?>/public/sales/pos.php" title="POS">
      <!-- cart icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6L5 2H2"/></svg>
      POS
    </a>
    <a class="btn" href="<?= $BASE ?>/public/products/index.php" title="Productos">
      <!-- box icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5"/><path d="M12 22V12"/></svg>
      Productos
    </a>
    <a class="btn" href="<?= $BASE ?>/public/refunds/index.php" title="Devoluciones / Vouchers">
      <!-- rotate-ccw icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
      Devoluciones
    </a>
    <a class="btn" href="<?= $BASE ?>/public/cash/index.php" title="Caja">
      <!-- wallet icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12V7a2 2 0 0 0-2-2H7l-4 4v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/><path d="M21 12h-7a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h7z"/></svg>
      Caja
    </a>

    <div class="sp"></div>

    <!-- Selector de Sucursal -->
    <form method="post" action="<?= $BASE ?>/public/branches/select_branch.php">
      <select class="input" name="branch_id" onchange="this.form.submit()">
        <option value="">— Sucursal —</option>
        <?php foreach($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($branch_id==$b['id']?'selected':'') ?>>
            <?= htmlspecialchars($b['society_name'].' · '.$b['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <!-- Selector de Caja -->
    <form method="post" action="<?= $BASE ?>/public/cash/select_register.php">
      <select class="input" name="register_id" onchange="this.form.submit()">
        <option value="">— Caja —</option>
        <?php foreach($registers as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ($register_id==$r['id']?'selected':'') ?>>
            <?= htmlspecialchars($r['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="small">Hola, <b><?= htmlspecialchars($u['name']) ?></b></div>
    <a class="btn" href="<?= $BASE ?>/api/auth/logout.php" title="Salir">
      <!-- log-out icon -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Salir
    </a>
  </div>
</div>