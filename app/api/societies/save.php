<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$in=$_POST; $id=isset($in['id'])&&$in['id']!==''?intval($in['id']):null; $f=['name','tax_id','email','phone','address','city','state','postal_code']; $v=[]; foreach($f as $x){$v[$x]=trim($in[$x]??'');}
$active= isset($in['is_active'])?(intval($in['is_active'])?1:0):1;
if($id){ DB::run("UPDATE societies SET name=?,tax_id=?,email=?,phone=?,address=?,city=?,state=?,postal_code=?,is_active=? WHERE id=?",[$v['name'],$v['tax_id'],$v['email'],$v['phone'],$v['address'],$v['city'],$v['state'],$v['postal_code'],$active,$id]); }
else { DB::run("INSERT INTO societies (name,tax_id,email,phone,address,city,state,postal_code,is_active) VALUES (?,?,?,?,?,?,?,?,?)",[$v['name'],$v['tax_id'],$v['email'],$v['phone'],$v['address'],$v['city'],$v['state'],$v['postal_code'],$active]); $id=DB::pdo()->lastInsertId();}
echo json_encode(['ok'=>true,'id'=>$id]);