<?php

namespace App\Services;

use OpenAI;

class TranscriptionService {
    private $client;

    public function __construct() {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->client = OpenAI::client($apiKey);
    }

    public function transcribe($filename) {
        $response = $this->client->audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen(__DIR__ . '/../../public/uploads/' . $filename, 'r'),
            'response_format' => 'text'
        ]);

        return $response->text;
    }
}
