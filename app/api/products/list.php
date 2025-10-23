<?php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

$q     = trim($_GET['q'] ?? '');
$limit = max(1, min(50, intval($_GET['limit'] ?? 20)));

try {
  // columnas principales (no dependemos de ONLY_FULL_GROUP_BY)
  $sqlBase = "SELECT p.id, p.name, p.price, p.barcode, p.sku, p.unit,
                     la.lbl AS _lbl
              FROM products p
              LEFT JOIN (
                SELECT pll.product_id,
                       GROUP_CONCAT(CONCAT(l.id,':', COALESCE(l.name,''),':', COALESCE(l.icon_path,'')) SEPARATOR '|') AS lbl
                FROM product_label_links pll
                JOIN product_labels l ON l.id = pll.label_id
                GROUP BY pll.product_id
              ) la ON la.product_id = p.id";

  $params = [];
  if ($q === '') {
    $sql = $sqlBase . " ORDER BY p.id DESC LIMIT $limit";
  } else {
    $where = ["p.name LIKE ?"];
    $params[] = '%'.$q.'%';
    if ($q !== '') { $where[] = "p.barcode = ?"; $params[] = $q; }
    if ($q !== '') { $where[] = "p.sku = ?";     $params[] = $q; }
    if (ctype_digit($q)) { $where[] = "p.id = ?"; $params[] = (int)$q; }
    $sql = $sqlBase . " WHERE ".implode(' OR ', $where)." ORDER BY p.name ASC LIMIT $limit";
  }

  $rows = DB::all($sql, $params);

  // parsear etiquetas a array
  foreach ($rows as &$r) {
    $r['price'] = (float)$r['price'];
    $labels = [];
    if (!empty($r['_lbl'])) {
      foreach (explode('|', $r['_lbl']) as $chunk) {
        [$id,$name,$icon] = array_pad(explode(':',$chunk,3),3,'');
        $labels[] = ['id'=>(int)$id,'name'=>$name,'icon'=>$icon];
      }
    }
    unset($r['_lbl']);
    $r['labels'] = $labels;
  }

  echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
