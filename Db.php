<?php

class DB {
    private static $pdo;

    public static function connect() {
        if (!self::$pdo) {
            $config = require 'config.php';
            $dbConfig = $config['dbConfig'];
            try {
                self::$pdo = new PDO(
                    "mysql:host={$dbConfig['host']};dbname={$dbConfig['db']};charset=utf8",
                    $dbConfig['user'],
                    $dbConfig['pass']
                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Ma'lumotlar bazasiga ulanish xatosi:" . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
