<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$id= isset($_POST['id'])&&$_POST['id']!==' '?intval($_POST['id']):null; $name=trim($_POST['name']??''); $bank=trim($_POST['bank']??'');
$fee= isset($_POST['fee_percent']) && $_POST['fee_percent']!=='' ? floatval($_POST['fee_percent']) : null; $active= isset($_POST['is_active'])?1:0;
if($id){ DB::run("UPDATE payment_methods SET name=?, bank=?, fee_percent=?, is_active=? WHERE id=?",[$name,$bank,$fee,$active,$id]); }
else { DB::run("INSERT INTO payment_methods (name,bank,fee_percent,is_active) VALUES (?,?,?,?)",[$name,$bank,$fee,1]); $id=DB::pdo()->lastInsertId();}
echo json_encode(['ok'=>true,'id'=>$id]);