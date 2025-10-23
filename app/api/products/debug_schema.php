<?php
// /360/app/api/products/debug_schema.php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

$out = ['ok'=>true, 'info'=>[]];

try {
  // columnas reales
  $cols = DB::all("
    SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_NULLABLE AS nullable
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
    ORDER BY ORDINAL_POSITION
  ");
  $out['columns'] = $cols;

  // índice aproximado
  $count = DB::one("SELECT COUNT(*) AS n FROM products");
  $out['count'] = intval($count['n'] ?? 0);

  // helper de mapeo
  $has = array_flip(array_map(fn($r)=> strtolower($r['name']), $cols));
  $pick = function(array $cands) use ($has) {
    foreach ($cands as $c) { if (isset($has[strtolower($c)])) return $c; }
    return null;
  };

  $map = [
    'id'     => 'id',
    'name'   => $pick(['name','nombre','product_name']),
    'price'  => $pick(['price','precio','sale_price','precio_venta','pvp']),
    'barcode'=> $pick(['barcode','ean','codigo_barras','cod_barra','codebar','cod_bar','codigo']),
    'sku'    => $pick(['sku','code','codigo_sku','sku_interno']),
  ];
  $out['map'] = $map;

  // prueba de query segura (sin placeholders en LIMIT)
  $sel = "`{$map['id']}` AS id, `{$map['name']}` AS name";
  if ($map['price'])   $sel .= ", `{$map['price']}` AS price";
  if ($map['barcode']) $sel .= ", `{$map['barcode']}` AS barcode";
  if ($map['sku'])     $sel .= ", `{$map['sku']}` AS sku";

  $limit = 3;
  $test = DB::all("SELECT $sel FROM products ORDER BY `{$map['id']}` DESC LIMIT $limit");
  $out['sample'] = $test;

} catch (Throwable $e) {
  $out['ok'] = false;
  $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
