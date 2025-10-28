<?php
// /app/api/fiscal/emit.php
declare(strict_types=1);

require_once __DIR__ . '/../auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/arca.php';

header('Content-Type: application/json; charset=utf-8');

function jexit($arr, int $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(['ok'=>false,'error'=>'Método no permitido'],405);

  $raw = file_get_contents('php://input');
  $in  = json_decode($raw, true) ?: $_POST;

  $sale_id = (int)($in['sale_id'] ?? 0);
  if ($sale_id <= 0) jexit(['ok'=>false,'error'=>'sale_id requerido'],422);

  // ==================================================================
  // *** ESTA ES LA LÍNEA CORREGIDA ***
  // Leemos de la columna correcta 'doc_type'
  $sale = DB::one("SELECT id, doc_type FROM sales WHERE id=?", [$sale_id]);
  if (!$sale) jexit(['ok'=>false,'error'=>'Venta no encontrada'],404);

  $doc_type = strtoupper((string)($sale['doc_type'] ?? 'TICKET_X'));
  if ($doc_type !== 'INVOICE') {
      jexit(['ok'=>false,'error'=>'La venta no es de tipo Factura (es ' . $doc_type . ')'],400);
  }
  // ==================================================================

  // De aquí en adelante, el flujo es correcto.
  $client = ArcaClient::fromSaleId($sale_id);
  $result = $client->emitInvoiceForSale($sale_id);

  jexit($result, 200);

} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>$e->getMessage()],500);
}