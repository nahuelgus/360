<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];
$cfg = DB::one("SELECT * FROM mail_settings WHERE id=1");
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMTP</title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Configuración SMTP</h3>
    <form id="f">
      <div class="row">
        <input class="input col-6" name="smtp_host" placeholder="Host" value="<?= htmlspecialchars($cfg['smtp_host']??'') ?>">
        <input class="input col-3" name="smtp_port" placeholder="Puerto" value="<?= htmlspecialchars($cfg['smtp_port']??'') ?>">
        <select class="input col-3" name="secure">
          <?php foreach(['none','tls','ssl'] as $s): ?>
            <option value="<?= $s ?>" <?= (($cfg['secure']??'none')===$s?'selected':'') ?>><?= strtoupper($s) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="input col-6" name="smtp_user" placeholder="Usuario" value="<?= htmlspecialchars($cfg['smtp_user']??'') ?>">
        <input class="input col-6" type="password" name="smtp_pass" placeholder="Clave" value="<?= htmlspecialchars($cfg['smtp_pass']??'') ?>">
        <input class="input col-6" name="from_email" placeholder="From email" value="<?= htmlspecialchars($cfg['from_email']??'') ?>">
        <input class="input col-6" name="from_name" placeholder="From nombre" value="<?= htmlspecialchars($cfg['from_name']??'') ?>">
        <label class="col-6"><input type="checkbox" name="is_active" <?= !empty($cfg['is_active'])?'checked':''; ?>> Activo</label>
        <label class="col-6"><input type="checkbox" name="auto_send_ticketx" <?= !empty($cfg['auto_send_ticketx'])?'checked':''; ?>> Enviar Ticket X automáticamente</label>
      </div>
      <div class="sticky-actions">
        <button class="btn" type="button" onclick="testSend()">Probar envío</button>
        <button class="btn primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', f=document.getElementById('f');
f.addEventListener('submit', async e=>{
  e.preventDefault();
  const j=await (await fetch(BASE+'/api/mail/save_settings.php',{method:'POST', body:new FormData(f)})).json();
  alert(j.ok?'Guardado':'Error');
});
async function testSend(){
  const email=prompt('Enviar email de prueba a:'); if(!email) return;
  const fd=new FormData(f); fd.append('to',email);
  const j=await (await fetch(BASE+'/api/mail/test_send.php',{method:'POST', body:fd})).json();
  alert(j.ok?'Enviado':'Error: '+(j.error||''));
}
</script>
</body></html>