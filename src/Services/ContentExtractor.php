<?php

namespace App\Services;

use GuzzleHttp\Client;
use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class ContentExtractor {
    public function extract($url, $manualContent = null) {
        if ($manualContent !== null) {
            // Se c'è contenuto manuale, genera un titolo da esso
            $title = $this->generateTitleFromContent($manualContent);
            return [
                'title' => $title,
                'content' => $manualContent
            ];
        }

        $html = $this->fetchContent($url);
        $readability = new Readability(new Configuration());

        try {
            $readability->parse($html);
            return [
                'title' => $readability->getTitle(),
                'content' => $readability->getContent()
            ];
        } catch (\Exception $e) {
            error_log("Errore nell'estrazione del contenuto: " . $e->getMessage());
            return null;
        }
    }

    private function generateTitleFromContent($content) {
        // Estrai le prime parole del contenuto come titolo
        $words = str_word_count(strip_tags($content), 1);
        $title = implode(' ', array_slice($words, 0, 20)); // Prendi le prime 20 parole
        return $title . '...'; // Aggiungi puntini di sospensione
    }

    private function fetchContent($url) {
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);
        return $response->getBody()->getContents();
    }
}
