<?php
namespace PHPMailer\PHPMailer;
class PHPMailer {
    public $Host; public $SMTPAuth; public $Username; public $Password;
    public $SMTPSecure; public $Port; public $From; public $FromName;
    public $Subject; public $Body; public $isHTML = false;
    private $to = [];
    public function isSMTP() {}
    public function setFrom($email, $name=''){ $this->From=$email; $this->FromName=$name; }
    public function addAddress($addr){ $this->to[]=$addr; }
    public function isHTML($bool=true){ $this->isHTML=$bool; }
    public function send(){ return true; }
}
