<?php
// /360/app/api/sales/create_ticket.php
require_once __DIR__ . '/../../lib/auth.php';
header('Content-Type: application/json');

function respond($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $u = $_SESSION['user'] ?? null;
  if (!$u) respond(['ok'=>false,'error'=>'No autenticado'], 401);

  $branch_id   = $_SESSION['branch_id'] ?? null;
  $register_id = $_SESSION['register_id'] ?? null;
  if (!$branch_id || !$register_id) respond(['ok'=>false,'error'=>'Sucursal o caja no seleccionada'], 400);

  $shift = DB::one("SELECT * FROM cash_shifts WHERE register_id=? AND status='open' ORDER BY id DESC LIMIT 1", [$register_id]);
  if (!$shift) respond(['ok'=>false,'error'=>'No hay turno de caja abierto'], 400);

  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) respond(['ok'=>false,'error'=>'JSON inválido'], 400);

  $items         = $in['items'] ?? [];
  $pays          = $in['payments'] ?? [];
  $customer_dni  = trim($in['customer_dni'] ?? '');
  $doc_type      = strtoupper(trim($in['doc_type'] ?? 'TICKET_X'));
  $redeem_points = intval($in['redeem_points'] ?? 0);

  if (empty($items)) respond(['ok'=>false,'error'=>'Carrito vacío'], 400);

  $customer_id = null;
  if ($customer_dni !== '') {
    $c = DB::one("SELECT id FROM customers WHERE dni=? LIMIT 1", [$customer_dni]);
    if ($c) $customer_id = intval($c['id']);
  }

  $so = DB::one("SELECT s.id FROM societies s JOIN branches b ON b.society_id=s.id WHERE b.id=? LIMIT 1", [$branch_id]);
  if (!$so) respond(['ok'=>false,'error'=>'Sociedad no encontrada'], 400);

  // ¿Existe la columna payment_methods.is_voucher?
  $col = DB::one("SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_methods' AND COLUMN_NAME='is_voucher'");
  $has_is_voucher = !!$col;

  DB::pdo()->beginTransaction();

  // Totalizar
  $subtotal = 0.0;
  foreach ($items as $it) {
    $qty = floatval($it['qty']);
    $price = floatval($it['price']);
    $disc = floatval($it['discount'] ?? 0);
    $line = $qty * $price * (1 - ($disc / 100));
    $subtotal += $line;
  }

  // Canje de puntos
  $redeem_amount = 0.0;
  $points_used = 0;
  if ($customer_id && $redeem_points > 0) {
    $cfg = DB::one("SELECT redeem_pesos_per_point FROM loyalty_settings WHERE id=1");
    $ppv = floatval($cfg['redeem_pesos_per_point'] ?? 50.0); // $ por punto

    $lp = DB::one("SELECT COALESCE(SUM(points),0) p FROM loyalty_points_ledger WHERE customer_id=?", [$customer_id]);
    $available = intval($lp['p'] ?? 0);
    if ($redeem_points > $available) throw new RuntimeException('Puntos insuficientes');

    $points_used = $redeem_points;
    $redeem_amount = $points_used * $ppv;
    if ($redeem_amount > $subtotal) {
      $points_used  = floor($subtotal / ($ppv > 0 ? $ppv : 1));
      $redeem_amount = $points_used * $ppv;
    }
  }

  $net_total = max(0.0, $subtotal - $redeem_amount);

  // Insert venta
  DB::run("INSERT INTO sales (society_id,branch_id,register_id,shift_id,user_id,customer_id,doc_type,subtotal,discount_total,total,created_at,arca_status)
           VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),'not_applicable')",
          [$so['id'],$branch_id,$register_id,$shift['id'],$u['id'],$customer_id,$doc_type,$subtotal,0,$net_total]);
  $sale_id = DB::pdo()->lastInsertId();

  // Ítems
  foreach ($items as $it) {
    DB::run("INSERT INTO sale_items (sale_id,product_id,qty,unit_price,discount_pct)
             VALUES (?,?,?,?,?)",
            [$sale_id,$it['product_id'],$it['qty'],$it['price'],$it['discount'] ?? 0]);
  }

  // Registrar canje (negativo)
  if ($customer_id && $points_used > 0) {
    DB::run("INSERT INTO loyalty_points_ledger (customer_id,sale_id,points,reason)
             VALUES (?,?,?,?)", [$customer_id,$sale_id,-$points_used,'Canje en compra']);
  }

  // Pagos
  $paid_sum = 0.0;
  foreach ($pays as $p) {
    $method_id = intval($p['method_id'] ?? 0);
    $amount    = floatval($p['amount'] ?? 0);
    $pos_info  = trim($p['pos_info'] ?? '');
    if (!$method_id || $amount <= 0) continue;

    // traer método con fallback
    if ($has_is_voucher) {
      $pm = DB::one("SELECT id,name,is_voucher FROM payment_methods WHERE id=? LIMIT 1", [$method_id]);
      $is_voucher = intval($pm['is_voucher'] ?? 0) === 1;
    } else {
      $pm = DB::one("SELECT id,name FROM payment_methods WHERE id=? LIMIT 1", [$method_id]);
      $is_voucher = (stripos($pm['name'] ?? '', 'voucher') !== false); // heurística
    }

    DB::run("INSERT INTO sale_payments (sale_id,payment_method_id,amount,pos_info)
             VALUES (?,?,?,?)", [$sale_id,$method_id,$amount,$pos_info]);

    if ($is_voucher) {
      if ($pos_info==='') throw new RuntimeException('Voucher sin código');
      $r = DB::one("SELECT id,remaining_amount,is_active FROM sale_refunds WHERE code=? AND type='voucher' FOR UPDATE", [$pos_info]);
      if(!$r || !$r['is_active']) throw new RuntimeException('Voucher inválido');
      $rem = floatval($r['remaining_amount']);
      if($rem < $amount) throw new RuntimeException('Monto del voucher excede el disponible');

      DB::run("INSERT INTO sale_refund_usages (refund_id,sale_id,amount) VALUES (?,?,?)", [$r['id'],$sale_id,$amount]);
      $newRem = $rem - $amount;
      DB::run("UPDATE sale_refunds SET remaining_amount=?, is_active=? WHERE id=?", [$newRem, ($newRem>0?1:0), $r['id']]);
      // no cash movement
    } else {
      DB::run("INSERT INTO cash_movements (shift_id,kind,amount,reason,created_at)
               VALUES (?,?,?,?,NOW())",
               [$shift['id'],'income',$amount,'Venta #'.$sale_id]);
    }

    $paid_sum += $amount;
  }

  if (round($paid_sum,2) < round($net_total,2)) {
    throw new RuntimeException('Pagos insuficientes');
  }

  // Sumar puntos por compra (1 cada $50 por defecto) sobre neto
  if ($customer_id) {
    $cfg = DB::one("SELECT pesos_per_point FROM loyalty_settings WHERE id=1");
    $pp = floatval($cfg['pesos_per_point'] ?? 50.0);
    $points = max(0, floor($net_total / ($pp > 0 ? $pp : 50.0)));
    if ($points > 0) {
      DB::run("INSERT INTO loyalty_points_ledger (customer_id,sale_id,points,reason)
               VALUES (?,?,?,?)", [$customer_id,$sale_id,$points,'Compra '.$doc_type]);
    }
  }

  DB::pdo()->commit();
  respond(['ok'=>true,'id'=>$sale_id,'total'=>$net_total,'redeem_amount'=>$redeem_amount,'redeem_points'=>$points_used]);

} catch (Throwable $e) {
  if (DB::pdo()->inTransaction()) DB::pdo()->rollBack();
  respond(['ok'=>false,'error'=>$e->getMessage()], 500);
}