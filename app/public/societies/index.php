<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Razones sociales</title><link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Nueva / Editar razón social</h3>
    <form id="fSoc"><input type="hidden" name="id" id="soc_id">
      <div class="row">
        <input class="input col-6" name="name" placeholder="Nombre legal *" required>
        <input class="input col-6" name="tax_id" placeholder="CUIT/CUIL">
        <input class="input col-6" name="email" placeholder="Email">
        <input class="input col-6" name="phone" placeholder="Teléfono">
        <input class="input col-6" name="address" placeholder="Dirección">
        <input class="input col-3" name="city" placeholder="Ciudad">
        <input class="input col-3" name="state" placeholder="Provincia">
        <input class="input col-3" name="postal_code" placeholder="Código postal">
        <label class="col-6"><input type="checkbox" name="is_active" checked> Activa</label>
      </div>
      <div class="sticky-actions"><button class="btn" type="button" onclick="fSoc.reset();soc_id.value=''">Limpiar</button><button class="btn primary">Guardar</button></div>
    </form>
  </div>
  <div class="card"><h3>Listado</h3>
    <table class="table" id="tSoc"><thead><tr><th>ID</th><th>Nombre</th><th>CUIT</th><th>Activa</th><th>ARCA</th><th></th></tr></thead><tbody></tbody></table>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', f=document.getElementById('fSoc');
f.addEventListener('submit',async e=>{e.preventDefault(); const res=await fetch(BASE+'/api/societies/save.php',{method:'POST',body:new FormData(f)});
  const j=await res.json(); if(!j.ok){alert('Error'); return;} f.reset(); soc_id.value=''; load();});
async function load(){const j=await (await fetch(BASE+'/api/societies/list.php')).json();
  const tb=document.querySelector('#tSoc tbody'); tb.innerHTML='';
  j.data.forEach(r=>{const tr=document.createElement('tr'); tr.innerHTML=`
    <td>${r.id}</td><td>${r.name}</td><td>${r.tax_id||''}</td><td>${r.is_active==1?'Sí':'No'}</td>
    <td><a class="btn" href="${BASE}/public/societies/arca.php?society_id=${r.id}">Credenciales</a></td>
    <td><button class="btn" onclick='edit(${JSON.stringify(r)})'>Editar</button></td>`; tb.appendChild(tr);});
}
function edit(r){for(const k of ['id','name','tax_id','email','phone','address','city','state','postal_code']){const el=f.querySelector('[name="'+k+'"]'); if(el) el.value=r[k]||'';} f.querySelector('[name="is_active"]').checked=parseInt(r.is_active)===1; soc_id.value=r.id;}
load();
</script>
</body></html>