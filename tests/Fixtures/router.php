<?php

/**
 * Petit serveur de test lancé via `php -S 127.0.0.1:PORT router.php`
 * (cf. Http.php ne prend pas d'injection de transport -- on teste donc
 * `Http`/`AlphaPayClient` contre un vrai serveur HTTP local plutôt qu'un
 * mock, en conditions plus proches du réel). Chaque route reproduit un
 * scénario précis de l'enveloppe StandardJSONRenderer côté AlphaPayBack.
 *
 * Les scénarios qui nécessitent un état entre deux requêtes (429 puis
 * succès) utilisent un compteur dans un fichier temporaire, nettoyé par
 * chaque test via resetCounter() -- le serveur de dev PHP traite les
 * requêtes une par une, un fichier suffit, pas besoin d'un vrai store partagé.
 */

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function counterFile(string $name): string
{
    return sys_get_temp_dir() . "/alphapay_php_sdk_test_{$name}.counter";
}

function bumpCounter(string $name): int
{
    $file = counterFile($name);
    $n = file_exists($file) ? (int) file_get_contents($file) : 0;
    $n++;
    file_put_contents($file, (string) $n);
    return $n;
}

if ($path === '/transactions/tx_1/' && $method === 'GET') {
    echo json_encode(['success' => true, 'data' => ['id' => 'tx_1', 'reference' => 'REF1'], 'code' => 200]);
    exit;
}

if ($path === '/settlements/' && $method === 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => ['amount' => ['Ce champ est requis.']], 'code' => 400]);
    exit;
}

if ($path === '/retry-then-success/' && $method === 'GET') {
    $n = bumpCounter('retry_then_success');
    if ($n < 2) {
        http_response_code(429);
        header('Retry-After: 0');
        echo json_encode(['success' => false, 'error' => ['detail' => 'Throttled'], 'code' => 429]);
        exit;
    }
    echo json_encode(['success' => true, 'data' => ['id' => 'ok'], 'code' => 200]);
    exit;
}

if ($path === '/always-429/' && $method === 'GET') {
    bumpCounter('always_429');
    http_response_code(429);
    header('Retry-After: 0');
    echo json_encode(['success' => false, 'error' => ['detail' => 'Throttled'], 'code' => 429]);
    exit;
}

if ($path === '/echo-headers/' && $method === 'POST') {
    $headers = getallheaders();
    echo json_encode(['success' => true, 'data' => ['idempotencyKey' => $headers['Idempotency-Key'] ?? null], 'code' => 201]);
    exit;
}

if ($path === '/payment-links/public/demo-slug/' && $method === 'GET') {
    echo json_encode([
        'success' => true,
        'data' => [
            'slug' => 'demo-slug',
            'facebook_pixel_id' => '123456789012345',
            'google_ads_id' => 'AW-123456789',
            'custom_fields' => [['key' => 'reference_client', 'label' => 'Référence client', 'required' => true]],
        ],
        'code' => 200,
    ]);
    exit;
}

if ($path === '/payment-links/' && $method === 'POST') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    echo json_encode(['success' => true, 'data' => $body, 'code' => 201]);
    exit;
}

if ($path === '/payment-links/public/demo-slug/checkout/' && $method === 'POST') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    echo json_encode([
        'success' => true,
        'data' => [
            'slug' => 'checkout-slug',
            'checkout_url' => 'https://checkout.example.test/checkout-slug',
            'received' => $body,
        ],
        'code' => 201,
    ]);
    exit;
}

if ($path === '/transactions/' && $method === 'GET') {
    // Reflète TransactionListView (CreatedAtCursorPagination) : pas de "count".
    echo json_encode([
        'success' => true,
        'data' => ['next' => "http://{$_SERVER['HTTP_HOST']}/transactions/page2/", 'previous' => null, 'results' => [['id' => 'tx_1']]],
        'code' => 200,
    ]);
    exit;
}

if ($path === '/transactions/page2/' && $method === 'GET') {
    echo json_encode([
        'success' => true,
        'data' => ['next' => null, 'previous' => "http://{$_SERVER['HTTP_HOST']}/transactions/", 'results' => [['id' => 'tx_2']]],
        'code' => 200,
    ]);
    exit;
}

if ($path === '/reset-counters/' && $method === 'POST') {
    foreach (['retry_then_success', 'always_429'] as $name) {
        @unlink(counterFile($name));
    }
    echo json_encode(['success' => true, 'data' => null, 'code' => 200]);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'error' => ['detail' => "Not found: {$path}"], 'code' => 404]);
