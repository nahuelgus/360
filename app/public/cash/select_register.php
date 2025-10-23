<?php require_once __DIR__.'/../../api/auth/require_login.php';
$_SESSION['register_id']= (isset($_POST['register_id'])&&$_POST['register_id']!=='')?intval($_POST['register_id']):null;
header('Location: /360/app/public/cash/index.php');