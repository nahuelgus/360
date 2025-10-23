<?php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode(['ok'=>false,'error'=>'Búsqueda vacía']); exit; }

try{
  $isCode = preg_match('/^[0-9A-Za-z\-]{6,}$/', $q) || ctype_digit($q);

  $sql = "SELECT p.id, p.name, p.price,
                 la.lbl AS _lbl
          FROM products p
          LEFT JOIN (
            SELECT pll.product_id,
                   GROUP_CONCAT(CONCAT(l.id,':', COALESCE(l.name,''),':', COALESCE(l.icon_path,'')) SEPARATOR '|') AS lbl
            FROM product_label_links pll
            JOIN product_labels l ON l.id = pll.label_id
            GROUP BY pll.product_id
          ) la ON la.product_id = p.id";

  $p = null;
  if ($isCode){
    $p = DB::one($sql." WHERE p.barcode=? OR p.sku=? OR p.id=? LIMIT 1", [$q,$q,(int)$q]);
  }
  if (!$p){
    $p = DB::one($sql." WHERE p.name LIKE ? ORDER BY p.name ASC LIMIT 1", ['%'.$q.'%']);
  }
  if (!$p){ echo json_encode(['ok'=>false,'error'=>'No encontrado']); exit; }

  $p['price'] = (float)$p['price'];
  $labels=[];
  if (!empty($p['_lbl'])){
    foreach (explode('|',$p['_lbl']) as $chunk){
      [$id,$name,$icon] = array_pad(explode(':',$chunk,3),3,'');
      $labels[] = ['id'=>(int)$id,'name'=>$name,'icon'=>$icon];
    }
  }
  unset($p['_lbl']);
  $p['labels']=$labels;

  echo json_encode(['ok'=>true,'data'=>$p], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
