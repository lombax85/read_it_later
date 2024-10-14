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

    public function __construct($url = null, $title = null, $category = null) {
        $this->url = $url;
        $this->title = $title;
        $this->category = $category;
        $this->createdAt = date('Y-m-d H:i:s');
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

    public function save() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO links (url, title, category, summary, content, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->url, $this->title, $this->category, $this->summary, $this->content, $this->createdAt]);
        $this->id = $db->lastInsertId();
    }

    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM links ORDER BY created_at DESC");
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Links from database: ' . print_r($links, true));
        
        $linkObjects = [];
        foreach ($links as $link) {
            $linkObject = new self($link['url'], $link['title'], $link['category']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
            $linkObjects[] = $linkObject;
        }
        
        error_log('Link objects: ' . print_r($linkObjects, true));
        
        return $linkObjects;
    }

    public static function getByCategory($category) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM links WHERE category = ? ORDER BY created_at DESC");
        $stmt->execute([$category]);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $linkObjects = [];
        foreach ($links as $link) {
            $linkObject = new self($link['url'], $link['title'], $link['category']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
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
            $linkObject = new self($link['url'], $link['title'], $link['category']);
            $linkObject->id = $link['id'];
            $linkObject->summary = $link['summary'];
            $linkObject->createdAt = $link['created_at'];
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
            'createdAt' => $this->createdAt
        ];
    }

    public function delete() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM links WHERE id = ?");
        $stmt->execute([$this->id]);
    }

    public function getFullContent() {
        $contentExtractor = new \App\Services\ContentExtractor();
        $content = $contentExtractor->extract($this->url);
        return $content ? $content['content'] : null;
    }
}
