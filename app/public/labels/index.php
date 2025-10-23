<?php require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor','stock']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url']; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Etiquetas</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css"></head><body><?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card"><h3>Nueva etiqueta</h3>
    <form id="f"><input type="hidden" name="id"><div class="row">
      <input class="input col-6" name="name" placeholder="Nombre (SIN TACC / KETO / VEGANO...)" required>
      <input class="input col-6" name="icon_path" placeholder="URL de icono (90x90 recomendado)">
    </div><div class="sticky-actions"><button class="btn primary">Guardar</button></div></form>
  </div>
  <div class="card"><h3>Listado</h3>
    <table class="table" id="t"><thead><tr><th>ID</th><th>Icono</th><th>Nombre</th><th></th></tr></thead><tbody></tbody></table>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', f=document.getElementById('f');
f.addEventListener('submit', async e=>{e.preventDefault(); const j=await (await fetch(BASE+'/api/labels/save.php',{method:'POST', body:new FormData(f)})).json();
  if(!j.ok){ alert('Error'); return;} f.reset(); f.querySelector('[name=id]').value=''; load();});
async function load(){const j=await (await fetch(BASE+'/api/labels/list.php')).json(); const tb=document.querySelector('#t tbody'); tb.innerHTML='';
  j.data.forEach(r=>{const tr=document.createElement('tr'); tr.innerHTML=`<td>${r.id}</td><td>${r.icon_path?'<img src="'+r.icon_path+'" style="height:26px">':''}</td><td>${r.name}</td>
  <td><button class="btn" onclick='edit(${JSON.stringify(r)})'>Editar</button></td>`; tb.appendChild(tr);});}
function edit(r){ for(const k of ['id','name','icon_path']){const el=f.querySelector('[name="'+k+'"]'); if(el) el.value=r[k]||'';} }
load();
</script></body></html>