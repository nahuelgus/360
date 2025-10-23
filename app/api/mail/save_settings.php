<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$in=$_POST;
$vals=[
  'smtp_host'=>trim($in['smtp_host']??''),'smtp_port'=>intval($in['smtp_port']??0),'smtp_user'=>trim($in['smtp_user']??''),
  'smtp_pass'=>trim($in['smtp_pass']??''),'secure'=>($in['secure']??'none'),'from_email'=>trim($in['from_email']??''),
  'from_name'=>trim($in['from_name']??''),'is_active'=>isset($in['is_active'])?1:0,'auto_send_ticketx'=>isset($in['auto_send_ticketx'])?1:0
];
$exists=DB::one("SELECT id FROM mail_settings WHERE id=1");
if($exists){
  DB::run("UPDATE mail_settings SET smtp_host=?,smtp_port=?,smtp_user=?,smtp_pass=?,secure=?,from_email=?,from_name=?,is_active=?,auto_send_ticketx=? WHERE id=1",
    [$vals['smtp_host'],$vals['smtp_port'],$vals['smtp_user'],$vals['smtp_pass'],$vals['secure'],$vals['from_email'],$vals['from_name'],$vals['is_active'],$vals['auto_send_ticketx']]);
}else{
  DB::run("INSERT INTO mail_settings (id,smtp_host,smtp_port,smtp_user,smtp_pass,secure,from_email,from_name,is_active,auto_send_ticketx) VALUES (1,?,?,?,?,?,?,?,?,?)",
    [$vals['smtp_host'],$vals['smtp_port'],$vals['smtp_user'],$vals['smtp_pass'],$vals['secure'],$vals['from_email'],$vals['from_name'],$vals['is_active'],$vals['auto_send_ticketx']]);
}
echo json_encode(['ok'=>true]);
