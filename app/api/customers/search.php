<?php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$limit = max(1,min(20,intval($_GET['limit'] ?? 12)));
if($q===''){ echo json_encode(['ok'=>true,'data'=>[]]); exit; }

$rows = DB::all("
  SELECT id, dni, name, email
    FROM customers
   WHERE dni LIKE ? OR name LIKE ?
   ORDER BY name ASC
   LIMIT $limit
", ['%'.$q.'%', '%'.$q.'%']);

echo json_encode(['ok'=>true,'data'=>$rows]);
