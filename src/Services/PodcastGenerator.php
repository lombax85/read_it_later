<?php

namespace App\Services;

use OpenAI;
use App\Models\Link;

class PodcastGenerator {
    private $openaiClient;
    private $contentExtractor;

    public function __construct() {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if (!$apiKey) {
            throw new \Exception('OPENAI_API_KEY non è stata impostata');
        }
        $this->openaiClient = OpenAI::client($apiKey);
        $this->contentExtractor = new ContentExtractor();
    }

    public function generate($linkIds, $length = 'medio', $language = 'italiano') {
        $contents = $this->getContents($linkIds);
        $script = $this->generateScript($contents, $length, $language);
        $audioFile = $this->generateAudio($script, $language);
        return $audioFile;
    }

    private function getContents($linkIds) {
        $contents = [];
        foreach ($linkIds as $id) {
            $link = Link::getById($id);
            if ($link) {
                $content = $this->contentExtractor->extract($link->getUrl());
                if ($content) {
                    $contents[] = $content['content'];
                }
            }
        }
        return $contents;
    }

    private function generateScript($contents, $length, $language) {
        $combinedContent = implode("\n\n", $contents);
        $lengthPrompt = $this->getLengthPrompt($length);
        $languagePrompt = $this->getLanguagePrompt($language);

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => "Sei un esperto creatore di podcast. Crea uno script scorrevole e piacevole da ascoltare basato sui seguenti contenuti. $lengthPrompt $languagePrompt"],
                ['role' => 'user', 'content' => $combinedContent],
            ],
            'max_tokens' => 4000,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }

    private function getLengthPrompt($length) {
        switch ($length) {
            case 'breve':
                return 'Lo script deve essere conciso e durare circa 5 minuti.';
            case 'lungo':
                return 'Lo script deve essere dettagliato e durare circa 15 minuti.';
            default:
                return 'Lo script deve avere una lunghezza media e durare circa 10 minuti.';
        }
    }

    private function getLanguagePrompt($language) {
        return "Genera lo script in $language.";
    }

    private function generateAudio($script, $language) {
        $segments = $this->splitScript($script);
        $audioFiles = [];

        foreach ($segments as $index => $segment) {
            $response = $this->openaiClient->audio()->speech([
                'model' => 'tts-1',
                'voice' => $this->getVoiceForLanguage($language),
                'input' => $segment
            ]);

            $fileName = "podcast_segment_{$index}.mp3";
            $filePath = __DIR__ . "/../../public/podcasts/{$fileName}";
            
            // Modifica qui: salva direttamente il contenuto della risposta
            file_put_contents($filePath, $response);
            $audioFiles[] = $filePath;
        }

        $mergedFileName = 'podcast_' . time() . '.mp3';
        $mergedFilePath = __DIR__ . "/../../public/podcasts/{$mergedFileName}";
        $this->mergeAudioFiles($audioFiles, $mergedFilePath);

        // Pulizia dei file temporanei
        foreach ($audioFiles as $file) {
            unlink($file);
        }

        return "/podcasts/{$mergedFileName}";
    }

    private function splitScript($script) {
        $words = explode(' ', $script);
        $segments = [];
        $currentSegment = '';

        foreach ($words as $word) {
            if (strlen($currentSegment) + strlen($word) + 1 > 4000) {
                $segments[] = trim($currentSegment);
                $currentSegment = '';
            }
            $currentSegment .= $word . ' ';
        }

        if (!empty($currentSegment)) {
            $segments[] = trim($currentSegment);
        }

        return $segments;
    }

    private function mergeAudioFiles($files, $output) {
        $command = "ffmpeg -i \"concat:" . implode('|', $files) . "\" -acodec copy {$output}";
        exec($command);
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
