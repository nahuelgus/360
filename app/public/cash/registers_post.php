<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$branch_id=$_SESSION['branch_id']??null; if(!$branch_id){ die('Seleccionar sucursal'); }
$name=trim($_POST['name']??''); if($name===''){ die('Nombre requerido'); }
DB::run("INSERT INTO cash_registers (branch_id,name) VALUES (?,?)",[$branch_id,$name]); header('Location: /360/app/public/cash/registers.php');