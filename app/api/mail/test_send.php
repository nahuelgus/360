<?php require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']); header('Content-Type: application/json');
$cfg=DB::one("SELECT * FROM mail_settings WHERE id=1 AND is_active=1");
$to=trim($_POST['to']??'');
if(!$cfg || !$to){ echo json_encode(['ok'=>false,'error'=>'Config/To inválido']); exit; }

require_once __DIR__.'/../../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__.'/../../vendor/PHPMailer/SMTP.php';
require_once __DIR__.'/../../vendor/PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail=new PHPMailer(true);
try{
  $mail->isSMTP(); $mail->Host=$cfg['smtp_host']; $mail->Port=intval($cfg['smtp_port']);
  if($cfg['secure']!=='none') $mail->SMTPSecure=$cfg['secure'];
  $mail->SMTPAuth=true; $mail->Username=$cfg['smtp_user']; $mail->Password=$cfg['smtp_pass'];
  $mail->setFrom($cfg['from_email'],$cfg['from_name']); $mail->addAddress($to);
  $mail->Subject='Prueba SMTP ND'; $mail->Body='Prueba OK';
  $mail->send(); echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
