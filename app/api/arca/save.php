<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$soc_id=intval($_POST['society_id']??0); $env=$_POST['env']??'none'; $key=trim($_POST['api_key']??''); $sec=trim($_POST['api_secret']??''); $en= isset($_POST['enabled'])?1:0;
$exists=DB::one("SELECT society_id FROM arca_accounts WHERE society_id=? LIMIT 1",[$soc_id]);
if($exists){ DB::run("UPDATE arca_accounts SET env=?, api_key=?, api_secret=?, enabled=?, status=? WHERE society_id=?",[$env,$key,$sec,$en, ($en&&$env!=='none'?'ready':'disabled'), $soc_id]); }
else { DB::run("INSERT INTO arca_accounts (society_id,env,api_key,api_secret,enabled,status) VALUES (?,?,?,?,?,?)",[$soc_id,$env,$key,$sec,$en, ($en&&$env!=='none'?'ready':'disabled')]); }
echo json_encode(['ok'=>true]);