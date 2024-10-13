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
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                title TEXT NOT NULL,
                category TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Aggiungiamo la colonna summary se non esiste già
        $result = $this->connection->query("PRAGMA table_info(links)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $summaryExists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'summary') {
                $summaryExists = true;
                break;
            }
        }
        if (!$summaryExists) {
            $this->connection->exec("ALTER TABLE links ADD COLUMN summary TEXT");
        }
    }
}
