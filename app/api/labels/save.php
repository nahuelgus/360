<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$id= isset($_POST['id'])&&$_POST['id']!==''?intval($_POST['id']):null; $name=trim($_POST['name']??''); $icon=trim($_POST['icon_path']??'');
if($id){ DB::run("UPDATE product_labels SET name=?, icon_path=? WHERE id=?",[$name,$icon,$id]); }
else { DB::run("INSERT INTO product_labels (name,icon_path) VALUES (?,?)",[$name,$icon]); $id=DB::pdo()->lastInsertId();}
echo json_encode(['ok'=>true,'id'=>$id]);