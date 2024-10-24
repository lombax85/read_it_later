<?php

namespace App\Services;

use OpenAI;

class TextToSpeechService {
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

    public function generateSpeech($text) {
        $response = $this->client->audio()->speech([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => $this->getVoiceForLanguage('italiano') // TODO: fix
        ]);

        // Log the OpenAI call
        (new Logger())->logOpenAICall(
            $this->owner, 
            'audio/speech;TextToSpeechService::generateSpeech', 
            0
        );

        $filename = 'response_' . time() . '.mp3';
        $filePath = __DIR__ . '/../../public/temp/' . $filename;
        file_put_contents($filePath, $response);

        return '/temp/' . $filename;
    }

    private function getVoiceForLanguage($language) {
        switch ($language) {
            case 'inglese':
                return 'alloy';
            case 'italiano':
                return 'nova';
            case 'francese':
                return 'lea';
            case 'spagnolo':
                return 'bella';
            case 'tedesco':
                return 'onyx';
            default:
                return 'alloy';
        }
    }
}
