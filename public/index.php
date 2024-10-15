<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Verifica che il file .env esista
if (!file_exists(__DIR__ . '/../.env')) {
    die('Il file .env non esiste');
}

// Carica le variabili d'ambiente dal file .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();

// Verifica che la chiave API sia stata caricata
if (!isset($_ENV['OPENAI_API_KEY'])) {
    die('OPENAI_API_KEY non è stata impostata nel file .env');
}


use Slim\App;
use App\Models\Link;
use App\Services\ContentExtractor;
use App\Services\SummaryGenerator;
use App\Services\LinkCategorizer;
use App\Services\PodcastGenerator;
use App\Models\Podcast;
use App\Services\ChatService;

// Inizializzazione dell'applicazione
$app = new App();

// Definizione delle route
$app->get('/', function ($request, $response) {
    return $response->write(file_get_contents(__DIR__ . '/index.html'));
});

// API routes
$app->get('/api/links', function ($request, $response) {
    $links = Link::getAll();
    return $response->withJson($links);
});

$app->post('/api/links', function ($request, $response) {
    $data = $request->getParsedBody();
    $link = new Link($data['url'], $data['title'], $data['category']);
    $link->save();
    return $response->withJson($link, 201);
});

$app->post('/api/summary', function ($request, $response) {
    $data = $request->getParsedBody();
    $extractor = new ContentExtractor();
    $content = $extractor->extract($data['url']);
    
    if ($content) {
        $generator = new SummaryGenerator();
        $summary = $generator->generate($content['content']);
        return $response->withJson(['summary' => $summary]);
    } else {
        return $response->withStatus(400)->withJson(['error' => 'Impossibile estrarre il contenuto']);
    }
});

$app->post('/api/add-and-summarize', function ($request, $response) {
    $data = $request->getParsedBody();
    $url = $data['url'];
    $summaryLength = $data['summaryLength'] ?? 'medio';
    $language = $data['language'] ?? 'italiano';
    $manualContent = $data['manualContent'] ?? null;

    // Estrai il contenuto
    $extractor = new ContentExtractor();
    $content = $extractor->extract($url, $manualContent);

    if ($content) {
        try {
            // Genera il riassunto
            $generator = new SummaryGenerator();
            $summary = $generator->generate($content['content'], $summaryLength, $language);

            // Categorizza il link
            $categorizer = new LinkCategorizer();
            $category = $categorizer->categorize($url, $manualContent);

            // Salva il link
            $link = new Link($url, $content['title'], $category);
            $link->setSummary($summary);
            $link->setContent($content['content']);
            $link->save();

            return $response->withJson([
                'link' => $link,
                'summary' => $summary
            ], 201);
        } catch (Exception $e) {
            // Log dell'errore
            error_log('Errore durante la generazione del riassunto o il salvataggio del link: ' . $e->getMessage());
            
            // Restituisci una risposta di errore
            return $response->withStatus(500)->withJson(['error' => 'Si è verificato un errore durante l\'elaborazione del link']);
        }
    } else {
        return $response->withStatus(400)->withJson(['error' => 'Impossibile estrarre il contenuto']);
    }
});

$app->post('/api/summary/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $link = Link::getById($args['id']);
    
    if ($link) {
        $generator = new SummaryGenerator();
        $summary = $generator->generate($data['content']);
        $link->updateSummary($summary);
        return $response->withJson(['summary' => $summary]);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

$app->get('/api/summary/{id}', function ($request, $response, $args) {
    $link = Link::getById($args['id']);
    
    if ($link && $link->getSummary()) {
        return $response->withJson(['summary' => $link->getSummary()]);
    } elseif ($link) {
        return $response->withStatus(404)->withJson(['error' => 'Riassunto non trovato']);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

$app->delete('/api/links/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $link = Link::getById($id);
    
    if ($link) {
        $link->delete();
        return $response->withStatus(204);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

// Modifica la route per la generazione del podcast
$app->post('/api/generate-podcast', function ($request, $response) {
    $data = $request->getParsedBody();
    $linkIds = array_column($data['links'], 'id');
    $length = $data['length'] ?? 'medio';
    $language = $data['language'] ?? 'italiano';

    $podcastGenerator = new PodcastGenerator();
    $result = $podcastGenerator->generate($linkIds, $length, $language);

    return $response->withJson([
        'podcastUrl' => $result['audioFile'],
        'podcastTitle' => $result['title']
    ]);
});

// Modifica la route per ottenere la lista dei podcast
$app->get('/api/podcasts', function ($request, $response) {
    $podcasts = Podcast::getAll();
    $podcastInfo = array_map(function ($podcast) {
        return [
            'url' => $podcast['filename'],
            'date' => $podcast['created_at'],
            'title' => $podcast['title']
        ];
    }, $podcasts);

    return $response->withJson($podcastInfo);
});

// Aggiungi questa nuova route dopo le altre route esistenti
$app->delete('/api/podcasts/{filename}', function ($request, $response, $args) {
    $filename = $args['filename'];
    $podcastPath = __DIR__ . '/podcasts/' . $filename;

    error_log("Podcast path: " . $podcastPath);

    if (file_exists($podcastPath)) {
        Podcast::delete('/podcasts/' .$filename);
        unlink($podcastPath);
        return $response->withStatus(204);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Podcast non trovato']);
    }
});

$app->post('/api/links/{id}/read', function ($request, $response, $args) {
    $id = $args['id'];
    $link = Link::getById($id);
    
    if ($link) {
        $link->setRead(true);
        $link->save();
        return $response->withJson(['success' => true]);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

$app->post('/api/links/{id}/unread', function ($request, $response, $args) {
    $id = $args['id'];
    $link = Link::getById($id);
    
    if ($link) {
        $link->setRead(false);
        $link->save();
        return $response->withJson(['success' => true]);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

// Aggiungi questa nuova route dopo le altre route esistenti
$app->post('/api/chat', function ($request, $response) {
    $data = $request->getParsedBody();
    $linkId = $data['linkId'];
    $message = $data['message'];
    $history = $data['history'];

    $link = Link::getById($linkId);
    if (!$link) {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }

    $chatService = new ChatService();
    $reply = $chatService->generateReply($link->getContent(), $message, $history);

    return $response->withJson(['reply' => $reply]);
});

// Esecuzione dell'applicazione
$app->run();