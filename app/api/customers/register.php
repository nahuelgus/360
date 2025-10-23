<?php require_once __DIR__.'/../../lib/db.php'; header('Content-Type: application/json');
$in=$_POST; if(empty($in['terms'])){ echo json_encode(['ok'=>false,'error'=>'Debe aceptar TyC']); exit; }
$dni=trim($in['dni']??''); $name=trim($in['name']??'');
if($dni===''||$name===''){ echo json_encode(['ok'=>false,'error'=>'Datos requeridos']); exit; }
$email=trim($in['email']??''); $phone=trim($in['phone']??''); $address=trim($in['address']??''); $birth=$in['birthdate']??null;
$flags = isset($in['diet_flags']) && is_array($in['diet_flags']) ? implode(',', $in['diet_flags']) : null;
try{
  DB::run("INSERT INTO customers (dni,name,email,phone,birthdate,address,diet_flags) VALUES (?,?,?,?,?,?,?)",
    [$dni,$name,$email,$phone,$birth?:null,$address,$flags]);
  echo json_encode(['ok'=>true,'redirect'=>'/360/app/public/clients/index.php?dni='.urlencode($dni)]);
}catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
