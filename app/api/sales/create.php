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

  $branch_id   = (int)($in['branch_id'] ?? 0);
  $register_id = isset($in['register_id']) ? (int)$in['register_id'] : null;
  $shift_id    = isset($in['shift_id'])    ? (int)$in['shift_id']    : null;
  $doc_mode    = strtoupper(trim((string)($in['doc_mode'] ?? 'TICKET_X'))); // TICKET_X | INVOICE
  $cbte_letter = isset($in['cbte_letter']) ? strtoupper(trim((string)$in['cbte_letter'])) : null; // A|B|C
  $customer_id = isset($in['customer_id']) ? (int)$in['customer_id'] : null;

  $items       = $in['items']    ?? [];
  $payments_in = $in['payments'] ?? [];
  $discTot     = (float)($in['discount_total'] ?? 0);

  if(!$branch_id) jexit(['ok'=>false,'error'=>'branch_id requerido'],422);
  if(!is_array($items) || !count($items)) jexit(['ok'=>false,'error'=>'items vacío'],422);

  // ====== Calcular totales (por ítem con descuento opcional) ======
  $subtotal=0.0;
  foreach($items as &$it){
    $pid   = (int)($it['product_id'] ?? 0);
    $qty   = (float)($it['qty'] ?? 0);
    $price = (float)($it['unit_price'] ?? 0);
    $disc  = (float)($it['discount_pct'] ?? 0);
    if($pid<=0 || $qty<=0 || $price<0) jexit(['ok'=>false,'error'=>'item inválido'],422);
    $line  = $qty * $price * (1 - max(0,min(100,$disc))/100.0);
    $it['__line_total'] = round($line,2);
    $subtotal += $it['__line_total'];
  }
  unset($it);
  $discount_total = max(0.0, round($discTot,2));
  $total          = max(0.0, round($subtotal - $discount_total,2));

  // Validar pagos (si vienen)
  $paid = 0.0;
  foreach($payments_in as $p){ $paid += (float)($p['amount'] ?? 0); }
  $paid = round($paid,2);
  if($paid>0 && abs($paid-$total) > 0.02){
    jexit(['ok'=>false,'error'=>'La suma de pagos no coincide con el total','total'=>$total,'pagado'=>$paid],422);
  }

  $pdo = DB::pdo();
  $pdo->beginTransaction();

  // ====== Detectar columnas disponibles en sales/sale_items/sale_payments ======
  $salesCols       = array_column($pdo->query("SHOW COLUMNS FROM sales")->fetchAll(), 'Field');
  $saleItemsCols   = array_column($pdo->query("SHOW COLUMNS FROM sale_items")->fetchAll(), 'Field');
  $salePaysCols    = array_column($pdo->query("SHOW COLUMNS FROM sale_payments")->fetchAll(), 'Field');

  $hasSales = fn(string $c)=> in_array($c,$salesCols,true);
  $hasItem  = fn(string $c)=> in_array($c,$saleItemsCols,true);
  $hasPay   = fn(string $c)=> in_array($c,$salePaysCols,true);

  // ====== Insert en sales (solo campos existentes) ======
  $fields = ['branch_id'];  $values = [ $branch_id ];
  if(!is_null($register_id) && $hasSales('register_id')){ $fields[]='register_id'; $values[]=$register_id; }
  if(!is_null($shift_id)    && $hasSales('shift_id'))   { $fields[]='shift_id';    $values[]=$shift_id; }
  if(!is_null($customer_id) && $hasSales('customer_id')){ $fields[]='customer_id'; $values[]=$customer_id; }

  if($hasSales('doc_mode'))        { $fields[]='doc_mode';        $values[]= ($doc_mode==='INVOICE'?'INVOICE':'TICKET_X'); }
  if($hasSales('cbte_letter'))     { $fields[]='cbte_letter';     $values[]= ($doc_mode==='INVOICE' && in_array($cbte_letter,['A','B','C']))?$cbte_letter:null; }
  if($hasSales('subtotal'))        { $fields[]='subtotal';        $values[]= $subtotal; }
  if($hasSales('discount_total'))  { $fields[]='discount_total';  $values[]= $discount_total; }
  if($hasSales('total'))           { $fields[]='total';           $values[]= $total; }
  if($hasSales('arca_status'))     { $fields[]='arca_status';     $values[]= ($doc_mode==='INVOICE'?'pending':'not_applicable'); }

  // created_by si existe
  $user = $_SESSION['user'] ?? null; $created_by = $user['id'] ?? null;
  if($created_by && $hasSales('created_by')){ $fields[]='created_by'; $values[]=(int)$created_by; }

  $sql = 'INSERT INTO sales ('.implode(',',$fields).') VALUES ('.rtrim(str_repeat('?,',count($fields)),',').')';
  $st  = $pdo->prepare($sql); $st->execute($values);
  $sale_id = (int)$pdo->lastInsertId();

  // ====== Insert en sale_items (sin exigir line_total si no existe) ======
  // columnas base que esperamos que existan: sale_id, product_id, qty, unit_price
  $canDisc = $hasItem('discount_pct');
  $canLine = $hasItem('line_total');

  $colsSI = ['sale_id','product_id','qty','unit_price'];
  if($canDisc) $colsSI[]='discount_pct';
  if($canLine) $colsSI[]='line_total';

  $sqlIt = 'INSERT INTO sale_items ('.implode(',',$colsSI).') VALUES ('.rtrim(str_repeat('?,',count($colsSI)),',').')';
  $stItem = $pdo->prepare($sqlIt);

  foreach($items as $it){
    $vals = [
      $sale_id,
      (int)$it['product_id'],
      (float)$it['qty'],
      (float)$it['unit_price'],
    ];
    if($canDisc) $vals[] = (float)($it['discount_pct'] ?? 0);
    if($canLine) $vals[] = (float)$it['__line_total'];
    $stItem->execute($vals);
  }

  // ====== Insert pagos (mapea ref→pos_info si hiciera falta) ======
  if(is_array($payments_in) && count($payments_in)>0){
    $colsSP = ['sale_id'];
    $usePM  = $hasPay('payment_method_id');
    $useAmt = $hasPay('amount');
    $useRef = $hasPay('ref');
    $usePos = $hasPay('pos_info'); // alternativa si no existe 'ref'

    if($usePM)  $colsSP[] = 'payment_method_id';
    if($useAmt) $colsSP[] = 'amount';
    if($useRef) $colsSP[] = 'ref';
    elseif($usePos) $colsSP[] = 'pos_info';

    $sqlPay = 'INSERT INTO sale_payments ('.implode(',',$colsSP).') VALUES ('.rtrim(str_repeat('?,',count($colsSP)),',').')';
    $stPay  = $pdo->prepare($sqlPay);

    foreach($payments_in as $p){
      $vals = [ $sale_id ];
      if($usePM)  $vals[] = (int)($p['payment_method_id'] ?? 0);
      if($useAmt) $vals[] = (float)($p['amount'] ?? 0);
      if($useRef) $vals[] = (string)($p['ref'] ?? null);
      elseif($usePos) $vals[] = (string)($p['ref'] ?? $p['pos_info'] ?? null);
      $stPay->execute($vals);
    }
  }

  $pdo->commit();
  jexit([
    'ok'=>true,
    'sale_id'=>$sale_id,
    'doc_mode'=>$doc_mode,
    'needs_arca'=> ($doc_mode==='INVOICE'),
    'total'=>$total
  ],201);

}catch(Throwable $e){
  if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  jexit(['ok'=>false,'error'=>'DB_ERROR: '.$e->getMessage()],500);
}
