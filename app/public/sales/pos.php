<?php
// /360/app/public/sales/pos.php
require_once __DIR__ . '/../../api/auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php';

$cfg   = require __DIR__.'/../../config/.env.php';
$BASE  = $cfg['app']['base_url'] ?? '/360/app';

$branch_id   = $_SESSION['branch_id']   ?? null;
$register_id = $_SESSION['register_id'] ?? null;

// Métodos de pago
$methods = DB::all("SELECT id,name FROM payment_methods WHERE COALESCE(is_active,1)=1 ORDER BY name");

// Defaults de la sucursal activa
$branch = null;
$def_letter = 'B';
$def_pos    = 1;
if ($branch_id) {
  $branch = DB::one("SELECT id,name, COALESCE(default_doc_letter,'B') AS def_letter, COALESCE(default_pos_number,1) AS def_pos 
                     FROM branches WHERE id=?", [$branch_id]);
  if ($branch) {
    $def_letter = $branch['def_letter'];
    $def_pos    = (int)$branch['def_pos'];
  }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  :root{ --bar-h: 74px; }
  body{ padding-bottom: var(--bar-h); background:#f6f7f9 }
  .pos{display:grid;grid-template-columns:2fr 1fr;gap:14px}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.06);padding:12px}
  .help{color:#667;font-size:.9rem}
  .chip{display:inline-flex;align-items:center;gap:8px;background:#f3f6f8;border:1px solid #e4eaee;border-radius:999px;padding:6px 10px;margin-top:8px}
  .chip b{color:#234}
  .chip .x{border:none;background:transparent;cursor:pointer;font-weight:700;opacity:.6}
  .chip .x:hover{opacity:1}
  .checkout-bar{
    position:fixed;left:0;right:0;bottom:0;height:var(--bar-h);
    background:#fff;border-top:1px solid #e8ecef;display:flex;align-items:center;gap:12px;
    padding:12px 16px;z-index:30;box-shadow:0 -6px 16px rgba(0,0,0,.04);
  }
  .checkout-bar .sp{flex:1}
  .checkout-total{font-weight:800;font-size:1.05rem}
  @media (max-width:980px){ .pos{grid-template-columns:1fr} }

  .ac-wrap{position:relative}
  .ac-list{
    position:absolute;left:0;right:0;top:100%;z-index:25;
    background:#fff;border:1px solid #e6eaee;border-top:none;border-radius:0 0 10px 10px;
    box-shadow:0 10px 18px rgba(0,0,0,.05); max-height:280px; overflow:auto; display:none;
  }
  .ac-item{display:flex;align-items:center;gap:10px;padding:9px 10px;cursor:pointer}
  .ac-item:hover,.ac-item.active{background:#f5f8fb}
  .ac-name{flex:1}
  .ac-price{min-width:96px;text-align:right;font-variant-numeric:tabular-nums}
  .ac-code{color:#6b7785;font-size:.86rem}

  .tag-icons img{height:20px;display:inline-block;margin-right:6px;vertical-align:middle}
  .qty-wrap{display:flex;gap:6px;align-items:center}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#2D6869;color:#fff;font-size:12px}
</style>
</head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>

<div class="container">
  <?php if(!$branch_id): ?><div class="card">Seleccioná una <b>sucursal</b> en el navbar para operar.</div><?php endif; ?>
  <?php if(!$register_id): ?><div class="card">Seleccioná una <b>caja</b> y abrí turno para vender.</div><?php endif; ?>

  <div class="pos">
    <div>
      <div class="card">
        <h3>Cliente (opcional)</h3>
        <form id="fCli" onsubmit="return setCustomer()">
          <div style="display:flex;gap:8px;align-items:center">
            <input class="input" id="dni" placeholder="DNI del cliente…">
            <button class="btn">Usar</button>
            <button type="button" class="btn" onclick="openRegister()">Registrar</button>
          </div>
        </form>

        <div class="ac-wrap" style="margin-top:8px">
          <input class="input" id="csearch" placeholder="Buscar cliente por nombre o DNI…" autocomplete="off">
          <div id="clist" class="ac-list"></div>
        </div>

        <div id="cliInfo" class="help"></div>
      </div>

      <div class="card">
        <div style="display:flex;gap:8px;align-items:center;justify-content:space-between">
          <h3>Buscar / escanear</h3>
          <?php if($branch): ?>
            <span class="badge">Sucursal: <?= htmlspecialchars($branch['name']) ?> · Pto.Vta: <b id="pos_number"><?= (int)$def_pos ?></b></span>
          <?php endif; ?>
        </div>
        <form id="fAdd" onsubmit="return addItem()">
          <div class="ac-wrap">
            <input class="input" id="scan" placeholder="Escanear código o buscar por nombre…" autocomplete="off" autofocus>
            <div id="acList" class="ac-list"></div>
          </div>
        </form>

        <table class="table" id="tItems">
          <thead>
            <tr>
              <th>Producto</th><th>Cant</th><th>P. Unit</th><th>% Desc</th><th>Total</th><th>Etiq.</th><th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h3>Comprobante</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select id="docMode" class="input" style="max-width:220px">
          <option value="TICKET_X" selected>Ticket X (no fiscal)</option>
          <option value="INVOICE">Factura</option>
        </select>
        <select id="cbteLetter" class="input" style="max-width:120px">
          <option value="A">A</option>
          <option value="B" <?= $def_letter==='B'?'selected':'' ?>>B</option>
          <option value="C" <?= $def_letter==='C'?'selected':'' ?>>C</option>
        </select>
      </div>

      <h3 style="margin-top:12px">Pagos</h3>
      <div id="pays"></div>
      <button class="btn" onclick="addPay()">+ Agregar pago</button>
      <div class="help" style="margin-top:6px">Si usás <b>Voucher</b>, escribí el <b>código</b> en “Ref”.</div>

      <h3 style="margin-top:12px">Puntos</h3>
      <div class="help">Disponibles: <b id="ptsAvail">0</b>. Usar:
        <input class="input" id="ptsUse" style="width:100px" value="0" inputmode="numeric">
      </div>

      <div style="margin-top:12px"><b>Total:</b> <span id="grand">$0.00</span></div>
    </div>
  </div>
</div>

<div class="checkout-bar">
  <div class="checkout-total">Total: <span id="grandFixed">$0.00</span></div>
  <div class="sp"></div>
  <button class="btn" onclick="clearCart()">Limpiar</button>
  <button class="btn primary" onclick="confirmSale()">Confirmar venta</button>
</div>

<script>
const BASE='<?= $BASE ?>',
      methods=<?= json_encode($methods) ?>,
      BRANCH_ID=<?= $branch_id? (int)$branch_id : 'null' ?>,
      REGISTER_ID=<?= $register_id? (int)$register_id : 'null' ?>;

// ===== Carrito / formateo
let cart=[], payments=[], customer=null, customerPoints=0;

const moneyUS = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const mfmt = n => '$' + moneyUS.format(Number(n)||0);

function render(){
  const tb=document.querySelector('#tItems tbody'); tb.innerHTML='';
  let total=0;
  cart.forEach((it,idx)=>{
    const line=it.qty*it.price*(1 - (it.discount||0)/100); total+=line;
    const tags = (it.labels || []).map(l => l.icon ? `<img src="${l.icon}" alt="${l.name||''}" title="${l.name||''}">` : '').join('');
    const tr=document.createElement('tr'); tr.innerHTML=`
      <td>${it.name}</td>
      <td>
        <div class="qty-wrap">
          <button class="btn" title="Quitar 1" onclick="chgQty(${idx},-1)">➖</button>
          <input class="input" style="width:80px;text-align:center" value="${it.qty}"
                 oninput="cart[${idx}].qty=Number(this.value)||1; render()">
          <button class="btn" title="Sumar 1" onclick="chgQty(${idx},1)">➕</button>
        </div>
      </td>
      <td>${mfmt(it.price)}</td>
      <td><input class="input" style="width:80px" value="${it.discount||0}" oninput="cart[${idx}].discount=Number(this.value)||0; render()"></td>
      <td>${mfmt(line)}</td>
      <td class="tag-icons">${tags}</td>
      <td><button class="btn" onclick="cart.splice(${idx},1); render()">✕</button></td>`;
    tb.appendChild(tr);
  });
  document.getElementById('grand').textContent = mfmt(total);
  document.getElementById('grandFixed').textContent = mfmt(total);
  document.getElementById('cbteLetter').disabled = document.getElementById('docMode').value!=='INVOICE';
}
function chgQty(i, d){ cart[i].qty = Math.max(1, (cart[i].qty||1) + d); render(); }

// ===== Clientes (Funcionalidad Original Mantenida)
let cTimer=null, cData=[], cIdx=-1;
const csearch = document.getElementById('csearch'), clist = document.getElementById('clist');
async function setCustomer(){
  const d = document.getElementById('dni').value.trim(); if(!d) return false;
  try{
    const r = await fetch(BASE+'/api/customers/lookup.php?dni='+encodeURIComponent(d));
    const j = await r.json();
    if(j.ok && j.data){ customer=j.data; customerPoints=j.points||0; paintCustomer(); }
    else{ Swal.fire('Error', 'Cliente no encontrado', 'error'); }
  }catch(e){ Swal.fire('Error', 'No se pudo buscar el cliente: '+e.message, 'error'); }
  return false;
}
function paintCustomer(){
  const box=document.getElementById('cliInfo');
  if(!customer){ box.innerHTML=''; document.getElementById('ptsAvail').textContent='0'; document.getElementById('ptsUse').value='0'; return; }
  box.innerHTML = `<span class="chip"><span>Cliente: <b>${customer.name}</b> (DNI ${customer.dni}) · Puntos: <b>${customerPoints}</b></span><button class="x" title="Quitar" onclick="clearCustomer()">×</button></span>`;
  document.getElementById('ptsAvail').textContent = String(customerPoints);
}
function clearCustomer(){ customer=null; customerPoints=0; paintCustomer(); }
function openRegister(){ const d=document.getElementById('dni').value.trim(); window.open(BASE+'/public/clients/index.php'+(d?('?dni='+encodeURIComponent(d)):'') ,'_blank'); }
csearch.addEventListener('input', ()=>{ const q=csearch.value.trim(); if(cTimer) clearTimeout(cTimer); if(q.length<2){ hideC(); return;} cTimer=setTimeout(()=> fetchCustomers(q), 220); });
csearch.addEventListener('keydown', e=>{ if(clist.style.display!=='none'){ if(e.key==='ArrowDown'){e.preventDefault();moveC(1);} else if(e.key==='ArrowUp'){e.preventDefault();moveC(-1);} else if(e.key==='Enter'){ if(cIdx>=0){e.preventDefault();pickC(cIdx);} } else if(e.key==='Escape'){hideC();}} });
async function fetchCustomers(q){ try{ const j = await (await fetch(`${BASE}/api/customers/search.php?q=${encodeURIComponent(q)}&limit=8`)).json(); if(j.ok) { cData=j.data||[]; renderC(); } else { hideC(); } }catch(_){ hideC(); } }
function renderC(){ clist.innerHTML=''; if(!cData.length){ hideC(); return; } cData.forEach((c,i)=>{ const div=document.createElement('div'); div.className='ac-item'; div.innerHTML=`<div class="ac-name">${c.name} · DNI ${c.dni}</div>`; div.onclick = ()=> pickC(i); clist.appendChild(div); }); cIdx=-1; clist.style.display='block'; }
function moveC(d){ if(!cData.length) return; let i=cIdx+d; i=(i+cData.length)%cData.length; [...clist.children].forEach((el,ix)=>el.classList.toggle('active', ix===i)); cIdx=i; }
async function pickC(i){ const c=cData[i]; if(!c) return; hideC(); csearch.value=''; const j = await (await fetch(`${BASE}/api/customers/lookup.php?dni=${encodeURIComponent(c.dni)}`)).json(); if(j.ok){ customer=j.data; customerPoints=j.points||0; paintCustomer(); }}
function hideC(){ clist.style.display='none'; cData=[]; cIdx=-1; }

// ==== Autocomplete productos (Funcionalidad Original Mantenida)
const scanEl = document.getElementById('scan'), acList = document.getElementById('acList');
let acIdx = -1, acData = [], acTimer = null;
scanEl.addEventListener('input', ()=>{ const q=scanEl.value.trim(); if(acTimer) clearTimeout(acTimer); if(q.length < 2){ hideAC(); return; } acTimer = setTimeout(()=> fetchAC(q), 220); });
scanEl.addEventListener('keydown', (e)=>{ if(acList.style.display!=='none'){ if(e.key==='ArrowDown'){ e.preventDefault(); moveAC(1); } else if(e.key==='ArrowUp'){ e.preventDefault(); moveAC(-1); } else if(e.key==='Enter'){ if(acIdx>=0){ e.preventDefault(); selectAC(acIdx); } else { addItem(); } } else if(e.key==='Escape'){ hideAC(); } } else if(e.key==='Enter'){ e.preventDefault(); addItem(); } });
async function fetchAC(q){ try{ const res = await fetch(`${BASE}/api/products/list.php?q=${encodeURIComponent(q)}&limit=8`); const j = await res.json(); if(j.ok){ acData = j.data || []; renderAC(); } else { hideAC(); } }catch(_){ hideAC(); } }
function renderAC(){ acList.innerHTML=''; if(!acData.length){ hideAC(); return; } acData.forEach((p,i)=>{ const div=document.createElement('div'); div.className='ac-item'; div.innerHTML = `<div class="ac-name">${p.name}</div><div class="ac-price">${mfmt(p.price)}</div>`; div.onclick = ()=> selectAC(i); acList.appendChild(div); }); acIdx=-1; acList.style.display='block'; }
function moveAC(d){ if(!acData.length) return; let i = acIdx + d; i=(i+acData.length)%acData.length; [...acList.children].forEach((el,ix)=> el.classList.toggle('active', ix===i)); acIdx=i; }
function selectAC(i){ const p = acData[i]; if(!p) return; cart.push({ product_id:p.id, name:p.name, price:Number(p.price), qty:1, discount:0, labels: p.labels || [] }); render(); hideAC(); scanEl.value=''; }
function hideAC(){ acList.style.display='none'; acData=[]; acIdx=-1; }
async function addItem(){ const q=scanEl.value.trim(); if(!q) return false; try{ const res = await fetch(BASE+'/api/products/find.php?q='+encodeURIComponent(q)); const j = await res.json(); if(j.ok && j.data){ cart.push({ product_id:j.data.id, name:j.data.name, price:Number(j.data.price), qty:1, discount:0, labels: j.data.labels || [] }); render(); }else{ Swal.fire('Error', `Producto no encontrado: ${j.error || ''}`, 'error'); } }catch(err){ Swal.fire('Error',`Buscando producto: ${err.message}`,'error'); } scanEl.value=''; hideAC(); return false; }

// ==== Pagos (Funcionalidad Original Mantenida)
function addPay(){
  const wrap=document.getElementById('pays'), i=payments.length, d=document.createElement('div'); d.style.marginBottom='6px';
  const sel=`<select class="input" onchange="payments[${i}].payment_method_id=Number(this.value)"><option value="">— Medio —</option>${methods.map(m=>`<option value="${m.id}">${m.name}</option>`).join('')}</select>`;
  d.innerHTML= `${sel} <input class="input" style="width:120px" placeholder="Monto" oninput="payments[${i}].amount=Number(this.value)||0"> <input class="input" style="width:160px" placeholder="Ref" oninput="payments[${i}].ref=this.value||''">`;
  wrap.appendChild(d); payments.push({payment_method_id:null,amount:0,ref:''});
}
function clearCart(){ cart=[]; payments=[]; customer=null; customerPoints=0; document.getElementById('pays').innerHTML=''; paintCustomer(); render(); }

// ==== Confirmar venta (FUNCIÓN **CORREGIDA Y MEJORADA**)
async function confirmSale(){
  if(cart.length===0) return Swal.fire('Error', 'El carrito está vacío.', 'error');
  if(!BRANCH_ID) return Swal.fire('Error', 'Debes seleccionar una sucursal para operar.', 'error');
  
  const pts = Number(document.getElementById('ptsUse').value)||0;
  if(pts > customerPoints) return Swal.fire('Error', 'El cliente no tiene suficientes puntos.', 'error');

  const payload = {
    branch_id: BRANCH_ID,
    register_id: REGISTER_ID,
    doc_mode: document.getElementById('docMode').value,
    cbte_letter: document.getElementById('cbteLetter').value,
    customer_id: customer ? customer.id : null,
    items: cart.map(i=>({product_id:i.product_id, qty:i.qty, unit_price:i.price, discount_pct:i.discount})),
    payments: payments.filter(p=> p.amount > 0),
    points_used: pts
  };

  Swal.fire({ title: 'Procesando Venta...', text: 'Por favor, espere.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

  try {
    const res = await fetch(BASE+'/api/sales/create.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();

    if(!res.ok || !data.ok) throw new Error(data.error || `Error HTTP ${res.status}`);

    const saleId = data.sale_id;

    if(data.needs_arca){
      Swal.update({ title: 'Venta registrada. Facturando en ARCA...' });
      
      const fiscalRes = await fetch(BASE+'/api/fiscal/emit.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ sale_id: saleId }) });
      const fiscalData = await fiscalRes.json();
      
      if(fiscalData.ok && fiscalData.data.cae) {
        Swal.fire({ icon: 'success', title: '¡Factura Emitida!', html: `Venta y factura generadas correctamente.<br><b>CAE: ${fiscalData.data.cae}</b>`, confirmButtonText: 'Ver Comprobante' })
           .then(() => { clearCart(); window.open(BASE+'/public/sales/view.php?id='+saleId, '_blank'); });
      } else {
        Swal.fire({ icon: 'warning', title: 'Venta Guardada, Factura Fallida', text: `La venta se guardó, pero ARCA devolvió un error: ${fiscalData.error || 'Desconocido'}`, confirmButtonText: 'Ver Venta (sin factura)' })
           .then(() => { clearCart(); window.location.href = BASE+'/public/sales/view.php?id='+saleId; });
      }
    } else {
      Swal.fire({ icon: 'success', title: 'Ticket Creado', text: `La venta #${saleId} se registró correctamente.`, confirmButtonText: 'Aceptar' })
         .then(() => clearCart());
    }
  } catch(e) {
    Swal.fire('Error Crítico', 'No se pudo completar la operación: '+e.message, 'error');
  }
}

// ===== Init
document.getElementById('docMode').addEventListener('change', render);
render();
</script>
</body></html>