<?php
require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor','vendedor']);
header('Content-Type: application/json');

$code   = trim($_POST['code'] ?? '');
$amount = max(0, floatval($_POST['amount'] ?? 0));
$sale_id = intval($_POST['sale_id'] ?? 0);

if($code==='' || $amount<=0 || $sale_id<=0){ echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos']); exit; }

$r = DB::one("SELECT id,remaining_amount,is_active FROM sale_refunds WHERE code=? AND type='voucher' FOR UPDATE", [$code]);
if(!$r || !$r['is_active']){ echo json_encode(['ok'=>false,'error'=>'Voucher inválido']); exit; }
$rem = floatval($r['remaining_amount']);
if($rem < $amount){ echo json_encode(['ok'=>false,'error'=>'Monto mayor al disponible']); exit; }

DB::pdo()->beginTransaction();
try{
  DB::run("INSERT INTO sale_refund_usages (refund_id,sale_id,amount) VALUES (?,?,?)", [$r['id'],$sale_id,$amount]);
  $newRem = $rem - $amount;
  DB::run("UPDATE sale_refunds SET remaining_amount=?, is_active=? WHERE id=?", [$newRem, ($newRem>0?1:0), $r['id']]);
  DB::pdo()->commit();
  echo json_encode(['ok'=>true,'remaining'=>$newRem]);
}catch(Throwable $e){ DB::pdo()->rollBack(); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
