<?php
// /360/app/api/customers/lookup.php
require_once __DIR__.'/../../lib/auth.php';
header('Content-Type: application/json');

$dni = trim($_GET['dni'] ?? '');
if ($dni === '') { echo json_encode(['ok'=>false,'error'=>'DNI vacío']); exit; }

$c = DB::one("SELECT id,dni,name,email FROM customers WHERE dni=? LIMIT 1", [$dni]);
if (!$c) { echo json_encode(['ok'=>false]); exit; }

$r = DB::one("SELECT COALESCE(SUM(points),0) AS p FROM loyalty_points_ledger WHERE customer_id=?", [$c['id']]);
$pts = intval($r['p'] ?? 0);

echo json_encode(['ok'=>true,'data'=>$c,'points'=>$pts]);