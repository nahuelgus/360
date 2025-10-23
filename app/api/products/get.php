<?php
// /360/app/api/products/get.php
require_once __DIR__ . '/../../lib/auth.php';
header('Content-Type: application/json');

function out($arr,$code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

try {
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) out(['ok'=>false,'error'=>'ID inválido'], 400);

  $p = DB::one("SELECT * FROM products WHERE id=? LIMIT 1", [$id]);
  if (!$p) out(['ok'=>false,'error'=>'Producto no encontrado'], 404);

  // Producto
  $out = [
    'id'             => (int)$p['id'],
    'sku'            => $p['sku'] ?? null,
    'barcode'        => $p['barcode'] ?? null,
    'name'           => $p['name'] ?? null,
    'unit'           => $p['unit'] ?? null,
    'box_units'      => isset($p['box_units']) ? (int)$p['box_units'] : null,
    'price'          => isset($p['price']) ? (float)$p['price'] : 0,
    'cost'           => isset($p['cost']) ? (float)$p['cost'] : 0,
    'is_active'      => isset($p['is_active']) ? (int)$p['is_active'] : 1,
    'category_id'    => isset($p['category_id']) ? (int)$p['category_id'] : null,
    'subcategory_id' => isset($p['subcategory_id']) ? (int)$p['subcategory_id'] : null,
  ];

  // Etiquetas (desde product_labels / product_label_links)
  $labels = DB::all(
    "SELECT pl.id, pl.name, pl.icon_path AS icon
     FROM product_label_links pll
     JOIN product_labels pl ON pl.id = pll.label_id
     WHERE pll.product_id = ?
     ORDER BY pl.name",
    [$id]
  );
  $out['labels'] = array_map(fn($r)=> [
    'id'=>(int)$r['id'], 'name'=>$r['name'], 'icon'=>$r['icon']
  ], $labels);

  out(['ok'=>true,'data'=>$out]);

} catch (Throwable $e) {
  out(['ok'=>false,'error'=>$e->getMessage()], 500);
}