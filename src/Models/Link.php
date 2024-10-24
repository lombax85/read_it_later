<?php

namespace App\Models;

use App\Database;
use PDO;
use JsonSerializable;

class Link implements JsonSerializable {
    private $id;
    private $url;
    private $title;
    private $category;
    private $summary;
    private $createdAt;
    private $content;
    private $isRead;
    private $ranking;

    public function __construct($url = null, $title = null, $category = null, $content = null, $isRead = false, $ranking = 0) {
        $this->url = $url;
        $this->title = $title;
        $this->category = $category;
        $this->content = $content;
        $this->createdAt = date('Y-m-d H:i:s');
        $this->isRead = $isRead;
        $this->ranking = $ranking;
    }

    // Getter e setter
    public function getId() { return $this->id; }
    public function getUrl() { return $this->url; }
    public function getTitle() { return $this->title; }
    public function getCategory() { return $this->category; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getSummary() { 
        return $this->summary; 
    }
    public function setSummary($summary) { $this->summary = $summary; }

    public function setTitle($title) { $this->title = $title; }
    public function setCategory($category) { $this->category = $category; }

    public function getContent() { return $this->content; }
    public function setContent($content) { $this->content = $content; }

    public function isRead() { return $this->isRead; }
    public function setRead($isRead) { $this->isRead = $isRead; }

    public function getRanking() { return $this->ranking; }
    public function setRanking($ranking) { $this->ranking = $ranking; }

    public function save() {
        $db = Database::getInstance()->getConnection();
        if ($this->id) {
            $stmt = $db->prepare("UPDATE links SET url = ?, title = ?, category = ?, summary = ?, content = ?, is_read = ?, ranking = ? WHERE id = ?");
            $stmt->execute([$this->url, $this->title, $this->category, $this->summary, $this->content, $this->isRead ? 1 : 0, $this->ranking, $this->id]);
        } else {
            $stmt = $db->prepare("INSERT INTO links (url, title, category, summary, content, created_at, is_read, ranking) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$this->url, $this->title, $this->category, $this->summary, $this->content, $this->createdAt, $this->isRead ? 1 : 0, $this->ranking]);
            $this->id = $db->lastInsertId();
        }
    }

    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM links ORDER BY ranking DESC, created_at DESC");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $linkObjects = [];
        foreach ($links as $link) {
            $linkObject = new self($link['url'], $link['title'], $link['category'], $link['content']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
            $linkObject->content = $link['content'];
            $linkObject->isRead = $link['is_read'];
            $linkObject->ranking = $link['ranking'];
            $linkObjects[] = $linkObject;
        }
                
        return $linkObjects;
    }

    public static function getByCategory($category) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM links WHERE category = ? ORDER BY created_at DESC");
        $stmt->execute([$category]);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $linkObjects = [];
        foreach ($links as $link) {
            $linkObject = new self($link['url'], $link['title'], $link['category'], $link['content'], $link['is_read']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
            $linkObject->content = $link['content'];
            $linkObject->isRead = $link['is_read'];
            $linkObjects[] = $linkObject;
        }
        
        return $linkObjects;
    }

    public function updateSummary($summary) {
        $this->summary = $summary;
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE links SET summary = ? WHERE id = ?");
        $stmt->execute([$this->summary, $this->id]);
    }

    public static function getById($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM links WHERE id = ?");
        $stmt->execute([$id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            $linkObject = new self($link['url'], $link['title'], $link['category'], $link['content']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
            $linkObject->content = $link['content'];
            $linkObject->isRead = $link['is_read'];
            $linkObject->ranking = $link['ranking'];
            return $linkObject;
        }
        
        return null;
    }

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'category' => $this->category,
            'summary' => $this->summary,
            'content' => $this->content,
            'createdAt' => $this->createdAt,
            'isRead' => $this->isRead,
            'ranking' => $this->ranking
        ];
    }

    public function delete() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM links WHERE id = ?");
        $stmt->execute([$this->id]);
    }
}
