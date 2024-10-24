<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;
    private $dbPath;

    private function __construct()
    {
        $this->dbPath = __DIR__ . '/../database/read_it_later.sqlite';
        $this->ensureDatabaseExists();

        try {
            $this->connection = new PDO("sqlite:{$this->dbPath}");
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->runMigrations();
        } catch (PDOException $e) {
            die("Connessione al database fallita: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function ensureDatabaseExists()
    {
        if (!file_exists(dirname($this->dbPath))) {
            mkdir(dirname($this->dbPath), 0777, true);
        }
        if (!file_exists($this->dbPath)) {
            touch($this->dbPath);
        }
    }

    private function runMigrations()
    {
        // Migrazione per la tabella links
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                title TEXT NOT NULL,
                category TEXT NOT NULL,
                summary TEXT,
                content TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_read INTEGER DEFAULT 0
            )
        ");

        // Migrazione per la tabella podcasts
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS podcasts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                title TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                link_ids TEXT
            )
        ");

        // Verifica se la colonna link_ids esiste già nella tabella podcasts
        $result = $this->connection->query("PRAGMA table_info(podcasts)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $linkIdsExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'link_ids') {
                $linkIdsExists = true;
                break;
            }
        }

        // Se la colonna link_ids non esiste, aggiungila
        if (!$linkIdsExists) {
            $this->connection->exec("ALTER TABLE podcasts ADD COLUMN link_ids TEXT");
        }

        // Aggiungiamo la colonna content se non esiste già
        $result = $this->connection->query("PRAGMA table_info(links)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $contentExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'content') {
                $contentExists = true;
                break;
            }
        }
        if (!$contentExists) {
            $this->connection->exec("ALTER TABLE links ADD COLUMN content TEXT");
        }

        // Aggiungiamo la colonna is_read se non esiste già
        $isReadExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'is_read') {
                $isReadExists = true;
                break;
            }
        }
        if (!$isReadExists) {
            $this->connection->exec("ALTER TABLE links ADD COLUMN is_read INTEGER DEFAULT 0");
        }

        // Aggiungi la colonna ranking se non esiste già
        $result = $this->connection->query("PRAGMA table_info(links)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $rankingExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'ranking') {
                $rankingExists = true;
                break;
            }
        }
        if (!$rankingExists) {
            $this->connection->exec("ALTER TABLE links ADD COLUMN ranking INTEGER DEFAULT 0");
        }

        // Migrazione per la tabella users
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL
            )
        ");

        // Inserisci un utente admin di default
        $adminPassword = password_hash('12345678', PASSWORD_BCRYPT);
        $this->connection->exec("
            INSERT OR IGNORE INTO users (username, password) VALUES ('admin', '$adminPassword')
        ");

        // Aggiungi la colonna owner alla tabella links se non esiste già
        $result = $this->connection->query("PRAGMA table_info(links)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $ownerExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'owner') {
                $ownerExists = true;
                break;
            }
        }
        if (!$ownerExists) {
            $this->connection->exec("ALTER TABLE links ADD COLUMN owner INTEGER");
        }

        // Aggiungi la colonna owner alla tabella podcasts se non esiste già
        $result = $this->connection->query("PRAGMA table_info(podcasts)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $ownerExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'owner') {
                $ownerExists = true;
                break;
            }
        }
        if (!$ownerExists) {
            $this->connection->exec("ALTER TABLE podcasts ADD COLUMN owner INTEGER");
        }

        // Migrazione per la tabella openai_logs
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS openai_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                tokens_used INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}
