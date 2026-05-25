<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'levelup');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('<div style="font-family:Arial;padding:20px;color:red;background:#fff">
                <h2>Erreur de connexion BDD</h2>
                <p>Verifiez config.php (DB_HOST, DB_NAME, DB_USER, DB_PASS)</p>
                <p><small>'.$e->getMessage().'</small></p>
            </div>');
        }
    }
    return $pdo;
}
