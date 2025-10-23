<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; $society_id=intval($_GET['society_id']??0);
$soc=DB::one("SELECT id,name FROM societies WHERE id=?",[$society_id]);
if(!$soc){ echo 'Sociedad no encontrada'; exit; } ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ARCA – <?= htmlspecialchars($soc['name']) ?></title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>ARCA – <?= htmlspecialchars($soc['name']) ?></h3>
    <form id="f"><input type="hidden" name="society_id" value="<?= $soc['id'] ?>">
      <div class="row">
        <select class="input col-4" name="env" required>
          <option value="none">No configurado</option>
          <option value="sandbox">Sandbox</option>
          <option value="production">Producción</option>
        </select>
        <input class="input col-4" name="api_key" placeholder="API Key">
        <input class="input col-4" name="api_secret" placeholder="API Secret">
        <label class="col-12"><input type="checkbox" name="enabled"> Habilitado</label>
      </div>
      <div class="sticky-actions"><button class="btn primary">Guardar</button></div>
    </form>
  </div>
  <div class="card"><h3>Estado</h3><div id="st" class="small">Cargando…</div></div>
</div>
<script>
const BASE='<?= $BASE ?>', society_id=<?= $soc['id'] ?>, f=document.getElementById('f');
async function load(){const j=await (await fetch(BASE+'/api/arca/get.php?society_id='+society_id)).json();
  if(j.data){ for(const k of ['env','api_key','api_secret']){ const el=f.querySelector('[name="'+k+'"]'); if(el) el.value=j.data[k]||''; }
    f.querySelector('[name="enabled"]').checked = j.data.enabled==1; document.getElementById('st').textContent='Estado: '+(j.data.status||'—');
  } else { document.getElementById('st').textContent='No configurado'; } }
f.addEventListener('submit', async e=>{e.preventDefault(); const j=await (await fetch(BASE+'/api/arca/save.php',{method:'POST', body:new FormData(f)})).json();
  if(!j.ok){alert('Error'); return;} load();});
load();
</script></body></html>