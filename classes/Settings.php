<?php
require_once __DIR__ . '/Database.php';

class Settings {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance();
    }
    public function get($key, $default = '') {
        $row = $this->db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : $default;
    }
    public function set($key, $value) {
        return $this->db->execute(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $value, $value]
        );
    }
}
