<?php

namespace App\Services;

use OpenAI;
use App\Models\Link;
use App\Models\Podcast;

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
        $title = $this->generateTitle($contents);
        $audioFile = $this->generateAudio($script, $language);

        $podcast = new Podcast($audioFile, $title);
        $podcast->save();

        return ['audioFile' => $audioFile, 'title' => $title];
    }

    private function getContents($linkIds) {
        $contents = [];
        foreach ($linkIds as $id) {
            $link = Link::getById($id);
            if ($link) {
                $content = $link->getContent();
                if ($content) {
                    $contents[] = $content;
                }
            }
        }
        return $contents;
    }

    private function generateScript($contents, $length, $language) {
        // Modifichiamo questa parte per assicurarci che tutti i contenuti vengano considerati
        $combinedContent = "";
        foreach ($contents as $index => $content) {
            $combinedContent .= "Articolo " . ($index + 1) . ":\n" . $content . "\n\n";
        }

        $lengthPrompt = $this->getLengthPrompt($length);
        $languagePrompt = $this->getLanguagePrompt($language);

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => "Sei un podcaster esperto. Crea uno script scorrevole e piacevole da ascoltare basato sui seguenti contenuti. Non mettere mai placeholder per il nome del podcaster nello script, il podcaster sei tu. Assicurati di includere informazioni da tutti gli articoli forniti. $lengthPrompt $languagePrompt"],
                ['role' => 'user', 'content' => $combinedContent],
            ],
            'max_tokens' => 8000,
            'temperature' => 0.7,
        ]);

        return $response->choices[0]->message->content;
    }

    private function getLengthPrompt($length) {
        switch ($length) {
            case 'breve':
                return 'Lo script deve essere conciso e di circa 500 parole. La lunghezza è molto importante e cerca di rispettarla.';
            case 'lungo':
                return 'Lo script deve essere molto dettagliato e di circa 4000 parole. La lunghezza è molto importante e cerca di rispettarla.';
            default:
                return 'Lo script deve avere una lunghezza media con circa 1500 parole. La lunghezza è molto importante e cerca di rispettarla.';
        }
    }

    private function getLanguagePrompt($language) {
        return "Genera lo script in $language.";
    }

    private function generateTitle($contents) {
        $combinedContent = implode("\n\n", array_slice($contents, 0, 3)); // Usiamo solo i primi 3 contenuti per brevità

        $response = $this->openaiClient->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Sei un assistente che genera titoli brevi e accattivanti. Genera un titolo di massimo 5 parole che riassuma il tema principale dei contenuti forniti.'],
                ['role' => 'user', 'content' => $combinedContent],
            ],
            'max_tokens' => 20,
            'temperature' => 0.7,
        ]);

        return trim($response->choices[0]->message->content);
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
