<?php
require_once __DIR__.'/../../api/auth/require_login.php';

$shift_id = intval($_POST['shift_id'] ?? 0);
$keep     = max(0, floatval($_POST['keep_in_drawer_amount'] ?? 0.0));

$s = DB::one("SELECT opening_amount, register_id, user_id_open FROM cash_shifts WHERE id=?",[$shift_id]);
if(!$s){ die('Turno no encontrado'); }

$mov = DB::one("SELECT COALESCE(SUM(CASE WHEN kind='income' THEN amount WHEN kind='expense' THEN -amount ELSE 0 END),0) AS net
                  FROM cash_movements WHERE shift_id=?",[$shift_id]);

$closing   = floatval($s['opening_amount']) + floatval($mov['net']);
$delivered = max(0, $closing - $keep);

DB::run("UPDATE cash_shifts
            SET closed_at=NOW(), closing_amount=?, keep_in_drawer_amount=?, delivered_amount=?, status='closed'
          WHERE id=?", [$closing,$keep,$delivered,$shift_id]);

header('Location: /360/app/public/cash/receipt.php?id='.$shift_id);