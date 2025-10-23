<?php require_once __DIR__.'/../../api/auth/require_login.php';
$_SESSION['branch_id']= (isset($_POST['branch_id'])&&$_POST['branch_id']!=='')?intval($_POST['branch_id']):null;
header('Location: /360/app/public/index.php');