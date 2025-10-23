<?php require_once __DIR__.'/../../lib/auth.php'; header('Content-Type: application/json; charset=utf-8');
$data=json_decode(file_get_contents('php://input'),true)??$_POST; $dni=trim($data['dni']??''); $pass=strval($data['password']??'');
try{$user=auth_login($dni,$pass); echo json_encode(['ok'=>true,'user'=>$user]);}
catch(Throwable $e){ http_response_code(401); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }