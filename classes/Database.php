<?php
class Database {
    private static $instance = null;
    private $pdo;
    private static $migrated = false;

    private function __construct() {
        try {
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            if (!self::$migrated) {
                $this->runAutoMigrations();
                self::$migrated = true;
            }
        } catch (PDOException $e) {
            error_log("Adatbázis kapcsolat hiba: " . $e->getMessage());
            die("Adatbázis kapcsolat hiba! Ellenőrizd a config.local.php beállításait és a MySQL szervert.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query hiba: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Execute hiba: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollBack();
    }

    private function runAutoMigrations() {
        try {
            // Ellenőrizzük a users tábla oszlopait
            $cols = $this->pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('email', $cols)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER full_name");
            }
            if (!in_array('email_verified', $cols)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 1 AFTER active");
            }
            if (!in_array('reset_token_hash', $cols)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN reset_token_hash VARCHAR(255) NULL AFTER email_verified");
            }
            if (!in_array('reset_token_expires_at', $cols)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires_at DATETIME NULL AFTER reset_token_hash");
            }
            if (!in_array('verification_token_hash', $cols)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN verification_token_hash VARCHAR(255) NULL AFTER reset_token_expires_at");
            }
            // Ellenőrizzük a clothes tábla oszlopait (Mosási ciklusszámlálóhoz)
            $clothCols = $this->pdo->query("SHOW COLUMNS FROM clothes")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('wash_count', $clothCols)) {
                $this->pdo->exec("ALTER TABLE clothes ADD COLUMN wash_count INT DEFAULT 0 AFTER status");
            }
            if (!in_array('max_wash_count', $clothCols)) {
                $this->pdo->exec("ALTER TABLE clothes ADD COLUMN max_wash_count INT DEFAULT 50 AFTER wash_count");
            }
        } catch (Exception $e) {
            // Ha még nincs létrehozva a tábla, ne okozzon hibát
        }
    }
}
