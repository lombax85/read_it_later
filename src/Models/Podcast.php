<?php

namespace App\Models;

use App\Database;
use PDO;

class Podcast {
    private $id;
    private $filename;
    private $title;
    private $createdAt;
    private $linkIds;

    public function __construct($filename = null, $title = null, $linkIds = []) {
        $this->filename = $filename;
        $this->title = $title;
        $this->createdAt = date('Y-m-d H:i:s');
        $this->linkIds = $linkIds;
    }

    public function save() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO podcasts (filename, title, created_at, link_ids) VALUES (?, ?, ?, ?)");
        $linkIdsString = implode(',', $this->linkIds);
        $stmt->execute([$this->filename, $this->title, $this->createdAt, $linkIdsString]);
        $this->id = $db->lastInsertId();
    }

    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM podcasts ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function delete($filename) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM podcasts WHERE filename = ?");
        $stmt->execute([$filename]);
    }

    // Getter per linkIds
    public function getLinkIds() {
        return $this->linkIds;
    }

    public function getScript() {
        // Assumiamo che lo script sia memorizzato in una colonna 'script' nel database
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT script FROM podcasts WHERE id = ?");
        $stmt->execute([$this->id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['script'] ?? '';
    }

    public static function getByFilename($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM podcasts WHERE filename = ?");
        $stmt->execute([$id]);
        $podcast = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($podcast) {
            $podcastObject = new self($podcast['filename'], $podcast['title'], explode(',', $podcast['link_ids']));
            $podcastObject->id = $podcast['id'];
            $podcastObject->createdAt = $podcast['created_at'];
            return $podcastObject;
        }
        
        return null;
    }
}
