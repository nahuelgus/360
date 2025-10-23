<?php require_once __DIR__.'/../../api/auth/require_login.php';
$shift_id=intval($_POST['shift_id']??0); $kind=$_POST['kind']??'income'; $amount=floatval($_POST['amount']??0); $reason=trim($_POST['reason']??'');
DB::run("INSERT INTO cash_movements (shift_id,kind,amount,reason,created_at) VALUES (?,?,?,?,NOW())",[$shift_id,$kind,$amount,$reason]); header('Location: /360/app/public/cash/index.php');