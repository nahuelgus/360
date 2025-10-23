<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; $choices=DB::all("SELECT id,name FROM societies WHERE is_active=1 ORDER BY name"); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sucursales</title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Nueva / Editar sucursal</h3>
    <form id="fBr"><input type="hidden" name="id" id="br_id">
      <div class="row">
        <select class="input col-6" name="society_id" required><option value="">— Razón social —</option>
          <?php foreach($choices as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
        </select>
        <input class="input col-6" name="name" placeholder="Nombre sucursal *" required>
        <input class="input col-6" name="address" placeholder="Dirección"><input class="input col-3" name="city" placeholder="Ciudad">
        <input class="input col-3" name="state" placeholder="Provincia"><input class="input col-3" name="postal_code" placeholder="CP">
        <input class="input col-6" name="manager" placeholder="Encargado/a"><label class="col-6"><input type="checkbox" name="is_active" checked> Activa</label>
      </div><div class="sticky-actions"><button class="btn" type="button" onclick="fBr.reset();br_id.value=''">Limpiar</button><button class="btn primary">Guardar</button></div>
    </form>
  </div>
  <div class="card"><h3>Listado</h3>
    <table class="table" id="tBr"><thead><tr><th>ID</th><th>Razón social</th><th>Sucursal</th><th>Activa</th><th></th></tr></thead><tbody></tbody></table>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', fBr=document.getElementById('fBr');
fBr.addEventListener('submit',async e=>{e.preventDefault(); const j=await (await fetch(BASE+'/api/branches/save.php',{method:'POST',body:new FormData(fBr)})).json();
  if(!j.ok){ alert(j.error||'Error'); return;} fBr.reset(); br_id.value=''; load();});
async function load(){const j=await (await fetch(BASE+'/api/branches/list.php')).json(); const tb=document.querySelector('#tBr tbody'); tb.innerHTML='';
  j.data.forEach(r=>{const tr=document.createElement('tr'); tr.innerHTML=`<td>${r.id}</td><td>${r.society_name||''}</td><td>${r.name}</td><td>${r.is_active==1?'Sí':'No'}</td>
    <td><button class="btn" onclick='edit(${JSON.stringify(r)})'>Editar</button></td>`; tb.appendChild(tr);});}
function edit(r){for(const k of ['id','society_id','name','address','city','state','postal_code','manager']){const el=fBr.querySelector('[name="'+k+'"]'); if(el) el.value=r[k]||'';} fBr.querySelector('[name="is_active"]').checked=parseInt(r.is_active)===1; br_id.value=r.id;}
load();
</script></body></html>