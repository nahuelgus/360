<?php
require_once __DIR__.'/db.php';
session_name((require __DIR__.'/../config/.env.php')['app']['session_name']);
if (session_status()===PHP_SESSION_NONE) session_start();
function auth_login(string $dni,string $password): array{
  $u=DB::one("SELECT id,dni,name,password_hash,role,is_active FROM users WHERE dni=? LIMIT 1",[$dni]);
  if(!$u || intval($u['is_active'])!==1) throw new RuntimeException('Usuario no encontrado o inactivo');
  if(!password_verify($password,$u['password_hash'])) throw new RuntimeException('Contraseña incorrecta');
  $_SESSION['user']=['id'=>$u['id'],'dni'=>$u['dni'],'name'=>$u['name'],'role'=>$u['role']];
  return $_SESSION['user'];
}
function auth_logout():void{
  $_SESSION=[];
  if(ini_get('session.use_cookies')){
    $p=session_get_cookie_params();
    setcookie(session_name(),'',
      time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
  }
  session_destroy();
}
function current_user(){return $_SESSION['user']??null;}
function require_role(array $roles){
  $u=current_user();
  if(!$u||!in_array($u['role'],$roles,true)){ header('Location: /360/app/public/auth/login.php'); exit; }
}