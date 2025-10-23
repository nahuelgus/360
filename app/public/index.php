<?php
// /360/app/public/index.php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../api/auth/require_login.php';
$BASE = (require __DIR__.'/../config/.env.php')['app']['base_url'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – Natural Dieteticas</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<style>
  .dash{max-width:1200px;margin:16px auto;padding:0 16px}
  .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
  .card.icon{display:flex;align-items:center;gap:12px;transition:transform .08s ease, box-shadow .12s ease}
  .card.icon:hover{transform:translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,.06)}
  .i{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;border:1px solid #e6eaee;background:#fff}
  .i svg{width:22px;height:22px}
  .muted{color:#667;font-size:.9rem}
</style>
</head><body>
<?php require __DIR__.'/partials/navbar.php'; ?>

<div class="dash">
  <div class="cards">
    <a class="card icon" href="<?= $BASE ?>/public/sales/pos.php">
      <div class="i">
        <!-- cart -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6L5 2H2"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">POS – Venta</h3>
        <div class="muted">Ticket X por defecto, múltiples pagos, vouchers.</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/products/index.php">
      <div class="i">
        <!-- box -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5"/><path d="M12 22V12"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Productos & Stock</h3>
        <div class="muted">Granel, caja, etiquetas globales.</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/refunds/index.php">
      <div class="i">
        <!-- rotate-ccw -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Devoluciones / Vouchers</h3>
        <div class="muted">Generación y uso parcial via POS.</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/cash/index.php">
      <div class="i">
        <!-- wallet -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12V7a2 2 0 0 0-2-2H7l-4 4v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1"/><path d="M21 12h-7a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h7z"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Caja</h3>
        <div class="muted">Turno, ingresos/egresos, cierre e impresión 90mm.</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/labels/index.php">
      <div class="i">
        <!-- tag -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20.59 13.41L11 4H4v7l9.59 9.59a2 2 0 0 0 2.82 0l4.18-4.18a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Etiquetas</h3>
        <div class="muted">SIN TACC, KETO, VEGANO y personalizadas.</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/societies/index.php">
      <div class="i">
        <!-- building -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h10M7 12h10M7 17h6"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Razones sociales / ARCA</h3>
        <div class="muted">Credenciales por R.S. (sandbox/producción).</div>
      </div>
    </a>

    <a class="card icon" href="<?= $BASE ?>/public/branches/index.php">
      <div class="i">
        <!-- map-pin -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 21s-6-4.35-6-10a6 6 0 1 1 12 0c0 5.65-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Sucursales</h3>
        <div class="muted">Vinculadas a razón social.</div>
      </div>
    </a>

    <div class="card icon" style="opacity:.8">
      <div class="i">
        <!-- alert -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">Productos a vencer</h3>
        <div class="muted">Próximamente (lotes + alertas).</div>
      </div>
    </div>

    <div class="card icon" style="opacity:.8">
      <div class="i">
        <!-- bell -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.7 1.7 0 0 0 3.4 0"/></svg>
      </div>
      <div>
        <h3 style="margin:.2rem 0 .2rem 0">“Avisar cuando entre”</h3>
        <div class="muted">Próximamente.</div>
      </div>
    </div>
  </div>
</div>

<footer style="text-align:center;color:#889;font-size:.85rem;padding:30px 0">
  Retail360 · Dashboard con íconos
</footer>
</body></html>