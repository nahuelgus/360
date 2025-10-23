<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$soc_id=intval($_GET['society_id']??0); $r=DB::one("SELECT society_id, env, api_key, api_secret, enabled, status FROM arca_accounts WHERE society_id=? LIMIT 1",[$soc_id]);
echo json_encode(['ok'=>true,'data'=>$r]);