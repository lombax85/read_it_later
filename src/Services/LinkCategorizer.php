<?php

namespace App\Services;

use OpenAI;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class LinkCategorizer {
    private $openaiClient;
    private $httpClient;

    public function __construct() {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->openaiClient = OpenAI::client($apiKey);
        $this->httpClient = new Client([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    public function categorize($url) {
        $content = $this->fetchContent($url);

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Sei un assistente che categorizza URL in base al loro contenuto. Rispondi solo con una singola parola che rappresenta la categoria.'],
                ['role' => 'user', 'content' => "Categorizza il seguente URL e il suo contenuto:\n\nURL: $url\n\nContenuto: $content"],
            ],
            'max_tokens' => 50,
            'temperature' => 0.3,
        ]);

        $category = trim($response->choices[0]->message->content);
        return $category ?: 'Uncategorized';
    }

    private function fetchContent($url) {
        try {
            $response = $this->httpClient->get($url);
            $html = $response->getBody()->getContents();
            
            // Estraiamo il testo dal contenuto HTML
            $text = strip_tags($html);
            
            // Limitiamo la lunghezza del contenuto per evitare di superare i limiti dell'API di OpenAI
            return substr($text, 0, 100000) . '...';
        } catch (RequestException $e) {
            // Gestione degli errori
            error_log("Errore nel recupero del contenuto da $url: " . $e->getMessage());
            return "Impossibile recuperare il contenuto da $url";
        }
    }
}
