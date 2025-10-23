<?php
// /360/app/api/products/save.php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

function out($arr, $code=200){ http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

try {
  // Normalizadores
  $str = fn($k)=> trim((string)($_POST[$k] ?? ''));
  $int = fn($k)=> (($_POST[$k] ?? '') === '' ? null : (int)$_POST[$k]);
  $bool = fn($k)=> isset($_POST[$k]) ? 1 : 0;

  // Soportar separadores de miles y coma decimal
  $dec = function($k){
    $v = trim((string)($_POST[$k] ?? ''));
    if ($v === '') return null;
    $v = str_replace(['$', ' '], '', $v);
    // Quita miles (.) y deja coma como decimal, o elimina comas si ya es US
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
      // formato 1.234,56
      $v = str_replace('.', '', $v);
      $v = str_replace(',', '.', $v);
    } else {
      // formato 1234,56 ó 1,234.56
      $v = str_replace(',', '.', $v);
    }
    return (float)$v;
  };

  $id     = $int('id');
  $sku    = $str('sku');
  $barcode= $str('barcode');
  $name   = $str('name');
  $unit   = $str('unit');          // enum en tu tabla
  $box    = $int('box_units');
  $price  = $dec('price');
  $cost   = $dec('cost');
  $active = $bool('is_active');
  $cat    = $int('category_id');
  $subcat = $int('subcategory_id');

  // labels[] llega desde el form
  $labels = $_POST['labels'] ?? [];
  if (!is_array($labels)) $labels = [];
  // Sanitizar a enteros únicos
  $labels = array_values(array_unique(array_map(fn($x)=> (int)$x, $labels)));
  $labels = array_filter($labels, fn($x)=> $x > 0);

  if ($name === '') out(['ok'=>false,'error'=>'Nombre requerido'], 400);

  $pdo = DB::pdo();
  $pdo->beginTransaction();

  if ($id) {
    // UPDATE
    DB::run(
      "UPDATE products SET
        sku=?, barcode=?, name=?, unit=?, box_units=?, price=?, cost=?, is_active=?,
        category_id=?, subcategory_id=?
       WHERE id=?",
      [$sku ?: null, $barcode ?: null, $name, $unit ?: null, $box,
       $price ?? 0, $cost ?? 0, $active ? 1 : 0, $cat, $subcat, $id]
    );
  } else {
    // INSERT
    DB::run(
      "INSERT INTO products (sku,barcode,name,unit,box_units,price,cost,is_active,created_at,category_id,subcategory_id)
       VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)",
      [$sku ?: null, $barcode ?: null, $name, $unit ?: null, $box,
       $price ?? 0, $cost ?? 0, $active ? 1 : 0, $cat, $subcat]
    );
    $id = (int)$pdo->lastInsertId();
  }

  // === Etiquetas: tabla puente product_label_links(product_id,label_id) ===
  // Limpio existentes
  DB::run("DELETE FROM product_label_links WHERE product_id=?", [$id]);

  // Inserto nuevas (si hay)
  if (!empty($labels)) {
    $vals = [];
    $params = [];
    foreach ($labels as $labId) {
      $vals[] = "(?,?)";
      $params[] = $id;
      $params[] = $labId;
    }
    $sql = "INSERT INTO product_label_links (product_id, label_id) VALUES ".implode(',', $vals);
    DB::run($sql, $params);
  }

  $pdo->commit();
  out(['ok'=>true,'id'=>$id]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  out(['ok'=>false,'error'=>$e->getMessage()], 500);
}