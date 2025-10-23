<?php require_once __DIR__.'/../../lib/auth.php'; $BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];
try{auth_login(trim($_POST['dni']??''),strval($_POST['password']??'')); header('Location: '.$BASE.'/public/index.php');}
catch(Throwable $e){ header('Location: '.$BASE.'/public/auth/login.php?e='.urlencode($e->getMessage())); }