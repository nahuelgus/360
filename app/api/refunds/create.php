<?php
require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']);
header('Content-Type: application/json');

$u = $_SESSION['user']; $branch_id = $_SESSION['branch_id'] ?? null; $register_id = $_SESSION['register_id'] ?? null;
$type   = $_POST['type'] ?? 'voucher';            // 'voucher' | 'cash'
$sale_id = intval($_POST['sale_id'] ?? 0);
$dni     = trim($_POST['customer_dni'] ?? '');
$amount  = max(0, floatval($_POST['amount'] ?? 0));
$reason  = trim($_POST['reason'] ?? '');

if ($amount <= 0) { echo json_encode(['ok'=>false,'error'=>'Monto inválido']); exit; }

$customer_id = null;
if ($dni !== '') {
  $c = DB::one("SELECT id FROM customers WHERE dni=? LIMIT 1", [$dni]);
  if ($c) $customer_id = intval($c['id']);
}

if ($type === 'cash') {
  // Devolución en efectivo: egreso de caja en el turno abierto.
  if (!$register_id) { echo json_encode(['ok'=>false,'error'=>'Caja no seleccionada']); exit; }
  $shift = DB::one("SELECT * FROM cash_shifts WHERE register_id=? AND status='open' ORDER BY id DESC LIMIT 1",[$register_id]);
  if(!$shift){ echo json_encode(['ok'=>false,'error'=>'No hay turno de caja abierto']); exit; }

  DB::pdo()->beginTransaction();
  try{
    DB::run("INSERT INTO sale_refunds (sale_id,customer_id,type,code,initial_amount,remaining_amount,reason,is_active)
             VALUES (?,?,?,?,?,?,?,1)",
      [$sale_id?:null,$customer_id,'cash',null,$amount,0,$reason]);
    $rid = DB::pdo()->lastInsertId();

    DB::run("INSERT INTO cash_movements (shift_id,kind,amount,reason,created_at)
             VALUES (?,?,?,?,NOW())",
      [$shift['id'],'expense',$amount,'Devolución #'.$rid.($reason?': '.$reason:'')]);

    DB::pdo()->commit();
    echo json_encode(['ok'=>true,'id'=>$rid,'type'=>'cash']);
  }catch(Throwable $e){ DB::pdo()->rollBack(); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }

} else {
  // Voucher (parcial): NO impacta caja ahora, se descuenta cuando se usa.
  // Generar código único
  $code = 'VCH-'.date('Ymd').'-'.substr(bin2hex(random_bytes(5)),0,10);

  try{
    DB::run("INSERT INTO sale_refunds (sale_id,customer_id,type,code,initial_amount,remaining_amount,reason,is_active)
             VALUES (?,?,?,?,?,?,?,1)",
      [$sale_id?:null,$customer_id,'voucher',$code,$amount,$amount,$reason]);
    $rid = DB::pdo()->lastInsertId();
    echo json_encode(['ok'=>true,'id'=>$rid,'type'=>'voucher','code'=>$code,'amount'=>$amount]);
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
}
