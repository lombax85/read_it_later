<?php

namespace App\Services;

use OpenAI;

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
            'max_tokens' => 150,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }
}
