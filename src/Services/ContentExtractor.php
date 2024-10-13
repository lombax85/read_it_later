<?php

namespace App\Services;

use GuzzleHttp\Client;
use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class ContentExtractor {
    public function extract($url) {
        $html = $this->fetchContent($url);
        $readability = new Readability(new Configuration());

        try {
            $readability->parse($html);
            return [
                'title' => $readability->getTitle(),
                'content' => $readability->getContent()
            ];
        } catch (\Exception $e) {
            error_log("Errore nell'estrazione del contenuto: " . $e->getMessage());
            return null;
        }
    }

    private function fetchContent($url) {
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);
        return $response->getBody()->getContents();
    }
}
