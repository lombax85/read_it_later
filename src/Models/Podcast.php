<?php

namespace App\Models;

use App\Database;
use PDO;

class Podcast {
    private $id;
    private $filename;
    private $title;
    private $createdAt;

    public function __construct($filename, $title) {
        $this->filename = $filename;
        $this->title = $title;
        $this->createdAt = date('Y-m-d H:i:s');
    }

    public function save() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO podcasts (filename, title, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$this->filename, $this->title]);
        $this->id = $db->lastInsertId();
    }

    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM podcasts ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getByFilename($filename) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM podcasts WHERE filename = ?");
        $stmt->execute([$filename]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function delete($filename) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM podcasts WHERE filename = ?");
        $stmt->execute([$filename]);
    }
}
