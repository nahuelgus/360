<?php
class DB {
  private static $pdo;
  public static function pdo(){
    if(!self::$pdo){
      $cfg = require __DIR__.'/../config/.env.php';
      $dsn = 'mysql:host='.$cfg['db']['host'].';dbname='.$cfg['db']['name'].';charset='.$cfg['db']['charset'];
      self::$pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      ]);
      self::$pdo->exec("SET NAMES ".$cfg['db']['charset']);
    }
    return self::$pdo;
  }
  public static function run($sql,$p=[]){$st=self::pdo()->prepare($sql);$st->execute($p);return $st;}
  public static function one($sql,$p=[]){return self::run($sql,$p)->fetch();}
  public static function all($sql,$p=[]){return self::run($sql,$p)->fetchAll();}
}