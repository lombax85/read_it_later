<?php

namespace App\Services;

use OpenAI;

class SummaryGenerator {
    private $client;
    private $owner;

    public function __construct($owner) {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->client = OpenAI::client($apiKey);
        $this->owner = $owner;
    }

    public function generate($content, $length = 'medio', $language = 'italiano') {
        $promptLength = $this->getLengthPrompt($length);
        $languagePrompt = $this->getLanguagePrompt($language);

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => "Sei un assistente che crea riassunti dettagliati e schematici. $languagePrompt"],
                ['role' => 'user', 'content' => "Riassumi il seguente testo in modo $promptLength e schematico, utilizzando elenchi puntati dove appropriato per migliorare la chiarezza:\n\n" . $content],
            ],
            'max_tokens' => 4096,
            'temperature' => 0.5,
        ]);

        // Log the OpenAI call
        (new Logger())->logOpenAICall(
            $this->owner, 
            'chat/create;SummaryGenerator::generate', 
            $response->usage->totalTokens
        );

        return $response->choices[0]->message->content;
    }

    private function getLengthPrompt($length) {
        switch (strtolower($length)) {
            case 'breve':
                return 'conciso';
            case 'lungo':
                return 'dettagliato';
            default:
                return 'equilibrato';
        }
    }

    private function getLanguagePrompt($language) {
        return "Genera il riassunto in $language.";
    }
}
