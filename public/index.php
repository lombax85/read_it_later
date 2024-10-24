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
use App\Services\TranscriptionService;
use App\Services\TextToSpeechService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use App\Database;

global $globalUserID;

// Inizializzazione dell'applicazione
$app = new App();


// login route
$app->get('/login', function ($request, $response) {
    return $response->write(file_get_contents(__DIR__ . '/login.html'));
});

// post login route
$app->post('/login', function ($request, $response) {
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    $db = Database::getInstance()->getConnection(); // Get the PDO instance from the Database class
    $userModel = new User($db);
    $user = $userModel->findByUsername($username);

    error_log('Verifica password: ' . password_verify($password, $user['password']));
    if ($user && password_verify($password, $user['password'])) {
        $token = generateJWT($user['id']);
        return $response->withJson(['token' => $token]);
    } else {
        return $response->withStatus(401)->withJson(['error' => 'Invalid credentials']);
    }
});

// auth middleware
$app->add(function ($request, $response, $next) {

    $allowedPaths = ['/login', '/'];
    if (in_array($request->getUri()->getPath(), $allowedPaths)) {
        error_log('Allowed path: ' . $request->getUri()->getPath());
        $response = $next($request, $response);
        return $response;
    }
    
    $token = $request->getHeaderLine('Authorization');
    // remove Bearer from the token
    $token = str_replace('Bearer ', '', $token);
    // check if token is valid and not expired 
    try {
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
        global $globalUserID;
        $globalUserID = $decoded->sub;
    } catch (Exception $e) {
        error_log('JWT Token is invalid: ' . $e->getMessage() . " in token " . $token);
        header('Location: /login');
        exit;
    }

    $response = $next($request, $response);
    return $response;
});


// Definizione delle route
$app->get('/', function ($request, $response) {
    return $response->write(file_get_contents(__DIR__ . '/index.html'));
});

// API routes
$app->get('/api/links', function ($request, $response) {
    global $globalUserID;
    error_log('Global user: ' . print_r($globalUserID, true));
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

// Aggiungi questo nuovo endpoint dopo gli altri
$app->post('/api/process-audio', function ($request, $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $audioFile = $uploadedFiles['audio'];
    $podcastId = $request->getParsedBody()['podcastId'] ?? null;
    $conversationHistory = json_decode($request->getParsedBody()['conversationHistory'] ?? '[]', true);

    if ($audioFile->getError() === UPLOAD_ERR_OK && $podcastId) {
        $filename = moveUploadedFile($audioFile);
        $transcriptionService = new TranscriptionService();
        $userTranscription = $transcriptionService->transcribe($filename);

        $chatService = new ChatService();
        $aiResponse = $chatService->generatePodcastResponse($userTranscription, $podcastId, $conversationHistory);

        $ttsService = new TextToSpeechService();
        $audioUrl = $ttsService->generateSpeech($aiResponse);

        cleanupTempFiles();

        return $response->withJson([
            'audioUrl' => $audioUrl,
            'userTranscription' => $userTranscription,
            'aiResponse' => $aiResponse
        ]);
    }

    return $response->withStatus(400)->withJson(['error' => 'Errore nel caricamento del file audio o ID del podcast mancante']);
});

$app->post('/api/links/{id}/ranking', function ($request, $response, $args) {
    $id = $args['id'];
    $data = $request->getParsedBody();
    $newRanking = $data['ranking'];

    $link = Link::getById($id);
    if ($link) {
        $link->setRanking($newRanking);
        $link->save();
        return $response->withJson(['success' => true]);
    } else {
        return $response->withStatus(404)->withJson(['error' => 'Link non trovato']);
    }
});

function moveUploadedFile($uploadedFile) {
    $directory = __DIR__ . '/uploads';
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

function cleanupTempFiles() {
    $tempDir = __DIR__ . '/temp';
    $files = glob($tempDir . '/*');
    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 3600) { // Rimuovi file più vecchi di 1 ora
                unlink($file);
            }
        }
    }
}

// Esecuzione dell'applicazione
$app->run();

// Add this function to generate JWT
function generateJWT($userId)
{
    $key = $_ENV['JWT_SECRET'];
    $payload = [
        'iat' => time(), // Issued at
        'exp' => time() + 86400, 
        'sub' => $userId 
    ];

    return JWT::encode($payload, $key, 'HS256');
}

