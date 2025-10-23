<?php
// /app/api/sales/create.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
function jexit($arr, int $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

try{
  if($_SERVER['REQUEST_METHOD']!=='POST') jexit(['ok'=>false,'error'=>'Método no permitido'],405);

  $raw = file_get_contents('php://input');
  $in  = json_decode($raw,true) ?: $_POST;
  $u   = $_SESSION['user'];

  $branch_id   = (int)($in['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
  $register_id = (int)($in['register_id'] ?? $_SESSION['register_id'] ?? 0);
  $doc_mode    = strtoupper(trim((string)($in['doc_mode'] ?? 'TICKET_X')));
  $cbte_letter = in_array($in['cbte_letter'] ?? '', ['A','B','C']) ? $in['cbte_letter'] : null;
  $customer_id = isset($in['customer_id']) ? (int)$in['customer_id'] : null;
  $items       = $in['items']    ?? [];
  $payments_in = $in['payments'] ?? [];
  $points_used = (int)($in['points_used'] ?? 0); // <-- NUEVO: Capturamos los puntos
  
  if(!$branch_id || !$register_id) jexit(['ok'=>false,'error'=>'Sucursal y/o caja no seleccionada'],422);
  if(!is_array($items) || !count($items)) jexit(['ok'=>false,'error'=>'El carrito está vacío'],422);
  
  $shift = DB::one("SELECT id FROM cash_shifts WHERE register_id=? AND status='open' ORDER BY id DESC LIMIT 1", [$register_id]);
  if(!$shift) jexit(['ok'=>false,'error'=>'No hay un turno de caja abierto para esta caja'],400);

  $subtotal = 0.0;
  foreach($items as $it) {
    $line = (float)($it['qty']??0) * (float)($it['unit_price']??0) * (1 - (float)($it['discount_pct']??0)/100.0);
    $subtotal += $line;
  }
  $total = round($subtotal, 2);
  
  // Lógica para puntos (si aplica)
  $points_value = 0;
  if ($points_used > 0 && $customer_id) {
      // Asumimos 1 punto = $1. Puedes cambiar esta lógica.
      $points_value = (float)DB::one("SELECT setting_value FROM app_settings WHERE setting_key='points_to_peso_ratio'")['setting_value'] ?? 1.0;
      $total -= ($points_used * $points_value);
  }

  $paid = 0.0;
  foreach($payments_in as $p) { $paid += (float)($p['amount'] ?? 0); }
  if (abs($paid - $total) > 0.02) {
      jexit(['ok'=>false,'error'=>"La suma de pagos ($paid) no coincide con el total a pagar ($total)."], 422);
  }

  $pdo = DB::pdo();
  $pdo->beginTransaction();
  
  $society = DB::one("SELECT society_id FROM branches WHERE id=?", [$branch_id]);
  
  DB::run("INSERT INTO sales (society_id, branch_id, register_id, shift_id, user_id, customer_id, doc_type, cbte_letter, total, subtotal, points_used, points_value, created_at, arca_status)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
           [$society['society_id'], $branch_id, $register_id, $shift['id'], $u['id'], $customer_id, $doc_mode, $cbte_letter, $total, $subtotal, $points_used, $points_value, ($doc_mode === 'INVOICE' ? 'pending' : 'not_applicable')]);
  $sale_id = (int)$pdo->lastInsertId();

  // Resto del código (inserción de items, pagos) se mantiene igual...
  $stItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, discount_pct) VALUES (?, ?, ?, ?, ?)");
  foreach($items as $it) {
    $stItem->execute([$sale_id, $it['product_id'], $it['qty'], $it['unit_price'], $it['discount_pct'] ?? 0]);
  }

  $stPay = $pdo->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount, pos_info) VALUES (?, ?, ?, ?)");
  foreach($payments_in as $p) {
    $stPay->execute([$sale_id, $p['payment_method_id'], $p['amount'], $p['ref'] ?? null]);
  }
  
  // Registrar el uso de puntos
  if ($points_used > 0 && $customer_id) {
      DB::run("INSERT INTO customer_points (customer_id, sale_id, points_change, reason) VALUES (?, ?, ?, 'Canje en venta')", [$customer_id, $sale_id, -$points_used]);
  }

  $pdo->commit();
  
  jexit([
    'ok' => true, 'sale_id' => $sale_id,
    'needs_arca' => ($doc_mode === 'INVOICE')
  ], 201);

} catch(Throwable $e) {
  if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(['ok'=>false,'error'=>'Error interno del servidor: '.$e->getMessage()],500);
}