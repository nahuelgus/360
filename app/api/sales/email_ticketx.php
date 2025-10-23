<?php require_once __DIR__.'/../../lib/auth.php'; header('Content-Type: application/json');
$in=json_decode(file_get_contents('php://input'),true);
$sale_id=intval($in['sale_id']??0); $to=trim($in['to']??'');
if(!$sale_id || $to===''){ echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos']); exit; }

$cfg=DB::one("SELECT * FROM mail_settings WHERE id=1 AND is_active=1");
if(!$cfg){ echo json_encode(['ok'=>false,'error'=>'SMTP no configurado']); exit; }

$sale=DB::one("SELECT s.*, b.name AS branch, so.name AS society FROM sales s
  LEFT JOIN branches b ON b.id=s.branch_id LEFT JOIN societies so ON so.id=s.society_id WHERE s.id=?",[$sale_id]);
$items=DB::all("SELECT si.qty,si.unit_price,si.discount_pct,p.name FROM sale_items si LEFT JOIN products p ON p.id=si.product_id WHERE si.sale_id=?",[$sale_id]);

ob_start(); ?>
<h3>Ticket X – Venta #<?= $sale['id'] ?></h3>
<div>Sociedad: <b><?= htmlspecialchars($sale['society']) ?></b> · Sucursal: <b><?= htmlspecialchars($sale['branch']) ?></b></div>
<div>Fecha: <?= htmlspecialchars($sale['created_at']) ?></div>
<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>Producto</th><th>Cant</th><th>P.Unit</th><th>%Desc</th><th>Total</th></tr></thead><tbody>
<?php foreach($items as $it): $line=$it['qty']*$it['unit_price']*(1-($it['discount_pct']??0)/100); ?>
<tr><td><?= htmlspecialchars($it['name']) ?></td><td><?= $it['qty'] ?></td><td>$<?= number_format($it['unit_price'],2,',','.') ?></td><td><?= $it['discount_pct']??0 ?></td><td>$<?= number_format($line,2,',','.') ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><b>Total:</b> $<?= number_format($sale['total'],2,',','.') ?></p>
<?php $html = ob_get_clean();

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
  $mail->isHTML(true);
  $mail->Subject='Ticket X Venta #'.$sale['id'];
  $mail->Body=$html;
  $mail->send();
  DB::run("INSERT INTO mail_log (sale_id,to_email,subject,status) VALUES (?,?,?,'sent')",[$sale_id,$to,$mail->Subject]);
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  DB::run("INSERT INTO mail_log (sale_id,to_email,subject,status,detail) VALUES (?,?,?,'error',?)",[$sale_id,$to,'Ticket X',$e->getMessage()]);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
