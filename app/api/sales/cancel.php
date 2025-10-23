<?php
// /app/api/sales/cancel.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_login.php';
require_role(['admin', 'supervisor']); // Solo admins y supervisores pueden anular
require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: application/json; charset=utf-8');
function jexit($ok, $data_or_error, int $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok, ($ok ? 'data' : 'error') => $data_or_error]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(false, 'Método no permitido', 405);
    $in = json_decode(file_get_contents('php://input'), true);
    $sale_id = (int)($in['sale_id'] ?? 0);
    if ($sale_id <= 0) jexit(false, 'ID de venta inválido', 422);

    $sale = DB::one("SELECT * FROM sales WHERE id=?", [$sale_id]);
    if (!$sale) jexit(false, 'Venta no encontrada', 404);
    
    if ($sale['arca_status'] === 'sent') jexit(false, 'No se puede anular una venta con CAE. Genera una Nota de Crédito.', 403);
    if ($sale['arca_status'] === 'voided') jexit(false, 'Esta venta ya fue anulada.', 409);

    $items = DB::all("SELECT product_id, qty FROM sale_items WHERE sale_id=?", [$sale_id]);

    $pdo = DB::pdo();
    $pdo->beginTransaction();

    // 1. Marcar la venta como anulada ('voided')
    DB::run("UPDATE sales SET arca_status='voided' WHERE id=?", [$sale_id]);

    // 2. Devolver el stock a los lotes (si el sistema de stock por lotes está activo)
    // NOTA: La devolución de stock a un lote específico no es posible sin saber de qué lote salió.
    // Por ahora, esta lógica se prepara para un futuro control de stock más simple.
    // En una futura versión, asociaremos cada sale_item a un depo_lot_id.
    // foreach ($items as $item) {
    //   DB::run("UPDATE product_stock SET quantity = quantity + ? WHERE product_id = ? AND branch_id = ?", 
    //            [$item['qty'], $item['product_id'], $sale['branch_id']]);
    // }

    // 3. Revertir el movimiento de caja (creando un egreso)
    $shift = DB::one("SELECT id FROM cash_shifts WHERE id=? AND status='open'", [$sale['shift_id']]);
    if ($shift) {
        DB::run("INSERT INTO cash_movements (shift_id, kind, amount, reason, created_at) VALUES (?, 'expense', ?, ?, NOW())",
                [$shift['id'], $sale['total'], "Anulación de venta #{$sale_id}"]);
    }
    
    // 4. Revertir los puntos de lealtad ganados o usados
    DB::run("DELETE FROM loyalty_points_ledger WHERE sale_id = ?", [$sale_id]);
    
    $pdo->commit();

    jexit(true, ['message' => "Venta #{$sale_id} anulada correctamente."]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    jexit(false, 'Error interno del servidor: ' . $e->getMessage(), 500);
}