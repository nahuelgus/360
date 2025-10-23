<?php
require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']);
header('Content-Type: application/json');

$in=$_POST;
$id = (isset($in['id']) && $in['id']!=='') ? intval($in['id']) : null;
$society_id = (isset($in['society_id']) && $in['society_id']!=='') ? intval($in['society_id']) : null;
if(!$society_id){ http_response_code(422); echo json_encode(['ok'=>false,'error'=>'society_id requerido']); exit; }

$fields=['name','address','city','state','postal_code','manager']; $v=[];
foreach($fields as $x){ $v[$x]=trim($in[$x]??''); }

/* ✅ Checkbox: si existe => 1, si no existe => 0 */
$active = isset($in['is_active']) ? 1 : 0;

if($id){
  DB::run("UPDATE branches SET society_id=?,name=?,address=?,city=?,state=?,postal_code=?,manager=?,is_active=? WHERE id=?",
    [$society_id,$v['name'],$v['address'],$v['city'],$v['state'],$v['postal_code'],$v['manager'],$active,$id]);
} else {
  DB::run("INSERT INTO branches (society_id,name,address,city,state,postal_code,manager,is_active)
           VALUES (?,?,?,?,?,?,?,?)",
    [$society_id,$v['name'],$v['address'],$v['city'],$v['state'],$v['postal_code'],$v['manager'],$active]);
  $id = DB::pdo()->lastInsertId();
}
echo json_encode(['ok'=>true,'id'=>$id]);