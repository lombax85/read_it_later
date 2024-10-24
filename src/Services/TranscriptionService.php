<?php

namespace App\Services;

use OpenAI;

class TranscriptionService {
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

    public function transcribe($filename) {
        $response = $this->client->audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen(__DIR__ . '/../../public/uploads/' . $filename, 'r'),
            'response_format' => 'text'
        ]);

        // Log the OpenAI call
        (new Logger())->logOpenAICall(
            $this->owner, 
            'audio/transcribe;TranscriptionService::transcribe', 
            0
        );

        return $response->text;
    }
}
