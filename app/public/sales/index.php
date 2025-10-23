<?php
// /360/app/public/sales/index.php
require_once __DIR__ . '/../../api/auth/require_login.php';
require_once __DIR__ . '/../../lib/db.php'; // <-- ESTA LÍNEA FALTABA

$cfg  = require __DIR__.'/../../config/.env.php';
$BASE = $cfg['app']['base_url'] ?? '/360/app';

// Para los filtros
$branches = DB::all("SELECT id, name FROM branches ORDER BY name");
$users = DB::all("SELECT u.id, u.name as full_name FROM users u WHERE u.role IN ('admin','supervisor','vendedor') ORDER BY u.name"); // Ajustado a 'name'
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ventas</title>
<link rel="stylesheet" href="<?= $BASE ?>/public/assets/css/base.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  .filters { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
  .total-row { font-weight: bold; background-color: #f8f9fa; }
  .status-badge { padding: 3px 8px; border-radius: 99px; color: #fff; font-size: 0.8rem; text-transform: capitalize; }
  .status-completed, .status-sent { background-color: #28a745; }
  .status-pending { background-color: #ffc107; color: #000; }
  .status-voided { background-color: #dc3545; }
  .status-not_applicable { background-color: #6c757d; }
  .status-error { background-color: #bd2130; }
</style>
</head><body>
<?php require __DIR__.'/../partials/navbar.php'; ?>

<div class="container">
  <div class="card">
    <h3>Listado de Ventas</h3>
    
    <div class="filters">
      <input type="date" id="f_date_from" class="input" title="Fecha Desde">
      <input type="date" id="f_date_to" class="input" title="Fecha Hasta">
      <select id="f_branch" class="input">
        <option value="">— Todas las sucursales —</option>
        <?php foreach($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="f_user" class="input">
        <option value="">— Todos los vendedores —</option>
        <?php foreach($users as $u): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" onclick="loadSales()">Filtrar</button>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" id="tSales">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Cliente</th>
              <th>Vendedor</th>
              <th>Sucursal</th>
              <th>Total</th>
              <th>Estado ARCA</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>
          </tbody>
          <tfoot></tfoot>
        </table>
    </div>
  </div>
</div>

<script>
const BASE = '<?= $BASE ?>';
const moneyUS = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const mfmt = n => '$' + moneyUS.format(Number(n) || 0);

async function loadSales() {
  const tbody = document.querySelector('#tSales tbody');
  const tfoot = document.querySelector('#tSales tfoot');
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Cargando...</td></tr>';
  tfoot.innerHTML = '';

  const params = new URLSearchParams({
    date_from: document.getElementById('f_date_from').value,
    date_to: document.getElementById('f_date_to').value,
    branch_id: document.getElementById('f_branch').value,
    user_id: document.getElementById('f_user').value,
  });

  try {
    const res = await fetch(`${BASE}/api/sales/list.php?${params.toString()}`);
    const j = await res.json();

    if (!j.ok) throw new Error(j.error || 'Error desconocido');
    
    tbody.innerHTML = '';
    let totalAmount = 0;
    (j.data || []).forEach(s => {
      totalAmount += parseFloat(s.total);
      const statusClass = `status-${(s.arca_status||'').toLowerCase().replace(' ','_')}`;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${s.id}</td>
        <td>${new Date(s.created_at).toLocaleString('es-AR')}</td>
        <td>${s.customer_name || 'Consumidor Final'}</td>
        <td>${s.user_name || 'N/A'}</td>
        <td>${s.branch_name || 'N/A'}</td>
        <td style="text-align:right;">${mfmt(s.total)}</td>
        <td><span class="status-badge ${statusClass}">${(s.arca_status || 'N/A').replace('_', ' ')}</span></td>
        <td style="white-space:nowrap;">
          <a href="${BASE}/public/sales/view.php?id=${s.id}" class="btn" target="_blank">Ver</a>
          <button class="btn" onclick="cancelSale(${s.id}, '${s.arca_status}')">Anular</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    if (j.data.length > 0) {
        tfoot.innerHTML = `<tr class="total-row"><td colspan="5" style="text-align:right;">Total de ventas listadas:</td><td style="text-align:right;">${mfmt(totalAmount)}</td><td colspan="2"></td></tr>`;
    } else {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No se encontraron ventas con esos filtros.</td></tr>';
    }

  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:red;">Error: ${e.message}</td></tr>`;
  }
}

async function cancelSale(id, status) {
  if (status === 'sent') {
    return Swal.fire('Acción no permitida', 'No se puede anular una venta con factura fiscal (CAE) emitida. Debes generar una Nota de Crédito.', 'warning');
  }
  if (status === 'voided') {
    return Swal.fire('Información', `La venta #${id} ya se encuentra anulada.`, 'info');
  }
  
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: `Se anulará la venta #${id}. El stock de los productos será devuelto y se generará un movimiento de caja inverso. ¡Esta acción no se puede revertir!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, ¡anular ahora!',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    try {
      const res = await fetch(`${BASE}/api/sales/cancel.php`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ sale_id: id })
      });
      const j = await res.json();
      if (!j.ok) throw new Error(j.error || 'Error desconocido');
      
      await Swal.fire('¡Anulada!', 'La venta ha sido anulada correctamente.', 'success');
      loadSales(); // Recargar la lista
    } catch(e) {
      Swal.fire('Error', 'No se pudo anular la venta: ' + e.message, 'error');
    }
  }
}

// Carga inicial
document.addEventListener('DOMContentLoaded', loadSales);
</script>
</body></html>