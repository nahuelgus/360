<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medios de pago</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body><?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Nuevo / Editar</h3>
    <form id="f"><input type="hidden" name="id">
      <div class="row">
        <input class="input col-4" name="name" placeholder="Nombre (p.ej. VISA débito)" required>
        <input class="input col-4" name="bank" placeholder="Banco/Adquirente (opcional)">
        <input class="input col-4" name="fee_percent" placeholder="% comisión (ej 1.8)" step="0.01">
        <label class="col-12"><input type="checkbox" name="is_active" checked> Activo</label>
      </div>
      <div class="sticky-actions"><button class="btn primary">Guardar</button></div>
    </form>
  </div>
  <div class="card"><h3>Listado</h3>
    <table class="table" id="t"><thead><tr><th>ID</th><th>Nombre</th><th>Banco</th><th>% comisión</th><th>Estado</th><th></th></tr></thead><tbody></tbody></table>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', f=document.getElementById('f');
f.addEventListener('submit', async e=>{e.preventDefault(); const j=await (await fetch(BASE+'/api/payments/save_method.php',{method:'POST', body:new FormData(f)})).json();
  if(!j.ok){ alert('Error'); return;} f.reset(); f.querySelector('[name=id]').value=''; load();});
async function load(){const j=await (await fetch(BASE+'/api/payments/list_methods.php')).json();
  const tb=document.querySelector('#t tbody'); tb.innerHTML=''; j.data.forEach(r=>{const tr=document.createElement('tr'); tr.innerHTML=`
    <td>${r.id}</td><td>${r.name}</td><td>${r.bank||''}</td><td>${r.fee_percent??0}%</td><td>${r.is_active==1?'Activo':'Inactivo'}</td>
    <td><button class="btn" onclick='edit(${JSON.stringify(r)})'>Editar</button></td>`; tb.appendChild(tr);});}
function edit(r){ for(const k of ['id','name','bank','fee_percent']){const el=f.querySelector('[name="'+k+'"]'); if(el) el.value=r[k]||'';} f.querySelector('[name="is_active"]').checked=parseInt(r.is_active)===1; f.querySelector('[name=id]').value=r.id;}
load();
</script></body></html>