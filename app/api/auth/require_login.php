<?php
require_once __DIR__.'/../../lib/auth.php';
$u=current_user(); if(!$u){ header('Location: /360/app/public/auth/login.php'); exit; }
$_SESSION['branch_id'] = $_SESSION['branch_id'] ?? null;
$_SESSION['register_id'] = $_SESSION['register_id'] ?? null;