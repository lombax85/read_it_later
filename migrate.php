<?php

require_once __DIR__ . '/vendor/autoload.php'; // Assicurati che il percorso sia corretto

use App\Database;

try {
    $db = Database::getInstance(); // Inizializza il database
    echo "Migrazioni eseguite con successo.";
} catch (Exception $e) {
    echo "Errore durante l'esecuzione delle migrazioni: " . $e->getMessage();
}
