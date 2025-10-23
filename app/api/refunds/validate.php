<?php
require_once __DIR__.'/../../lib/auth.php'; header('Content-Type: application/json');
$code = trim($_GET['code'] ?? '');
if ($code===''){ echo json_encode(['ok'=>false,'error'=>'Código vacío']); exit; }
$r = DB::one("SELECT id,code,remaining_amount,is_active FROM sale_refunds WHERE code=? AND type='voucher' LIMIT 1", [$code]);
if(!$r || !$r['is_active'] || floatval($r['remaining_amount'])<=0){
  echo json_encode(['ok'=>false,'error'=>'Voucher inválido o agotado']); exit;
}
echo json_encode(['ok'=>true,'data'=>$r]);
