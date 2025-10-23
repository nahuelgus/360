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
      <!-- CLIENTE -->
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

      <!-- BUSCAR / ESCANEAR + CARRITO -->
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
              <th>Producto</th>
              <th>Cant</th>
              <th>P. Unit</th>
              <th>% Desc</th>
              <th>Total</th>
              <th>Etiq.</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- COMPROBANTE / PAGOS -->
    <div class="card">
      <h3>Comprobante</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select id="docMode" class="input" style="max-width:220px">
          <option value="TICKET_X" selected>Ticket X (no fiscal)</option>
          <option value="INVOICE">Factura</option>
        </select>
        <select id="cbteLetter" class="input" style="max-width:120px" <?= $def_letter ? '' : '' ?> >
          <option value="A">A</option>
          <option value="B" <?= $def_letter==='B'?'selected':'' ?>>B</option>
          <option value="C" <?= $def_letter==='C'?'selected':'' ?>>C</option>
        </select>
        <span class="help">Letra por defecto de la sucursal: <b><?= htmlspecialchars($def_letter) ?></b></span>
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
    const tags = [];
    if (Array.isArray(it.labels)) {
      it.labels.forEach(l => { if (l && l.icon) tags.push(`<img src="${l.icon}" alt="${l.name||''}" title="${l.name||''}">`); });
    }
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
      <td class="tag-icons">${tags.join(' ')}</td>
      <td><button class="btn" onclick="cart.splice(${idx},1); render()">✕</button></td>`;
    tb.appendChild(tr);
  });
  document.getElementById('grand').textContent = mfmt(total);
  document.getElementById('grandFixed').textContent = mfmt(total);

  // habilitar / deshabilitar letra según modo
  const inv = document.getElementById('docMode').value==='INVOICE';
  document.getElementById('cbteLetter').disabled = !inv;
}
function chgQty(i, d){ cart[i].qty = Math.max(1, Number(cart[i].qty||1) + d); render(); }

// ===== Clientes
let cTimer=null, cData=[], cIdx=-1;
const csearch = document.getElementById('csearch');
const clist   = document.getElementById('clist');

async function setCustomer(){
  const d = document.getElementById('dni').value.trim(); if(!d) return false;
  try{
    const r = await fetch(BASE+'/api/customers/lookup.php?dni='+encodeURIComponent(d));
    const j = await r.json();
    if(j.ok && j.data){ customer=j.data; customerPoints=j.points||0; paintCustomer(); }
    else{ alert('Cliente no encontrado'); }
  }catch(e){ alert('Error cliente: '+e.message); }
  return false;
}
function paintCustomer(){
  const box=document.getElementById('cliInfo');
  if(!customer){ box.innerHTML=''; document.getElementById('ptsAvail').textContent='0'; document.getElementById('ptsUse').value='0'; return; }
  box.innerHTML = `<span class="chip">
    <span>Cliente: <b>${customer.name}</b> (DNI ${customer.dni}) · Puntos: <b>${customerPoints}</b></span>
    <button class="x" title="Quitar" onclick="clearCustomer()">×</button></span>`;
  document.getElementById('ptsAvail').textContent = String(customerPoints);
}
function clearCustomer(){ customer=null; customerPoints=0; paintCustomer(); }
function openRegister(){ const d=document.getElementById('dni').value.trim(); window.open(BASE+'/public/clients/index.php'+(d?('?dni='+encodeURIComponent(d)):'') ,'_blank'); }

// Autocomplete clientes
csearch.addEventListener('input', ()=>{ const q=csearch.value.trim(); if(cTimer) clearTimeout(cTimer); if(q.length<2){ hideC(); return;} cTimer=setTimeout(()=> fetchCustomers(q), 220); });
csearch.addEventListener('keydown', e=>{ if(clist.style.display!=='none'){ if(e.key==='ArrowDown'){e.preventDefault();moveC(1);} else if(e.key==='ArrowUp'){e.preventDefault();moveC(-1);} else if(e.key==='Enter'){ if(cIdx>=0){e.preventDefault();pickC(cIdx);} } else if(e.key==='Escape'){hideC();}} });
document.addEventListener('click', (e)=>{ if(!e.target.closest('.ac-wrap')) hideC(); });
async function fetchCustomers(q){ try{ const j = await (await fetch(`${BASE}/api/customers/search.php?q=${encodeURIComponent(q)}&limit=12`)).json(); if(!j.ok){ hideC(); return; } cData=j.data||[]; renderC(); }catch(_){ hideC(); } }
function renderC(){ clist.innerHTML=''; if(!cData.length){ hideC(); return; } cData.forEach((c,i)=>{ const div=document.createElement('div'); div.className='ac-item'; div.innerHTML=`<div class="ac-name">${c.name} · DNI ${c.dni}</div><div class="ac-price">${c.email||''}</div>`; div.addEventListener('mouseenter',()=> setCIdx(i,false)); div.addEventListener('mouseleave',()=> setCIdx(-1,false)); div.addEventListener('click',()=> pickC(i)); clist.appendChild(div); }); cIdx=-1; clist.style.display='block'; }
function setCIdx(i){ cIdx=i; [...clist.children].forEach((el,ix)=>el.classList.toggle('active', ix===i)); }
function moveC(d){ if(!cData.length) return; let i=cIdx+d; if(i<0)i=cData.length-1; if(i>=cData.length)i=0; setCIdx(i); }
async function pickC(i){ const c=cData[i]; if(!c) return; const j = await (await fetch(`${BASE}/api/customers/lookup.php?dni=${encodeURIComponent(c.dni)}`)).json(); if(j.ok){ customer=j.data; customerPoints=j.points||0; paintCustomer(); } hideC(); csearch.value=''; }
function hideC(){ clist.style.display='none'; cData=[]; cIdx=-1; }

// ==== Autocomplete productos
const scanEl = document.getElementById('scan'); const acList = document.getElementById('acList');
let acIdx = -1; let acData = []; let acTimer = null;

scanEl.addEventListener('input', ()=>{ const q=scanEl.value.trim(); if(acTimer) clearTimeout(acTimer); if(q.length < 2){ hideAC(); return; } acTimer = setTimeout(()=> fetchAC(q), 220); });
scanEl.addEventListener('keydown', (e)=>{ if(acList.style.display!=='none'){ if(e.key==='ArrowDown'){ e.preventDefault(); moveAC(1); } else if(e.key==='ArrowUp'){ e.preventDefault(); moveAC(-1); } else if(e.key==='Enter'){ if(acIdx>=0){ e.preventDefault(); selectAC(acIdx); } else { addItem(); } } else if(e.key==='Escape'){ hideAC(); } } else if(e.key==='Enter'){ e.preventDefault(); addItem(); } });
document.addEventListener('click', (e)=>{ if(!e.target.closest('.ac-wrap')) hideAC(); });

async function fetchAC(q){
  try{
    const res = await fetch(`${BASE}/api/products/list.php?q=${encodeURIComponent(q)}&limit=12`);
    const j = await res.json();
    if(!j.ok){ hideAC(); return; }
    acData = j.data || [];
    renderAC();
  }catch(_){ hideAC(); }
}
function renderAC(){
  acList.innerHTML='';
  if(!acData.length){ hideAC(); return; }
  acData.forEach((p,i)=>{
    const tagIcons = Array.isArray(p.labels)
      ? p.labels.filter(l=>l && l.icon).map(l=>`<img src="${l.icon}" alt="${l.name||''}" title="${l.name||''}" style="height:16px;margin-left:6px">`).join('')
      : '';
    const div=document.createElement('div'); div.className='ac-item';
    const code = p.barcode ? `<span class="ac-code">${p.barcode}</span>` : '';
    div.innerHTML = `<div class="ac-name">${p.name}${code? ' · '+code : ''} ${tagIcons}</div>
                     <div class="ac-price">${mfmt(p.price)}</div>`;
    div.addEventListener('mouseenter', ()=> setACIdx(i,false));
    div.addEventListener('mouseleave', ()=> setACIdx(-1,false));
    div.addEventListener('click', ()=> selectAC(i));
    acList.appendChild(div);
  });
  acIdx=-1;
  acList.style.display='block';
}
function setACIdx(i,scroll=true){ acIdx=i; [...acList.children].forEach((el,ix)=> el.classList.toggle('active', ix===i)); if(scroll && i>=0){ acList.children[i].scrollIntoView({block:'nearest'}); } }
function moveAC(delta){ if(!acData.length) return; let i = acIdx + delta; if(i<0) i=acData.length-1; if(i>=acData.length) i=0; setACIdx(i); }
function selectAC(i){
  const p = acData[i]; if(!p) return;
  cart.push({ product_id:p.id, name:p.name, price:Number(p.price), qty:1, discount:0, labels: Array.isArray(p.labels) ? p.labels : [] });
  render(); hideAC(); scanEl.value='';
}
function hideAC(){ acList.style.display='none'; acData=[]; acIdx=-1; }

// ==== Agregar por Enter / scanner (robusto)
async function addItem(){
  const q=scanEl.value.trim(); if(!q) return false;
  try{
    const res = await fetch(BASE+'/api/products/find.php?q='+encodeURIComponent(q));
    const text = await res.text();
    let j; try { j = JSON.parse(text); } catch(e){ alert('Respuesta no-JSON al buscar producto:\\n'+text.slice(0,600)); return false; }
    if(j && j.ok && j.data){
      cart.push({
        product_id:j.data.id, name:j.data.name, price:Number(j.data.price), qty:1, discount:0,
        labels: Array.isArray(j.data.labels) ? j.data.labels : []
      });
      render();
    }else{
      alert('Producto no encontrado'+(j && j.error?': '+j.error:'')); 
    }
  }catch(err){ alert('Error buscando producto: '+err.message); }
  scanEl.value=''; hideAC(); return false;
}

// ==== Pagos
function addPay(){
  const wrap=document.getElementById('pays');
  const i=payments.length; const d=document.createElement('div'); d.style.marginBottom='6px';
  const sel=`<select class="input" onchange="payments[${i}].payment_method_id=Number(this.value)">
    <option value="">— Medio —</option>${methods.map(m=>`<option value="${m.id}">${m.name}</option>`).join('')}</select>`;
  d.innerHTML= sel+` <input class="input" style="width:120px" placeholder="Monto" inputmode="decimal"
                 oninput="payments[${i}].amount=Number(this.value)||0">
                 <input class="input" style="width:160px" placeholder="Ref (voucher: código)"
                 oninput="payments[${i}].ref=this.value||''">`;
  wrap.appendChild(d); payments.push({payment_method_id:null,amount:0,ref:''});
}
function clearCart(){ cart=[]; payments=[]; document.getElementById('pays').innerHTML=''; render(); }

// ==== Confirmar venta → /api/sales/create.php
async function confirmSale(){
  if(cart.length===0){ alert('Carrito vacío'); return; }
  if(!BRANCH_ID){ alert('Debés seleccionar una sucursal en el navbar.'); return; }

  const pts = Number(document.getElementById('ptsUse').value)||0;
  if(pts>0 && !customer){ alert('Para canjear puntos, seleccioná un cliente.'); return; }

  // Modo/Letra
  const docMode = (document.getElementById('docMode').value || 'TICKET_X').toUpperCase();
  const cbteLetter = document.getElementById('cbteLetter').value || '<?= htmlspecialchars($def_letter) ?>';

  // Mapear items a lo que espera la API
  const items = cart.map(i=>({
    product_id: i.product_id,
    qty: Number(i.qty)||1,
    unit_price: Number(i.price)||0,
    discount_pct: Number(i.discount)||0
  }));

  // Armar payload
  const payload = {
    branch_id: BRANCH_ID,
    register_id: REGISTER_ID || null,
    doc_mode: docMode,             // 'TICKET_X' | 'INVOICE'
    cbte_letter: cbteLetter,       // A | B | C (solo si INVOICE)
    customer_id: customer ? (customer.id||null) : null,
    items,
    discount_total: 0,             // descuento total adicional $ (editable si querés)
    payments
  };

  try{
    const res = await fetch(BASE+'/api/sales/create.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const text = await res.text();
    let j; try { j = JSON.parse(text); } catch(e){ alert('Respuesta no-JSON:\\n'+text.slice(0,600)); return; }
    if(!j.ok){ alert(j.error||'Error creando venta'); return; }

    if(j.needs_arca){
      // Paso 2: cuando activemos ARCA, descomentar:
      // const r2 = await fetch(BASE+'/api/fiscal/emit.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ sale_id:j.sale_id })});
      // const t2 = await r2.text(); let e2; try{ e2=JSON.parse(t2) }catch(_){ alert('ARCA no respondió JSON:\\n'+t2.slice(0,600)); return; }
      // if(!e2.ok){ alert('ARCA error: '+(e2.error||'desconocido')); return; }
      alert('Venta creada. Falta emisión ARCA (Paso 2). #'+j.sale_id);
    } else {
      alert('Venta Ticket X creada. #'+j.sale_id);
    }

    // Redirigir a vista/impresión si ya la tenés:
    // window.location = BASE+'/public/sales/view.php?id='+j.sale_id;

    clearCart();
  }catch(e){
    alert('Error confirmando venta: '+e.message);
  }
}

// ===== Init
document.getElementById('docMode').addEventListener('change', render);
render();
</script>
</body></html>
