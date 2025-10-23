<?php
require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor','vendedor']);
header('Content-Type: application/json');
$rows=DB::all("SELECT id,type,code,initial_amount,remaining_amount,is_active,created_at
                 FROM sale_refunds ORDER BY id DESC LIMIT 50");
echo json_encode(['ok'=>true,'data'=>$rows]);
