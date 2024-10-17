<?php

namespace App\Services;

use OpenAI;

class TextToSpeechService {
    private $client;

    public function __construct() {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->client = OpenAI::client($apiKey);
    }

    public function generateSpeech($text) {
        $response = $this->client->audio()->speech([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'alloy'
        ]);

        $filename = 'response_' . time() . '.mp3';
        $filePath = __DIR__ . '/../../public/temp/' . $filename;
        file_put_contents($filePath, $response);

        return '/temp/' . $filename;
    }
}
