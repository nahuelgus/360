<?php
require_once __DIR__.'/../../api/auth/require_login.php';

$rid   = intval($_POST['register_id'] ?? 0);
$amount= floatval($_POST['opening_amount'] ?? 0.0);
$u     = $_SESSION['user'];

// Validaciones
if ($rid <= 0) { die('Caja inválida'); }
if ($amount < 0) { die('El monto inicial no puede ser negativo'); }

// ¿Usuario ya tiene un turno abierto en cualquier sucursal?
$open = DB::one("SELECT cs.id, cs.register_id, b.name AS branch, r.name AS regname
                   FROM cash_shifts cs
                   JOIN cash_registers r ON r.id = cs.register_id
                   JOIN branches b ON b.id = r.branch_id
                  WHERE cs.user_id_open = ? AND cs.status='open'
                  ORDER BY cs.id DESC LIMIT 1", [$u['id']]);
if ($open) {
  // Mensaje claro
  $msg = 'Ya tenés un turno abierto en la sucursal "' . $open['branch'] .
         '" (caja "' . $open['regname'] . '"). Cerralo para abrir otro.';
  // Mostramos mensaje simple y opción de volver
  echo "<meta charset='utf-8'><p style='font-family:system-ui'>".$msg."</p>
        <p><a href='/360/app/public/cash/index.php'>Volver</a></p>";
  exit;
}

// Abrir turno
DB::run("INSERT INTO cash_shifts (register_id,user_id_open,opened_at,opening_amount,status)
         VALUES (?,?,NOW(),?,'open')", [$rid,$u['id'],$amount]);

$_SESSION['register_id']=$rid;
header('Location: /360/app/public/cash/index.php');