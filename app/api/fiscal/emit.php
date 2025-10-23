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

  // Traer venta y validar doc_mode
  $sale = DB::one("SELECT id, doc_mode FROM sales WHERE id=?", [$sale_id]);
  if (!$sale) jexit(['ok'=>false,'error'=>'Venta no encontrada'],404);

  $doc_mode = strtoupper((string)($sale['doc_mode'] ?? 'TICKET_X'));
  if ($doc_mode !== 'INVOICE') jexit(['ok'=>false,'error'=>'La venta no es de tipo Factura'],422);

  // Construir cliente y emitir
  $client = ArcaClient::fromSaleId($sale_id);
  $res    = $client->emitInvoiceForSale($sale_id);

  jexit($res, 200);

} catch (Throwable $e) {
  jexit(['ok'=>false,'error'=>$e->getMessage()],500);
}
