<?php
// /app/api/fiscal/test_wsaa.php
declare(strict_types=1);
require_once __DIR__.'/../auth/require_login.php';
require_once __DIR__.'/../../lib/arca_wsaa.php';
header('Content-Type: application/json; charset=utf-8');

function out($x,$c=200){ http_response_code($c); echo json_encode($x,JSON_UNESCAPED_UNICODE); exit; }

try{
  $society_id = (int)($_GET['society_id'] ?? $_POST['society_id'] ?? 1);
  $env        = (string)($_GET['env'] ?? $_POST['env'] ?? 'sandbox'); // sandbox|production
  $service    = (string)($_GET['service'] ?? $_POST['service'] ?? 'wsfe');

  $wsaa = new WSAAAuth($society_id, $env, $service);
  $ta   = $wsaa->getTA();

  out(['ok'=>true,'ta'=>$ta]);
}catch(Throwable $e){
  out(['ok'=>false,'error'=>$e->getMessage()],500);
}
