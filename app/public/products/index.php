<?php
// /360/app/public/products/index.php
require_once __DIR__.'/../../api/auth/require_login.php';

$ENV  = require __DIR__.'/../../config/.env.php';
$BASE = $ENV['app']['base_url'] ?? '';

/* Helpers defensivos */
function safeAll($sql,$p=[]){ try{ return DB::all($sql,$p);}catch(Throwable $e){ return []; } }
function hasTable($t){
  try{ return !!DB::one("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",[$t]); }
  catch(Throwable $e){ return false; }
}
function hasCol($t,$c){
  try{ return !!DB::one("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?",[$t,$c]); }
  catch(Throwable $e){ return false; }
}

/* Categorías */
$cats = safeAll("SELECT id,name FROM product_categories WHERE is_active=1 ORDER BY name");

/* Etiquetas: preferimos product_labels (icon_path), si no, labels (icon_url/icon) */
$labels = [];
$labelTable = null; $iconCol = null;
if (hasTable('product_labels')) {
  $labelTable = 'product_labels';
  $iconCol = hasCol('product_labels','icon_path') ? 'icon_path' : (hasCol('product_labels','icon') ? 'icon' : null);
  $sel = "id,name".($iconCol? ",$iconCol AS icon" : "");
  $labels = safeAll("SELECT $sel FROM product_labels ORDER BY name");
} elseif (hasTable('labels')) {
  $labelTable = 'labels';
  if     (hasCol('labels','icon_url')) $iconCol='icon_url';
  elseif (hasCol('labels','icon'))     $iconCol='icon';
  elseif (hasCol('labels','image'))    $iconCol='image';
  $sel = "id,name".($iconCol? ",$iconCol AS icon" : "");
  $labels = safeAll("SELECT $sel FROM labels ORDER BY name");
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Productos</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<style>
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  @media (max-width:1000px){ .grid{grid-template-columns:1fr} }
  .hint{font-size:.85rem;color:#667;margin-bottom:4px}
  .labels-grid{display:flex;flex-wrap:wrap;gap:14px;margin-top:8px}
  .label-item{display:flex;align-items:center;gap:8px}
  .label-item img{height:22px;width:22px;object-fit:contain;border-radius:4px}
  .money{text-align:right}
</style>
</head>
<body>
<?php require __DIR__.'/../partials/navbar.php'; ?>

<div class="container">
  <div class="card">
    <h3>Producto</h3>
    <form id="fProd" onsubmit="return saveProd()">
      <div class="grid">
        <div>
          <div class="hint">SKU interno</div>
          <input class="input" name="sku" id="sku" placeholder="SKU interno">
        </div>

        <div>
          <div class="hint">Código de barras (alfanumérico)</div>
          <input class="input" name="barcode" id="barcode" placeholder="Código de barras (alfanumérico)">
        </div>

        <div>
          <div class="hint">Nombre *</div>
          <input class="input" name="name" id="name" placeholder="Nombre *" required>
        </div>

        <div>
          <div class="hint">Unidad</div>
          <select class="input" name="unit" id="unit">
            <option value="">Unidad</option>
            <option value="Unidad">Unidad</option>
            <option value="Gramos">Gramos</option>
            <option value="Kilos">Kilos</option>
            <option value="Litros">Litros</option>
            <option value="ML">ML</option>
            <option value="Caja">Caja</option>
          </select>
        </div>

        <div>
          <div class="hint">Unidades por caja (si aplica)</div>
          <input class="input" name="box_units" id="box_units" placeholder="Unidades por caja (si aplica)" inputmode="numeric">
        </div>

        <div>
          <div class="hint">Precio venta</div>
          <input class="input money" name="price" id="price" placeholder="Precio venta" inputmode="decimal">
        </div>

        <div>
          <div class="hint">Categoría</div>
          <select class="input" name="category_id" id="category_id" onchange="loadSubs()">
            <option value="">— Categoría —</option>
            <?php foreach($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <div class="hint">Subcategoría</div>
          <select class="input" name="subcategory_id" id="subcategory_id">
            <option value="">— Subcategoría —</option>
          </select>
        </div>

        <div>
          <div class="hint">Costo</div>
          <input class="input money" name="cost" id="cost" placeholder="Costo" inputmode="decimal">
        </div>
      </div>

      <label style="display:flex;gap:8px;margin-top:8px;align-items:center">
        <input type="checkbox" name="is_active" id="is_active" checked> Activo
      </label>

      <div style="margin-top:10px">
        <div class="hint" style="margin-bottom:6px">Etiquetas</div>
        <div class="labels-grid">
          <?php if ($labels): ?>
            <?php foreach ($labels as $lab): ?>
              <label class="label-item" title="<?= htmlspecialchars($lab['name']) ?>">
                <input type="checkbox" class="label-check" value="<?= (int)$lab['id'] ?>">
                <?php if (!empty($lab['icon'])): ?>
                  <img src="<?= htmlspecialchars($lab['icon']) ?>" alt="<?= htmlspecialchars($lab['name']) ?>">
                <?php endif; ?>
                <span><?= htmlspecialchars($lab['name']) ?></span>
              </label>
            <?php endforeach; ?>
          <?php else: ?>
            <small class="hint">No hay etiquetas cargadas (menú <b>Etiquetas</b>).</small>
          <?php endif; ?>
        </div>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px">
        <button class="btn">Guardar</button>
        <button class="btn" type="button" onclick="clearForm()">Limpiar</button>
      </div>
      <input type="hidden" name="id" id="id">
    </form>
  </div>

  <div class="card">
    <h3>Listado</h3>
    <input class="input" id="q" placeholder="Buscar por nombre / código / ID" oninput="loadList()">
    <table class="table" id="tList" style="margin-top:10px">
      <thead><tr>
        <th>ID</th><th>Nombre</th><th>Cod</th><th>Precio</th><th>Unidad</th><th>Categoría</th><th>Subcat</th><th></th>
      </tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
const BASE='<?= $BASE ?>';
const moneyUS = new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
const mfmt = n => '$'+moneyUS.format(Number(n)||0);

async function loadSubs(){
  const cid = document.getElementById('category_id').value || '';
  const sel = document.getElementById('subcategory_id');
  sel.innerHTML = '<option value="">— Subcategoría —</option>';
  if(!cid) return;
  const j = await (await fetch(BASE+'/api/categories/subs.php?category_id='+encodeURIComponent(cid))).json();
  if(j.ok){ (j.data||[]).forEach(s=>{ const o=document.createElement('option'); o.value=s.id; o.textContent=s.name; sel.appendChild(o); }); }
}

async function saveProd(){
  const fd = new FormData(document.getElementById('fProd'));
  document.querySelectorAll('.label-check:checked').forEach(cb => fd.append('labels[]', cb.value));
  const res = await fetch(BASE+'/api/products/save.php',{method:'POST',body:fd});
  const txt = await res.text(); let j;
  try{ j=JSON.parse(txt); }catch(e){ alert('Respuesta no-JSON:\\n'+txt.slice(0,500)); return false; }
  if(!j.ok){ alert(j.error||'Error'); return false; }
  clearForm(); loadList(); return false;
}

function clearForm(){
  document.getElementById('fProd').reset();
  document.getElementById('id').value='';
  document.querySelectorAll('.label-check').forEach(cb=>cb.checked=false);
}

async function loadList(){
  const q = document.getElementById('q').value.trim();
  const params = new URLSearchParams(); if(q) params.set('q',q); params.set('limit','50');
  const res = await fetch(BASE+'/api/products/list.php?'+params.toString());
  if(!res.ok){ alert('Error HTTP '+res.status); return; }
  const j = await res.json(); if(!j.ok){ alert(j.error||'Error'); return; }
  const tb=document.querySelector('#tList tbody'); tb.innerHTML='';
  (j.data||[]).forEach(r=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td><td>${r.name||''}</td><td>${r.barcode||r.sku||''}</td>
      <td>${mfmt(r.price||0)}</td><td>${r.unit||''}</td>
      <td>${r.category_name||''}</td><td>${r.subcategory_name||''}</td>
      <td><button class="btn" onclick="editProd(${r.id})">Editar</button>
          <button class="btn" onclick="delProd(${r.id})">Eliminar</button></td>`;
    tb.appendChild(tr);
  });
}

async function editProd(id){
  const res = await fetch(BASE+'/api/products/get.php?id='+encodeURIComponent(id));
  const txt = await res.text(); let j;
  try{ j=JSON.parse(txt); }catch(e){ alert('Respuesta no-JSON:\\n'+txt.slice(0,500)); return; }
  if(!j.ok){ alert(j.error||'Error'); return; }
  const r=j.data;
  id&&(document.getElementById('id').value=r.id||'');
  document.getElementById('sku').value = r.sku||'';
  document.getElementById('barcode').value = r.barcode||'';
  document.getElementById('name').value = r.name||'';
  document.getElementById('unit').value = r.unit||'';
  document.getElementById('box_units').value = r.box_units||'';
  document.getElementById('price').value = (r.price??'')===''?'':String(r.price);
  document.getElementById('cost').value  = (r.cost??'')===''?'':String(r.cost);
  document.getElementById('is_active').checked = !!(r.is_active??1);
  document.getElementById('category_id').value = r.category_id||'';
  await loadSubs();
  document.getElementById('subcategory_id').value = r.subcategory_id||'';

  const set = new Set((r.labels||[]).map(l=>String(l.id)));
  document.querySelectorAll('.label-check').forEach(cb=> cb.checked = set.has(String(cb.value)));
  document.getElementById('name').scrollIntoView({behavior:'smooth',block:'center'});
}

async function delProd(id){
  if(!confirm('¿Eliminar producto #'+id+'?')) return;
  const fd=new FormData(); fd.append('id',id);
  const j = await (await fetch(BASE+'/api/products/delete.php',{method:'POST',body:fd})).json();
  if(!j.ok){ alert(j.error||'Error'); return; }
  loadList();
}

loadList();
</script>
</body></html>