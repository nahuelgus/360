<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$rows=DB::all("SELECT b.id,b.society_id,s.name AS society_name,b.name,b.address,b.city,b.state,b.postal_code,b.manager,b.is_active FROM branches b LEFT JOIN societies s ON s.id=b.society_id ORDER BY b.id DESC");
echo json_encode(['ok'=>true,'data'=>$rows]);