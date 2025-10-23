<?php
require_once __DIR__.'/../../lib/auth.php'; require_role(['admin','supervisor']);
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if(!$id){ echo json_encode(['ok'=>false,'error'=>'ID inválido']); exit; }

try{
  // si hay FKs (sale_items), borrar/soft-delete según tu modelo; aquí hard delete
  DB::run("DELETE FROM products WHERE id=?",[$id]);
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
