<?php
require_once __DIR__.'/../../api/auth/require_login.php'; require_role(['admin','supervisor','vendedor']);
$BASE=(require __DIR__.'/../../config/.env.php')['app']['base_url'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Devoluciones / Vouchers</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
</head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>
<div class="container">
  <div class="card">
    <h3>Crear devolución</h3>
    <form id="f">
      <div class="row">
        <select class="input col-4" name="type">
          <option value="voucher">Voucher</option>
          <option value="cash">Efectivo</option>
        </select>
        <input class="input col-4" name="sale_id" placeholder="Venta # (opcional)">
        <input class="input col-4" name="customer_dni" placeholder="DNI cliente (opcional)">
        <input class="input col-6" name="amount" placeholder="Monto" inputmode="decimal" required>
        <input class="input col-6" name="reason" placeholder="Motivo (opcional)">
      </div>
      <div class="sticky-actions">
        <button class="btn primary">Generar</button>
      </div>
    </form>
    <div id="out" class="small" style="margin-top:8px"></div>
  </div>

  <div class="card">
    <h3>Vouchers recientes</h3>
    <table class="table" id="t"><thead>
      <tr><th>Código</th><th>Monto</th><th>Restante</th><th>Estado</th><th>Fecha</th></tr>
    </thead><tbody></tbody></table>
  </div>
</div>
<script>
const BASE='<?= $BASE ?>', f=document.getElementById('f'), out=document.getElementById('out');
f.addEventListener('submit', async e=>{
  e.preventDefault();
  const j=await (await fetch(BASE+'/api/refunds/create.php',{method:'POST', body:new FormData(f)})).json();
  if(!j.ok){ out.textContent='Error: '+(j.error||''); return;}
  if(j.type==='voucher'){
    out.innerHTML = 'Voucher creado: <b>'+j.code+'</b> por $'+Number(j.amount).toFixed(2)
      +' · <a href="'+BASE+'/public/refunds/receipt.php?code='+encodeURIComponent(j.code)+'" target="_blank">Imprimir</a>';
  }else{
    out.textContent = 'Devolución en efectivo creada (egreso de caja registrado).';
  }
  load();
});
async function load(){
  const res=await fetch(BASE+'/api/refunds/list_recent.php'); const j=await res.json();
  const tb=document.querySelector('#t tbody'); tb.innerHTML='';
  j.data.forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `<td>${r.code||'(efectivo)'}</td><td>$${Number(r.initial_amount).toFixed(2)}</td>
      <td>${r.code?'$'+Number(r.remaining_amount).toFixed(2):'-'}</td>
      <td>${r.is_active==1?'Activo':'Cerrado'}</td><td>${r.created_at}</td>`;
    tb.appendChild(tr);
  });
}
load();
</script>
</body></html>
