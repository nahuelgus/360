<?php require_once __DIR__.'/../../lib/db.php'; require_once __DIR__.'/../../lib/auth.php';
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; if(current_user()){header('Location: '.$BASE.'/public/index.php'); exit;}
$err=$_GET['e']??'';?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar – ND</title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<style>.login{max-width:420px;margin:80px auto;background:#fff;border:1px solid #e6eaee;border-radius:14px;padding:20px}</style></head><body>
<div class="login"><h2>Ingresar</h2><?php if($err): ?><div class="badge" style="background:#fee;color:#a33">Error: <?= htmlspecialchars($err) ?></div><?php endif; ?>
<form method="post" action="<?= $BASE ?>/public/auth/login_post.php"><label>DNI</label><input class="input" name="dni" required>
<label>Contraseña</label><input class="input" name="password" type="password" required><div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end"><button class="btn primary">Ingresar</button></div></form></div>
</body></html>