<?php
namespace App;
use PDO;
final class DB {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (!self::$pdo) self::$pdo = new PDO('mysql:host='.env('DB_HOST').';port='.env('DB_PORT',3306).';dbname='.env('DB_NAME').';charset=utf8mb4',(string)env('DB_USER'),(string)env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        return self::$pdo;
    }
    public static function one(string $sql,array $params=[]):?array{$s=self::pdo()->prepare($sql);$s->execute($params);return $s->fetch()?:null;}
    public static function all(string $sql,array $params=[]):array{$s=self::pdo()->prepare($sql);$s->execute($params);return $s->fetchAll();}
    public static function exec(string $sql,array $params=[]):int{$s=self::pdo()->prepare($sql);$s->execute($params);return $s->rowCount();}
}

