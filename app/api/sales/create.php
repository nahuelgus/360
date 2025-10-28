<?php
// /app/api/sales/create.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
function jexit($ok, $data_or_error, int $code = 200) {
    http_response_code($code);
    $payload = ['ok' => $ok];
    if ($ok) {
        // Para respuestas exitosas, fusionamos el array de datos
        $payload = array_merge($payload, $data_or_error);
    } else {
        $payload['error'] = $data_or_error;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(false, 'Método no permitido', 405);

    $in = json_decode(file_get_contents('php://input'), true);
    $u = $_SESSION['user'];

    $branch_id   = (int)($in['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    $register_id = (int)($in['register_id'] ?? $_SESSION['register_id'] ?? 0);
    // Leemos 'doc_mode' del frontend pero lo guardaremos en la columna 'doc_type'
    $doc_type    = strtoupper(trim((string)($in['doc_mode'] ?? 'TICKET_X')));
    $cbte_letter = in_array($in['cbte_letter'] ?? '', ['A','B','C']) ? $in['cbte_letter'] : null;
    $customer_id = !empty($in['customer_id']) ? (int)$in['customer_id'] : null;
    $items       = $in['items']    ?? [];
    $payments_in = $in['payments'] ?? [];
    $points_used = (int)($in['points_used'] ?? 0);
  
    if (!$branch_id || !$register_id) jexit(false, 'Sucursal y/o caja no seleccionada', 422);
    if (!is_array($items) || !count($items)) jexit(false, 'El carrito está vacío', 422);
  
    $shift = DB::one("SELECT id FROM cash_shifts WHERE register_id=? AND status='open' ORDER BY id DESC LIMIT 1", [$register_id]);
    if (!$shift) jexit(false, 'No hay un turno de caja abierto para esta caja', 400);

    $subtotal = 0.0;
    foreach ($items as $it) {
        $line = (float)($it['qty']??0) * (float)($it['unit_price']??0) * (1 - (float)($it['discount_pct']??0)/100.0);
        $subtotal += $line;
    }
    $total = round($subtotal, 2);
    
    // Asumimos 1 punto = $1. Esto puede venir de la configuración.
    $points_value_ratio = 1.0; 
    $points_value = $points_used * $points_value_ratio;
    $total_payable = max(0, $total - $points_value);

    $paid = 0.0;
    foreach ($payments_in as $p) { $paid += (float)($p['amount'] ?? 0); }
    if (abs($paid - $total_payable) > 0.02) {
        jexit(false, "La suma de pagos (".number_format($paid, 2).") no coincide con el total a pagar (".number_format($total_payable, 2).").", 422);
    }

    $pdo = DB::pdo();
    $pdo->beginTransaction();
  
    $society = DB::one("SELECT society_id FROM branches WHERE id=?", [$branch_id]);
  
    DB::run("INSERT INTO sales (society_id, branch_id, register_id, shift_id, user_id, customer_id, doc_type, cbte_letter, total, subtotal, points_used, points_value, created_at, arca_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
             [
                $society['society_id'], $branch_id, $register_id, $shift['id'], $u['id'], $customer_id, 
                $doc_type, // <-- Se inserta en la columna correcta 'doc_type'
                $cbte_letter, $total, $subtotal, $points_used, $points_value, 
                ($doc_type === 'INVOICE' ? 'pending' : 'not_applicable')
             ]);
    $sale_id = (int)$pdo->lastInsertId();

    $stItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, discount_pct) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $it) {
        $stItem->execute([$sale_id, $it['product_id'], $it['qty'], $it['unit_price'], $it['discount_pct'] ?? 0]);
    }

    $stPay = $pdo->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount, pos_info) VALUES (?, ?, ?, ?)");
    foreach ($payments_in as $p) {
        if (!empty($p['payment_method_id']) && !empty($p['amount'])) {
            $stPay->execute([$sale_id, $p['payment_method_id'], $p['amount'], $p['ref'] ?? null]);
        }
    }
  
    if ($points_used > 0 && $customer_id) {
        // DB::run("INSERT INTO customer_points (customer_id, sale_id, points_change, reason) VALUES (?, ?, ?, 'Canje en venta')", [$customer_id, $sale_id, -$points_used]);
    }

    $pdo->commit();
  
    jexit(true, [
        'sale_id' => $sale_id,
        'needs_arca' => ($doc_type === 'INVOICE')
    ]);

} catch(Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jexit(false, 'Error interno del servidor: '.$e->getMessage(), 500);
}