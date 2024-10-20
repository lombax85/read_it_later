<?php

namespace App\Services;

use OpenAI;
use App\Models\Podcast;
use App\Models\Link;

class ChatService {
    private $client;

    public function __construct() {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->client = OpenAI::client($apiKey);
    }

    public function generateReply($articleContent, $userMessage, $history) {
        $messages = [
            ['role' => 'system', 'content' => "Sei un assistente che risponde a domande su un articolo specifico. Ecco il contenuto dell'articolo:\n\n" . $articleContent],
        ];

        // Aggiungi la cronologia della chat
        foreach ($history as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue; // Salta i messaggi non validi
            }
            $messages[] = $message;
        }

        // Aggiungi il messaggio dell'utente
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }

    public function generatePodcastResponse($transcription, $podcastId, $conversationHistory = []) {
        $podcast = Podcast::getByFilename($podcastId);
        if (!$podcast) {
            throw new \Exception('Podcast non trovato');
        }

        $linkIds = $podcast->getLinkIds();
        $articlesContent = $this->getArticlesContent($linkIds);
        $podcastScript = $this->getPodcastScript($linkIds);

        $systemPrompt = "Sei un assistente esperto che risponde a domande su un podcast specifico, nella lingua in cui l'utente ha effettuato la domanda. " .
                        "L'utente sta ascoltando un podcast, ma ha interrotto la riproduzione per fare una domanda di approfondimento. " .
                        "Ti fornirò il testo degli articoli originali e lo script del podcast. " .
                        "Dovrai rispondere contestualmente in base a queste informazioni, fornendo risposte concise e pertinenti. " .
                        "Ecco il contenuto degli articoli originali:\n\n" . $articlesContent . "\n\n" .
                        "E questo è lo script del podcast:\n\n" . $podcastScript;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Aggiungi la cronologia della conversazione
        foreach ($conversationHistory as $message) {
            $messages[] = $message;
        }

        // Aggiungi la nuova domanda dell'utente
        $messages[] = ['role' => 'user', 'content' => $transcription];

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }

    private function getArticlesContent($linkIds) {
        $content = "";
        foreach ($linkIds as $id) {
            $link = Link::getById($id);
            if ($link) {
                $content .= "Articolo: " . $link->getTitle() . "\n";
                $content .= $link->getContent() . "\n\n";
            }
        }
        return $content;
    }

    private function getPodcastScript($linkIds) {
        $script = "";
        foreach ($linkIds as $id) {
            $link = Link::getById($id);
            if ($link) {
                $script .= $link->getContent() . "\n\n";
            }
        }
        return $script;
    }
}
