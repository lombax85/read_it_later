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

    // Estrai il contenuto
    $extractor = new ContentExtractor();
    $content = $extractor->extract($url);

    if ($content) {
        // Genera il riassunto
        $generator = new SummaryGenerator();
        $summary = $generator->generate($content['content'], $summaryLength, $language);

        // Categorizza il link
        $categorizer = new LinkCategorizer();
        $category = $categorizer->categorize($url);

        // Salva il link
        $link = new Link($url, $content['title'], $category);
        $link->setSummary($summary);
        $link->save();

        return $response->withJson([
            'link' => $link,
            'summary' => $summary
        ], 201);
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

$app->post('/api/generate-podcast', function ($request, $response) {
    $data = $request->getParsedBody();
    $linkIds = array_column($data['links'], 'id');

    $podcastGenerator = new PodcastGenerator();
    $podcastUrl = $podcastGenerator->generate($linkIds);

    return $response->withJson(['podcastUrl' => $podcastUrl]);
});

$app->get('/api/podcasts', function ($request, $response) {
    $podcastDir = __DIR__ . '/podcasts';
    $podcasts = glob($podcastDir . '/*.mp3');
    $podcastUrls = array_map(function ($path) {
        return '/podcasts/' . basename($path);
    }, $podcasts);

    return $response->withJson($podcastUrls);
});

// Esecuzione dell'applicazione
$app->run();